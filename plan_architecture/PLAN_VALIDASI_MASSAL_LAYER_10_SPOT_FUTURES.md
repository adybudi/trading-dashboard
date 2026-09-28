# 🏗️ PLAN ARSITEKTUR: VALIDASI MASSAL (LAYER 10 QA) — SPOT (COIN HUNTER) VS FUTURES (SMART SCREEN FUTURE)

**Dokumen**: Plan Arsitektur & Spesifikasi Fitur Validasi Massal  
**Lokasi Berkas**: `/Users/adybudi/Project/project-tools/trading-dashboard/plan_architecture/PLAN_VALIDASI_MASSAL_LAYER_10_SPOT_FUTURES.md`  
**Status**: DESIGN APPROVED & READY FOR IMPLEMENTATION  
**Modul Utama**: Simulasi Trade (`/simulation`) — Tab Validasi Massal (Layer 10 QA)  
**Tujuan**: Menyediakan pemilih mode pengujian massal (Spot vs Futures) beserta pilihan strategi (IFVG vs PADAWAE) yang terelasi presisi dengan mesin sinyal **Coin Hunter** dan **Smart Screen Future**, tanpa mengubah/mengganggu data transaksi Paper Trading yang sedang berjalan.

---

## 📌 1. LATAR BELAKANG & TUJUAN SISWARA

Pada sistem saat ini, **Validasi Massal (Layer 10 QA)** digunakan untuk menguji keandalan strategi (*walk-forward historical simulation*) secara serentak pada 50 hingga 250 koin terlikuid. 

Namun, pengujian massal sebelumnya masih dominan berbasis **Spot Long-Only** (Coin Hunter). Dengan hadirnya modul **SMART SCREEN FUTURE (2-Way iFVG Scanner)**, diperlukan pembaruan arsitektur pada tab Validasi Massal agar trader dapat:

1. **Menguji Skenario Spot Only (Coin Hunter)**:
   - Menggunakan sinyal beli akumulasi diskon dari **Coin Hunter**.
   - Pilihan Strategi: **IFVG (Inversion FVG + Liquidity Sweep)** ATAU **PADAWAE (Trendline Break/Retest)**.
2. **Menguji Skenario Futures 2-Way (Smart Screen Future)**:
   - Menggunakan sinyal 2-arah (LONG & SHORT) dari **Smart Screen Future**.
   - Pilihan Strategi: **IFVG Only** (PADAWAE dieksklusikan dari Futures karena merupakan pola trendline 1-arah khas Elliot Wave spot).
   - Pilihan Filter Arah: **Semua Arah (LONG & SHORT)**, **LONG Only**, atau **SHORT Only**.
   - Pengaturan **Leverage** (1x hingga 100x), margin terisolasi, perhitungan harga likuidasi, dan estimasi biaya funding.
3. **Korelasi & Akurasi Presisi**:
   - Menjamin bahwa indikator matematis, ambang batas skor (Grade A+ >= 90%, Grade A >= 70%), RVOL, HTF Trend, dan Money Flow Paus antara Coin Hunter dan Smart Screen Future berjalan konsisten dan dapat dibandingkan (*apple-to-apple*).

---

## 🛡️ 2. BATASAN KEAMANAN MUTLAK (SAFETY CONSTRAINT)

> ⚠️ **PERINGATAN INTEGRITAS DATA**:  
> Seluruh proses pengujian pada **Validasi Massal (Layer 10 QA)** dilakukan 100% secara **IN-MEMORY & STATELESS (PURE BACKTEST SIMULATION)**.  
> **FITUR INI TIDAK BOLEH MENYENTUH, MENGEDIT, MENAMBAH, MENGHAPUS, ATAU MENG-OVERWRITE DATA PADA TABEL `simulated_trades` DAN `simulation_accounts`.**

### Status Posisi Paper Trading yang Wajib Dijaga:
- **Posisi Berjalan (Running)**: 2 Posisi aktif tetap berjalan normal tanpa terganggu.
- **Posisi Riwayat (History)**: 1 Posisi tertutup tetap tersimpan presisi di database.
- Hasil dari Validasi Massal dikirimkan murni sebagai payload JSON ephemeral untuk di-render oleh Alpine.js di browser.

---

## 📐 3. KORELASI STRATEGI & PETA MATEMATIS (ACCURACY ALIGNMENT)

Agar hasil pengujian massal antara **Coin Hunter (Spot)** dan **Smart Screen Future (Futures)** terelasi akurat, sistem menerapkan korelasi parametrik sebagai berikut:

| Parameter | Mode Spot (Coin Hunter) | Mode Futures (Smart Screen Future) | Catatan Korelasi & Akurasi |
|---|---|---|---|
| **Pilihan Strategi** | **IFVG** atau **PADAWAE** | **IFVG Only** | PADAWAE murni strategi *trendline breakout* 1-arah tanpa konsep Premium/Discount. |
| **Pilihan Arah** | Locked: **LONG Only** | **All (Auto)** / **LONG Only** / **SHORT Only** | Mode Futures menguji kemampuan entri *SHORT* saat tren HTF sedang Bearish. |
| **Dealing Range** | Entry <= Equilibrium (Discount) | LONG: Discount (Entry <= Equilibrium)<br>SHORT: Premium (Entry >= Equilibrium) | Formula Fibonacci 61.8% - 78.6% Retracement identik di kedua engine. |
| **Invariant Entry Price** | waitPriceIdeal = min(Equilibrium, Current) | LONG: min(Equilibrium, Current)<br>SHORT: max(Equilibrium, Current) | Mencegah bug target entri disarankan di atas/bawah harga pasar saat ini. |
| **Liquidity Sweep** | SSL Grab (Sell-Side Liquidity) | LONG: SSL Grab<br>SHORT: BSL Grab (Buy-Side Liquidity) | Simetri 2-arah presisi untuk deteksi *fakeout*. |
| **Ambang Batas Grade** | Grade A+ (>=90%), Grade A (>=70%) | Grade A+ (>=90%), Grade A (>=70%) | Skala penilaan skor 0–100 diharmoniskan 100% sama. |
| **Money Flow Confluence** | +-15% flow_pct_24h (+-10 Skor) | +-15% flow_pct_24h (+-10 Skor) | Inflow = Bullish LONG / Bearish SHORT. Outflow = Bearish LONG / Bullish SHORT. |

---

## 🖥️ 4. DESAIN ANTARMUKA PENGGUNA (UI SPECIFICATION)

Pada tab **Validasi Massal (Layer 10 QA)** di `resources/views/simulation/index.blade.php`, akan ditambahkan kontrol dinamis:

```
+-----------------------------------------------------------------------------------+
| 🚀 VALIDASI MASSAL STRATEGI (LAYER 10 QA - MULTI-COIN TESTING)                   |
| Uji keandalan strategi pada 50 - 250 koin terlikuid secara serentak secara in-memory |
+-----------------------------------------------------------------------------------+
| 1. PILIH PASAR & PASANGAN SINYAL:                                                |
|    [ 🟢 Spot Only (Coin Hunter) ]    [ ⚡ Futures 2-Way (Smart Screen Future) ]  |
|                                                                                   |
| 2. PILIH STRATEGI TERHUBUNG:                                                     |
|    • Jika Spot Selected    :  (o) IFVG (Discount+iFVG)   ( ) PADAWAE (Trendline)|
|    • Jika Futures Selected :  (x) IFVG Only (2-Way LONG/SHORT) [LOCKED]           |
|                                                                                   |
| 3. PILIH ARAH TRADING (Khusus Futures):                                          |
|    [ 🔄 Semua Arah (Auto) ]   [ 🟢 LONG Only ]   [ 🔴 SHORT Only ]                |
|                                                                                   |
| 4. PENGATURAN LEVERAGE (Khusus Futures):                                         |
|    Leverage: [ 1x | 2x | 5x | 10x (Default) | 20x | 50x | 100x ]                    |
|                                                                                   |
| 5. PARAMETER UJI:                                                                |
|    • Jumlah Koin  : [ 50 Koin  v ] (50 / 100 / 250 Koin)                          |
|    • Timeframe    : [ 1H (1 Jam) v ] (15M / 1H / 4H / 1D)                         |
|    • Target Profit: [ Standard (+10% s.d. +20%) v ]                              |
|    • Modal Virtual: [ $10.000,00 ]                                               |
+-----------------------------------------------------------------------------------+
|               [ ⚡ JALANKAN VALIDASI MASSAL (LAYER 10 TESTING) ]                 |
+-----------------------------------------------------------------------------------+
```

---

## ⚙️ 5. ALIRAN KODE & INTEGRASI BACKEND (DATA FLOW)

```mermaid
graph TD
    subgraph Frontend Request
        V_PAGE[Tab Validasi Massal Layer 10 QA]
        V_PAGE -->|POST /simulation/batch-backtest| CTRL[SimulationController::runBatchBacktest]
    end

    subgraph Controller Parameter Extraction
        CTRL --> P_MARKET[market_type: spot | futures]
        CTRL --> P_STRAT[strategy_mode: ifvg | padawae]
        CTRL --> P_DIR[direction: all | long | short]
        CTRL --> P_LEV[leverage: 1 - 100]
    end

    subgraph Backtest Engine Logic
        P_MARKET -->|If spot| ENG_SPOT[SpotSignalEngineService]
        P_MARKET -->|If futures| ENG_FUT[FuturesSignalEngineService]
        ENG_SPOT --> SIM[BacktestEngineService::runBatchBacktest]
        ENG_FUT --> SIM
        SIM -->|Pure In-Memory Simulation| RES[JSON Ephemeral Payload]
    end

    subgraph UI Render
        RES -->|Update Stat Cards & Trade Log| V_PAGE
    end

    subgraph Database Security
        DB[(simulated_trades)]
        SIM -.-x|NO WRITE / READ ONLY| DB
    end
```

---

## 🔧 6. RINCIAN MODIFIKASI KODE

### A. Modifikasi `SimulationController.php`
Menambahkan validasi request dan meneruskan parameter `market_type`, `direction`, dan `leverage` ke service:

```php
$validated = $request->validate([
    'market_type' => ['nullable', 'string', 'in:spot,futures'],
    'strategy' => ['nullable', 'string', 'in:ifvg,padawae'],
    'direction' => ['nullable', 'string', 'in:all,long,short'],
    'leverage' => ['nullable', 'integer', 'min:1', 'max:100'],
    'coin_limit' => ['nullable', 'integer', 'min:5', 'max:250'],
    'timeframe' => ['nullable', 'string', 'in:15m,1h,4h,1d'],
    'target_profile' => ['nullable', 'string', 'in:scalp,standard,extended,moonshot,custom'],
    'initial_capital' => ['nullable', 'numeric', 'min:10'],
]);
```

### B. Modifikasi `BacktestEngineService.php`
Memperluas fungsi `runBatchBacktest()` agar dapat memanggil `FuturesSignalEngineService` ketika `$marketType === 'futures'`:

```php
public function runBatchBacktest(
    array $symbols,
    string $timeframe = '1h',
    float $targetMinPct = 10.0,
    float $targetMaxPct = 20.0,
    float $initialCapital = 10000.00,
    float $allocationPct = 25.0,
    int $candleCount = 250,
    float $feePct = 0.1,
    float $slippagePct = 0.05,
    string $strategyMode = 'ifvg',
    string $marketType = 'spot',
    string $direction = 'all',
    int $leverage = 1
): array
```

- Pada loop simulasi, jika `$marketType === 'futures'`, gunakan pemicu analisis 2-arah dari `FuturesSignalEngineService::analyze()`.
- Lakukan kalkulasi PnL ber-leverage: `pnlPercent = rawPriceChangePct * leverage`.
- Terapkan pemeriksaan harga likuidasi terisolasi sebelum hit Stop Loss.

---

## 🧪 7. RENCANA PENGUJIAN & VALIDASI (QA CHECKLIST)

1. [ ] **Uji Isolasi Database**:
   - Menjalankan `runBatchBacktest` untuk Spot dan Futures.
   - Memastikan jumlah baris di tabel `simulated_trades` (2 running, 1 closed) **tidak berubah sama sekali**.
2. [ ] **Uji Korelasi Strategi**:
   - Memastikan pengujian **Spot IFVG** pada koin X menghasilkan jumlah sinyal dan level TP/SL yang identik dengan kartu sinyal **Coin Hunter**.
   - Memastikan pengujian **Futures IFVG (LONG & SHORT)** menghasilkan level TP/SL dan harga likuidasi yang identik dengan **Smart Screen Future**.
3. [ ] **Uji Filter UI**:
   - Saat "Futures 2-Way" dipilih, radio button strategi otomatis berpindah ke `IFVG Only` dan mengunci pilihan PADAWAE.
   - Selector leverage muncul secara dinamis saat mode Futures aktif.
4. [ ] **Uji Performa Latensi**:
   - Memastikan eksekusi 50 koin ber- time TTL cache paralel selesai dalam waktu **< 3 detik**.

---

## 📄 8. KESIMPULAN

Rencana arsitektur ini memberikan fleksibilitas penuh bagi trader untuk menguji strategi **Spot (Coin Hunter)** maupun **Futures (Smart Screen Future)** secara massal dengan akurasi matematis 100% konsisten, sekaligus **menjamin keamanan penuh** pada data Paper Trading yang sedang aktif berjalan.
