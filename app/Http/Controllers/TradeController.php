<?php

namespace App\Http\Controllers;

use App\Events\AccountBalanceUpdated;
use App\Events\TradeCreated;
use App\Events\TradeDeleted;
use App\Events\TradeUpdated;
use App\Models\Account;
use App\Models\Strategy;
use App\Models\Tag;
use App\Models\Trade;
use App\Models\TradeAttachment;
use App\Models\User;
use App\Services\TradeCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TradeController extends Controller
{
    public function __construct(protected TradeCalculatorService $calculatorService)
    {
    }

    protected function getUser()
    {
        return Auth::user() ?? User::first();
    }

    public function index(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect('/');
        }

        $query = Trade::query()->whereHas('account', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['account', 'strategy', 'tags', 'attachments']);

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('symbol')) {
            $query->where('symbol', 'like', '%' . strtoupper($request->symbol) . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('strategy_id')) {
            $query->where('strategy_id', $request->strategy_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_at', '<=', $request->date_to);
        }

        $trades = $query->orderBy('entry_at', 'desc')->paginate(15)->withQueryString();
        $accounts = $user->accounts()->where('is_active', true)->get();
        $strategies = $user->strategies()->get();
        $tags = $user->tags()->get();

        return view('trades.index', compact('trades', 'accounts', 'strategies', 'tags', 'user'));
    }

    public function create()
    {
        $user = $this->getUser();
        $accounts = $user->accounts()->where('is_active', true)->get();
        $strategies = $user->strategies()->get();
        $tags = $user->tags()->get();

        return view('trades.create', compact('accounts', 'strategies', 'tags', 'user'));
    }

    public function store(Request $request)
    {
        $user = $this->getUser();

        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'strategy_id' => 'nullable|exists:strategies,id',
            'symbol' => 'required|string|max:30',
            'direction' => 'required|in:long,short',
            'status' => 'required|in:open,closed,cancelled',
            'entry_at' => 'required|date',
            'entry_price' => 'required|numeric|min:0.00000001',
            'exit_at' => 'nullable|date',
            'exit_price' => 'nullable|numeric|min:0',
            'quantity' => 'required|numeric|min:0.00000001',
            'stop_loss' => 'nullable|numeric|min:0',
            'take_profit' => 'nullable|numeric|min:0',
            'fees' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'lesson_learned' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'screenshot_before' => 'nullable|image|max:10240', // 10MB
            'screenshot_after' => 'nullable|image|max:10240',
        ]);

        $calculated = $this->calculatorService->calculate($validated);

        $tradeData = array_merge($validated, $calculated);
        $tradeData['symbol'] = strtoupper($tradeData['symbol']);
        $tradeData['fees'] = $tradeData['fees'] ?? 0;

        $trade = Trade::create($tradeData);

        // Attach Tags
        if ($request->filled('tags')) {
            $trade->tags()->sync($request->tags);
        }

        // Handle Screenshot Uploads
        if ($request->hasFile('screenshot_before')) {
            $path = $request->file('screenshot_before')->store('trade_attachments', 'public');
            TradeAttachment::create([
                'trade_id' => $trade->id,
                'file_path' => $path,
                'stage' => 'before_entry',
                'caption' => 'Chart Before Entry',
            ]);
        } elseif (app(\App\Services\TradingView\TradingViewRemoteService::class)->isConnected()) {
            try {
                $bridge = app(\App\Services\TradingView\TradingViewBridgeService::class);
                $rawBase64 = $bridge->captureScreenshot('trade_entry_' . $trade->symbol . '_' . time());
                if (!empty($rawBase64)) {
                    $fileName = 'trade_attachments/tv_' . strtolower($trade->symbol) . '_' . time() . '.png';
                    \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, base64_decode($rawBase64));
                    TradeAttachment::create([
                        'trade_id' => $trade->id,
                        'file_path' => $fileName,
                        'stage' => 'before_entry',
                        'caption' => 'TradingView Auto Proof-of-Trade (Live Capture)',
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::info("TradingView auto-attachment skipped: " . $e->getMessage());
            }
        }

        if ($request->hasFile('screenshot_after')) {
            $path = $request->file('screenshot_after')->store('trade_attachments', 'public');
            TradeAttachment::create([
                'trade_id' => $trade->id,
                'file_path' => $path,
                'stage' => 'after_exit',
                'caption' => 'Chart After Exit',
            ]);
        }

        // Recalculate account balance
        $trade->account->recalculateBalance();

        // Broadcast Real-time event
        broadcast(new TradeCreated($trade));
        broadcast(new AccountBalanceUpdated($trade->account));

        if ($request->wantsJson()) {
            return response()->json(['status' => 'success', 'trade' => $trade]);
        }

        return redirect()->route('trades.index')->with('success', 'Jurnal trade ' . $trade->symbol . ' berhasil dicatat.');
    }

    public function show(Trade $trade)
    {
        $trade->load(['account', 'strategy', 'tags', 'attachments']);

        if (request()->wantsJson()) {
            return response()->json($trade);
        }

        return view('trades.show', compact('trade'));
    }

    public function edit(Trade $trade)
    {
        $user = $this->getUser();
        $accounts = $user->accounts()->where('is_active', true)->get();
        $strategies = $user->strategies()->get();
        $tags = $user->tags()->get();

        $trade->load(['tags', 'attachments']);

        return view('trades.edit', compact('trade', 'accounts', 'strategies', 'tags', 'user'));
    }

    public function update(Request $request, Trade $trade)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'strategy_id' => 'nullable|exists:strategies,id',
            'symbol' => 'required|string|max:30',
            'direction' => 'required|in:long,short',
            'status' => 'required|in:open,closed,cancelled',
            'entry_at' => 'required|date',
            'entry_price' => 'required|numeric|min:0.00000001',
            'exit_at' => 'nullable|date',
            'exit_price' => 'nullable|numeric|min:0',
            'quantity' => 'required|numeric|min:0.00000001',
            'stop_loss' => 'nullable|numeric|min:0',
            'take_profit' => 'nullable|numeric|min:0',
            'fees' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'lesson_learned' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'screenshot_before' => 'nullable|image|max:10240',
            'screenshot_after' => 'nullable|image|max:10240',
        ]);

        $calculated = $this->calculatorService->calculate($validated);

        $tradeData = array_merge($validated, $calculated);
        $tradeData['symbol'] = strtoupper($tradeData['symbol']);
        $tradeData['fees'] = $tradeData['fees'] ?? 0;

        $trade->update($tradeData);

        if ($request->has('tags')) {
            $trade->tags()->sync($request->tags ?? []);
        }

        if ($request->hasFile('screenshot_before')) {
            $path = $request->file('screenshot_before')->store('trade_attachments', 'public');
            TradeAttachment::create([
                'trade_id' => $trade->id,
                'file_path' => $path,
                'stage' => 'before_entry',
                'caption' => 'Chart Before Entry',
            ]);
        }

        if ($request->hasFile('screenshot_after')) {
            $path = $request->file('screenshot_after')->store('trade_attachments', 'public');
            TradeAttachment::create([
                'trade_id' => $trade->id,
                'file_path' => $path,
                'stage' => 'after_exit',
                'caption' => 'Chart After Exit',
            ]);
        }

        $trade->account->recalculateBalance();

        broadcast(new TradeUpdated($trade));
        broadcast(new AccountBalanceUpdated($trade->account));

        return redirect()->route('trades.index')->with('success', 'Trade ' . $trade->symbol . ' berhasil diperbarui.');
    }

    public function destroy(Trade $trade)
    {
        $symbol = $trade->symbol;
        $tradeId = $trade->id;
        $userId = $trade->account->user_id;
        $account = $trade->account;

        foreach ($trade->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $trade->delete();
        $account->recalculateBalance();

        broadcast(new TradeDeleted($userId, $tradeId, $symbol));
        broadcast(new AccountBalanceUpdated($account));

        if (request()->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => "Trade {$symbol} deleted."]);
        }

        return redirect()->route('trades.index')->with('success', "Trade {$symbol} berhasil dihapus.");
    }

    public function calculatePreview(Request $request)
    {
        $calculated = $this->calculatorService->calculate($request->all());
        return response()->json($calculated);
    }
}
