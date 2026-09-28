# Technical Architecture: Spot Signal Engine v1
## System Design, Market Data Pipelines & Live Dispatcher

---

## 1. Engine System Architecture

```mermaid
graph TD
    subgraph Market Data Layer
        BinanceAPI[Binance Public Spot Kline API / Fallback Mock] --> MarketFetcher[MarketDataFetcherService]
        MarketFetcher --> KlineCache[Cache Store: Redis / File]
    end

    subgraph Core Algorithm Layer
        KlineCache --> SignalEngine[SpotSignalEngineService]
        SignalEngine --> IFVGModule[iFVG Inversion Detector]
        SignalEngine --> DiscountModule[Dealing Range & Discount Calc]
        SignalEngine --> SweepModule[Liquidity Sweep Grab Detector]
        SignalEngine --> PadawaeModule[PADAWAE Wave & Trendline Analyzer]
        
        IFVGModule --> Scorer[Rating & Score Compiler]
        DiscountModule --> Scorer
        SweepModule --> Scorer
        PadawaeModule --> Scorer
    end

    subgraph Presentation & Dispatch Layer
        Scorer -->|Score >= 80 (Grade A/A+)| SignalBroadcaster[SignalNotificationService]
        SignalBroadcaster --> ReverbWS[Laravel Reverb WebSocket]
        SignalBroadcaster --> SignalModel[Database: spot_signals]
        
        ReverbWS --> DashboardUI[Dashboard Live Signal Radar]
        DashboardUI --> SweetAlert[SweetAlert2 Live Buy Toast]
        DashboardUI --> AutoJournal[1-Click Quick Journal Entry]
    end
```

---

## 2. Component Specifications

### 2.1. `app/Services/MarketDataFetcherService.php`
- Bertanggung jawab mengambil data candlestick OHLCV real-time dari endpoint publik (tanpa memerlukan API key rahasia):
  - Endpoint: `https://api.binance.com/api/v3/klines?symbol={PAIR}&interval=1h&limit=100`
  - Fallback offline generator jika koneksi terputus untuk pengujian lokal.
  - Caching 60 detik untuk efisiensi jaringan.

### 2.2. `app/Services/SpotSignalEngineService.php`
- Menjalankan analisis kuantitatif matematis sesuai spesifikasi di [`ALGORITHM_SPECIFICATION.md`](file:///Users/adybudi/Project/project-tools/trading-dashboard/brain/engine_v1/ALGORITHM_SPECIFICATION.md).
- Menghitung skor konfluensi (0 - 100), menentukan Grade (`A+`, `A`, `B`, `C`), serta memproyeksikan target Take Profit 1 (+10%), Take Profit 2 (+20%), dan level Invalidation.

### 2.3. `app/Services/SignalNotificationService.php`
- Menyimpan sinyal valid (Grade A & A+) ke tabel `spot_signals`.
- Menyiarkan event WebSocket `SpotSignalDetected` ke channel trader.

### 2.4. `app/Http/Controllers/SpotSignalController.php`
- `index()`: Menampilkan daftar sinyal diskon aktif.
- `scan(Request $request)`: Memicu pemindaian langsung pada daftar pair spot pilihan.
- `convertToTrade($signalId)`: Mengonversi sinyal langsung menjadi entri jurnal trade hanya dengan 1 klik.

---

## 3. Database Schema for `spot_signals` Table

```php
Schema::create('spot_signals', function (Blueprint $table) {
    $table->id();
    $table->string('symbol', 30)->index();
    $table->string('timeframe', 10)->default('1h');
    $table->enum('grade', ['A+', 'A', 'A-', 'B+', 'B', 'C'])->default('A');
    $table->integer('score')->default(85);
    $table->decimal('entry_price', 18, 8);
    $table->decimal('take_profit_1', 18, 8); // +10% target
    $table->decimal('take_profit_2', 18, 8); // +20% target
    $table->decimal('stop_loss', 18, 8);
    $table->decimal('potential_gain_percent', 8, 2)->default(15.00);
    $table->text('confluence_summary')->nullable();
    $table->json('metrics_data')->nullable(); // detail dealing range, ifvg coordinates
    $table->enum('status', ['active', 'filled', 'expired', 'invalidated'])->default('active')->index();
    $table->timestamps();
});
```
