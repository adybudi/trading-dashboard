# 🚀 PRD & RENCANA ARSITEKTUR: SMART SCREEN FUTURE (2-WAY iFVG SCANNER)

> **Nama Modul:** SMART SCREEN FUTURE (`/futures-signals`)  
> **Tipe Pasar:** Futures Perdagangan 2 Arah (LONG & SHORT Only)  
> **Metodologi Strategi:** Inversion Fair Value Gap (iFVG) Murni + Money Flow Whale Confluence (PADAWAE dieksklusikan)  
> **Standar UI:** Zero Unicode Emojis, `<x-app-icon>` Standard, Kurs 1 USD = Rp 17.756,00, Real-Time Dynamic Updates

---

## 📌 Ringkasan Eksekutif

**SMART SCREEN FUTURE** adalah pemindai pasar berjangka kripto (*Futures Scanner*) real-time yang memindai **250 koin spot/perpetual** teratas untuk mendeteksi peluang entri presisi tinggi **2 arah (LONG & SHORT)**.

Berbeda dengan **Coin Hunter** yang dikhususkan untuk pasar **Spot** (hanya akumulasi beli diskon), **SMART SCREEN FUTURE** memanfaatkan strategi **Inversion Fair Value Gap (iFVG)** murni yang dipadukan dengan **Liquidity Sweep (SSL/BSL)** dan konfluensi **Money Flow Arus Modal Paus** (*Whale Net Inflow/Outflow*).

---

## 🎯 6 Spesifikasi Utama Produk

| # | Fitur | Deskripsi Teknis |
|---|---|---|
| **1** | **Screening 250 Koin Real-Time** | Memindai 250 koin likuid teratas dari feed Gate.io secara *in-memory* dalam hitungan detik. |
| **2** | **Tampilan Fleksibel (Card Grid vs Table List)** | Menyediakan toggle cepat antara **Card Grid View** (kartu visual interaktif) dan **Table List View** (tabel data kompak trader). |
| **3** | **Kontrol Filter Lengkap** | • **Target Profit %:** Scalp (+5-10%), Standard (+10-20%), Extended (+20-35%), Moonshot (+35-50%).<br>• **Timeframe:** 15m, 1h, 4h, 1d.<br>• **Arah:** Semua Arah, LONG Only, SHORT Only.<br>• **Mata Uang:** USD ($) dan IDR (Rp) kurs 1 USD = Rp 17.756,00 (tersimpan *persistent*). |
| **4** | **Integrasi Simulasi Trade Virtual** | Tombol *"Simulasi Futures"* pada setiap kartu/baris yang membuka modal simulasi dengan leverage (1x - 100x), margin terisolasi, dan arah LONG/SHORT terhubung ke `SimulationController`. |
| **5** | **Label Kualitas Setup (Setup B, A, A+)** | Klasifikasi sinyal berdasarkan skor konfluensi matematis: **Setup B** (70%+), **Setup A** (80%+), dan **Setup A+** (90%+ Golden Master Setup). |
| **6** | **Kartu Detail Sinyal 2 Arah (LONG/SHORT) + Chart Modal** | Struktur kartu identik dengan Coin Hunter ditambah badge penanda arah **LONG (Bullish)** atau **SHORT (Bearish)**, rasio Risk-Reward (RRR), indikator Money Flow paus, dan modal grafik TradingView Lightweight Charts interaktif. |

---

## 📐 Arsitektur Sistem & Aliran Data (Data Flow)

```mermaid
graph TD
    subgraph Data Feeds
        G_TICKER[Gate.io 250 Top Tickers Live Feed]
        G_CANDLES[Gate.io Multi-Timeframe OHLCV Candles]
        MF_SERVICE[MoneyFlowWhaleTrackerService: 24h/4h Net Flow]
    end

    subgraph Futures Engine Logic
        F_ENGINE[FuturesSignalEngineService<br/>- 2-Way iFVG Detector<br/>- SSL / BSL Liquidity Sweep<br/>- Fibonacci Dealing Range Discount/Premium<br/>- Money Flow Weighting<br/>- Tier Classifier Setup B / A / A+]
    end

    subgraph Controller & Routing
        F_CTRL[FuturesSignalController<br/>- GET /futures-signals (Index)<br/>- GET /futures-signals/live-signals (Polling)<br/>- GET /futures-signals/chart-data/{symbol}<br/>- POST /futures-signals/instant-journal]
    end

    subgraph User Interface
        V_SIDEBAR[Sidebar Navigation: 'Smart Screen Future']
        V_PAGE[resources/views/futures-signals/index.blade.php<br/>- Header Metric Banner<br/>- Filter Bar: TP Profile, TF, Side, USD/IDR, View Mode<br/>- Signal Card Grid / Table List<br/>- Chart & Confluence Detail Modal<br/>- Futures Simulation Modal]
    end

    G_TICKER --> F_ENGINE
    G_CANDLES --> F_ENGINE
    MF_SERVICE --> F_ENGINE
    F_ENGINE --> F_CTRL
    F_CTRL --> V_PAGE
    V_SIDEBAR --> V_PAGE
```

---

## 🖥️ Rincian Desain Antarmuka (UI Components)

### 1. Struktur Kartu Sinyal (Futures Signal Card)
```
+-----------------------------------------------------------------------------------+
| [SOL] SOLUSDT • TF 1H          [SETUP A+]           [🟢 LONG / BULLISH]           |
| Harga Live: Rp 2.450.000 ($138.00)                 Konfluensi: 92% (5/5 Match)    |
+-----------------------------------------------------------------------------------+
| • iFVG Zone: Rp 2.435.000 - Rp 2.448.000 (Bullish Support Flip)                   |
| • Liquidity: SSL Sweep Low terdeteksi di Rp 2.410.000                             |
| • Money Flow: +Rp 1.775,6 Miliar (+$100.0M Net Inflow Paus 24H)                   |
| • Target TP1: Rp 2.695.000 (+10.0%) | TP2: Rp 2.940.000 (+20.0%)                  |
| • Stop Loss: Rp 2.395.000 (-2.2%)   | Risk-to-Reward: 1 : 4.5                     |
+-----------------------------------------------------------------------------------+
| [ Buka Chart & Detail ]                         [ ⚡ SIMULASI FUTURES ]           |
+-----------------------------------------------------------------------------------+
```

---

## 🗂️ Rencana Berkas & Struktur File

```
app/
├── Http/
│   └── Controllers/
│       └── FuturesSignalController.php               [NEW]
├── Services/
│   └── FuturesSignalEngineService.php                [NEW]
routes/
└── web.php                                           [MODIFY] (Daftarkan route /futures-signals)
resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php                             [MODIFY] (Tambahkan link navigasi 'Smart Screen Future' di sidebar)
│   └── futures-signals/
│       └── index.blade.php                           [NEW]
tests/
├── Unit/
│   └── FuturesSignalEngineTest.php                   [NEW]
└── Feature/
    └── FuturesSignalTest.php                         [NEW]
```

---

## ⏱️ Rencana Tahapan Eksekusi

1. **Fase 1: Engine Matematika & Service (`FuturesSignalEngineService.php`)**
   - Mengimplementasikan deteksi iFVG 2 arah (Bullish Inversion untuk LONG & Bearish Inversion untuk SHORT).
   - Mengintegrasikan deteksi SSL Sweep & BSL Sweep.
   - Mengintegrasikan konfluensi data arus modal dari `MoneyFlowWhaleTrackerService`.
   - Mengimplementasikan kalkulasi skor 0–100 dan klasifikasi `Setup B`, `Setup A`, dan `Setup A+`.
2. **Fase 2: Controller & Endpoints (`FuturesSignalController.php` & `routes/web.php`)**
   - Membuat `index()`, `liveSignals()` (JSON polling 5 detik), `chartData($symbol)`, dan integrasi konversi ke jurnal/simulasi.
3. **Fase 3: Antarmuka Pengguna (`futures-signals/index.blade.php`)**
   - Membangun halaman antarmuka lengkap dengan mode Card dan List/Table.
   - Filter Target Profit, Timeframe, Direction (LONG/SHORT/ALL), dan toggle USD/IDR (Rp).
   - Modal split chart TradingView Lightweight Charts + detail konfluensi iFVG dan Money Flow.
   - Modal simulasi trading futures interaktif (pilihan leverage, margin, dan arah).
4. **Fase 4: Navigasi & Pengujian Menyeluruh**
   - Menambahkan menu **Smart Screen Future** pada sidebar navigasi [`resources/views/layouts/app.blade.php`](file:///Users/adybudi/Project/project-tools/trading-dashboard/resources/views/layouts/app.blade.php).
   - Menulis Unit & Feature tests (`FuturesSignalEngineTest.php` & `FuturesSignalTest.php`).
   - Menjalankan `npm run build` dan `php artisan test` hingga lulus 100%.
