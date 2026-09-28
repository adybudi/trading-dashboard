# Plan Architecture: Mode Futures/Leverage untuk Backtest & Validasi Massal

**Author**: AlphaJournal System Architect
**Status**: PROPOSED PLAN (Belum Dikerjakan — Ditahan/Keep Dahulu)
**Location**: `plan_architecture/PLAN_FUTURES_MODE_BACKTEST_VALIDASI_MASSAL.md`

---

## 1. Latar Belakang & Tujuan

Saat ini **Backtest** (single-coin) dan **Validasi Massal (Layer 10 QA)** murni menguji skenario **Spot / Long-only** — persis strategi dasar Coin Hunter ("beli di zona diskon"). Fitur **leverage & short** sudah ada, tapi HANYA di menu **Paper Trading manual** (`PaperTradingService`) — artinya kalau user memilih pakai leverage 10x saat trading manual, performa keputusan itu **tidak pernah divalidasi lewat Backtest**.

Tujuan plan ini: menambahkan pilihan **Mode Trading (Spot / Futures)** di form Backtest dan Validasi Massal, supaya user bisa menguji "kalau sinyal yang sama saya eksekusi pakai leverage Nx, bagaimana hasil historisnya" — sebelum benar-benar memakai leverage di Paper Trading sungguhan.

---

## 2. Kondisi Saat Ini (Verified dari Kode)

```
grep "trading_type|leverage|side|'short'|'futures'" app/Services/BacktestEngineService.php
→ 0 hasil ditemukan
```

- `BacktestEngineService::simulate()` murni menghitung `grossPnlPercent = (exitPrice - entryPrice) / entryPrice * 100` — tidak ada logika leverage/short/liquidation/funding sama sekali.
- Leverage, side (long/short), liquidation price, dan funding rate **hanya** diimplementasikan di `PaperTradingService` (manual trading).
- Tab "Filter Mode" (Spot/Futures) di halaman Paper Trading **cuma filter tampilan** daftar posisi yang sudah dibuka — tidak terhubung ke mesin backtest sama sekali.

---

## 3. Batasan Penting yang Harus Dipahami Sebelum Implementasi

> ⚠️ **Strategi Coin Hunter (`SpotSignalEngineService`) HANYA menghasilkan sinyal LONG** (beli di zona diskon). Belum ada algoritma deteksi setup SHORT (jual di zona premium/breakdown).
>
> Konsekuensinya: "Mode Futures" di backtest **BUKAN berarti menguji short-selling sungguhan** — artinya menguji **sinyal LONG yang sama, tapi diberi pengali leverage**. Kalau nanti ingin backtest short betulan, perlu dikembangkan dulu algoritma deteksi setup short di `SpotSignalEngineService` — itu di luar scope plan ini.

---

## 4. Desain Fitur yang Diusulkan

### A. UI — Toggle Mode Trading

Tambahkan selector **Spot vs Futures** (mengikuti pola visual yang sudah ada di modal "Simulasi" Coin Hunter) di dua tempat:
1. Form **Backtest** (Tab "Historical Backtester", single coin)
2. Modal **"Simulasikan dengan Saldo Real"** (Validasi Massal)

Saat **Futures** dipilih, muncul selector **Leverage** (2x/5x/10x/20x/50x/100x, sama seperti Paper Trading).

### B. Perhitungan yang Perlu Ditambahkan ke `BacktestEngineService::simulate()`

| Komponen | Formula/Logika |
|---|---|
| PnL dengan leverage | `pnlPercent = rawPriceChangePct * leverage` (sama seperti `PaperTradingService`) |
| Liquidation Price | `entry * (1 - 1/leverage + MMR)` untuk long, MMR 0.5% (sama formula dgn Paper Trading) |
| Cek Liquidation di forward-scan | Prioritas **sebelum** cek SL biasa — kalau leverage tinggi, liquidation price bisa lebih dekat ke entry dibanding SL yang direncanakan, jadi harus dicek duluan |
| Funding Rate | Dihitung dari **durasi riil** antara `entry_time` dan `exit_time` (di backtest ini bisa presisi karena tahu waktu exit pastinya, beda dengan Paper Trading yang cuma cek tiap poll) — pakai rate approksimasi yang sama (~0.01%/8 jam) |
| Margin vs Notional | `allocation_pct` merepresentasikan **margin**, bukan notional penuh — notional = margin × leverage (sama seperti Paper Trading) |

### C. Statistik Tambahan di Hasil

- `liquidated_trades` — hitung terpisah dari `losing_trades` biasa (kena SL vs kena liquidation itu beda parah)
- `avg_funding_cost` — total funding yang termakan selama backtest, supaya user tahu berapa % return tergerus cuma dari funding

---

## 5. Perubahan Teknis (Checklist Implementasi)

1. [ ] `BacktestEngineService::simulate()` — tambah parameter `$tradingType`, `$leverage`; implementasi liquidation check + funding cost
2. [ ] `runBacktest()` & `runBatchBacktest()` — tambah parameter `tradingType`, `leverage`, teruskan ke `simulate()`
3. [ ] `SimulationController::runBacktest()` & `runBatchBacktest()` — validasi input `trading_type` (`spot`|`futures`) dan `leverage` (1-100)
4. [ ] UI: toggle Mode Trading + Leverage picker di form Backtest (single) dan modal Validasi Massal
5. [ ] UI: tampilkan `liquidated_trades` dan `avg_funding_cost` di hasil (KPI card tambahan)
6. [ ] Testing end-to-end dengan beberapa level leverage (2x, 10x, 50x) untuk pastikan liquidation logic benar
7. [ ] Update `penggunaan/PANDUAN_PENGGUNAAN.md` dengan cara pakai fitur ini

---

## 6. Peringatan yang Perlu Ditampilkan ke User (UX)

Karena leverage tinggi + likuidasi historis bisa menghasilkan hasil jauh lebih buruk (atau lebih fantastis) dibanding spot murni, sebaiknya UI menampilkan disclaimer eksplisit saat mode Futures dipilih — supaya user tidak salah kalibrasi ekspektasi hanya dari satu angka Profit Factor yang terlihat tinggi karena leverage, padahal risiko likuidasinya juga jauh lebih tinggi.

---

## 7. Status

**BELUM DIKERJAKAN** — plan ini disimpan untuk referensi implementasi di masa depan. Tidak ada perubahan kode yang dibuat terkait plan ini.
