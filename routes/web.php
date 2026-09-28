<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EconomicCalendarController;
use App\Http\Controllers\FuturesSignalController;
use App\Http\Controllers\MoneyFlowController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\SpotSignalController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TradingViewBridgeController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/demo-login', [AuthController::class, 'demoLogin'])->name('demo.login');
});

// Authenticated Application Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/live-data', [DashboardController::class, 'liveData'])->name('dashboard.live');

    // Trade Journal Routes
    Route::post('/trades/preview-calc', [TradeController::class, 'calculatePreview'])->name('trades.preview-calc');
    Route::resource('trades', TradeController::class);

    // Multi-Account Portfolio Routes
    Route::resource('accounts', AccountController::class)->only(['index', 'store', 'update', 'destroy']);

    // Deep Analytics & Evaluation
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Coin Hunter Spot Scanner Engine v1.6
    Route::get('/signals', [SpotSignalController::class, 'index'])->name('signals.index');
    Route::get('/signals/live-prices', [SpotSignalController::class, 'livePrices'])->name('signals.live');
    Route::get('/signals/live-signals', [SpotSignalController::class, 'liveSignals'])->name('signals.live.signals');
    Route::post('/signals/scan', [SpotSignalController::class, 'scan'])->name('signals.scan');
    Route::get('/signals/chart-data/{symbol}', [SpotSignalController::class, 'chartData'])->name('signals.chart');
    Route::post('/signals/journal-instant', [SpotSignalController::class, 'journalInstant'])->name('signals.journal.instant');
    Route::post('/signals/{id}/journal', [SpotSignalController::class, 'convertToJournal'])->name('signals.journal');

    Route::get('/futures-signals', [FuturesSignalController::class, 'index'])->name('futures-signals.index');
    Route::get('/futures-signals/live-signals', [FuturesSignalController::class, 'liveSignals'])->name('futures-signals.live.signals');
    Route::post('/futures-signals/scan', [FuturesSignalController::class, 'scan'])->name('futures-signals.scan');
    Route::get('/futures-signals/chart-data/{symbol}', [FuturesSignalController::class, 'chartData'])->name('futures-signals.chart');

    // Simulasi Trade & Strategy Backtesting Engine
    Route::get('/simulation', [SimulationController::class, 'index'])->name('simulation.index');
    Route::post('/simulation/backtest/run', [SimulationController::class, 'runBacktest'])->name('simulation.backtest.run');
    Route::get('/simulation/backtest/history', [SimulationController::class, 'backtestHistory'])->name('simulation.backtest.history');
    Route::get('/simulation/backtest/history/pdf', [SimulationController::class, 'downloadBacktestHistoryPdf'])->name('simulation.backtest.history.pdf');
    Route::delete('/simulation/backtest/history', [SimulationController::class, 'deleteAllBacktestHistory'])->name('simulation.backtest.history.destroy-all');
    Route::delete('/simulation/backtest/history/{id}', [SimulationController::class, 'deleteBacktestRun'])->name('simulation.backtest.history.destroy');
    Route::post('/simulation/backtest/batch', [SimulationController::class, 'runBatchBacktest'])->name('simulation.backtest.batch');
    Route::post('/simulation/paper/buy', [SimulationController::class, 'openPaperTrade'])->name('simulation.paper.buy');
    Route::post('/simulation/paper/{id}/close', [SimulationController::class, 'closePaperTrade'])->name('simulation.paper.close');
    Route::post('/simulation/reset', [SimulationController::class, 'resetAccount'])->name('simulation.reset');
    Route::get('/simulation/live-status', [SimulationController::class, 'livePaperStatus'])->name('simulation.live');

    // Berita Dunia (World News Aggregator)
    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    Route::get('/news/live-stream', [NewsController::class, 'liveStream'])->name('news.live');
    Route::get('/news/{id}', [NewsController::class, 'show'])->name('news.show');
    Route::get('/news/{id}/translate', [NewsController::class, 'translate'])->name('news.translate');

    // Kalender Ekonomi (Economic Calendar — FOMC / NFP / CPI)
    Route::get('/calendar', [EconomicCalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/events-data', [EconomicCalendarController::class, 'eventsData'])->name('calendar.events');

    // Money Flow & Whale Tracker
    Route::get('/money-flow', [MoneyFlowController::class, 'index'])->name('money-flow.index');
    Route::get('/money-flow/live-whales', [MoneyFlowController::class, 'liveWhales'])->name('money-flow.whales');
    Route::get('/money-flow/net-flow-matrix', [MoneyFlowController::class, 'netFlowMatrix'])->name('money-flow.matrix');
    Route::get('/money-flow/order-book', [MoneyFlowController::class, 'orderBook'])->name('money-flow.orderbook');

    // Profile & Security Settings
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');

    // TradingView Desktop Remote Bridge & OmniRoute AI Triangulation
    Route::get('/tradingview/status', [TradingViewBridgeController::class, 'status'])->name('tradingview.status');
    Route::post('/tradingview/launch-remote', [TradingViewBridgeController::class, 'launchRemote'])->name('tradingview.launch');
    Route::post('/tradingview/restart-clean', [TradingViewBridgeController::class, 'restartClean'])->name('tradingview.restart');
    Route::post('/tradingview/sync-symbol', [TradingViewBridgeController::class, 'syncSymbol'])->name('tradingview.sync');
    Route::post('/tradingview/watchlist-add', [TradingViewBridgeController::class, 'watchlistAdd'])->name('tradingview.watchlist');
    Route::post('/tradingview/alert-create', [TradingViewBridgeController::class, 'alertCreate'])->name('tradingview.alert');
    Route::post('/tradingview/ai-audit', [TradingViewBridgeController::class, 'aiAudit'])->name('tradingview.ai-audit');

    // API Aliases for seamless integration
    Route::get('/api/tradingview/status', [TradingViewBridgeController::class, 'status']);
    Route::post('/api/tradingview/launch-remote', [TradingViewBridgeController::class, 'launchRemote']);
    Route::post('/api/tradingview/restart-clean', [TradingViewBridgeController::class, 'restartClean']);
    Route::post('/api/tradingview/sync-symbol', [TradingViewBridgeController::class, 'syncSymbol']);
    Route::post('/api/tradingview/watchlist-add', [TradingViewBridgeController::class, 'watchlistAdd']);
    Route::post('/api/tradingview/alert-create', [TradingViewBridgeController::class, 'alertCreate']);
    Route::post('/api/tradingview/ai-audit', [TradingViewBridgeController::class, 'aiAudit']);
});

