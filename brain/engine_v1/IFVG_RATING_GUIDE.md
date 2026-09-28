# Penilaian Setup iFVG (Inversion Fair Value Gap)

> Sumber: `IFVG RATING GUIDE.pdf` (Jack Trades, 2025).
> Diringkas apa adanya untuk jadi rujukan implementasi
> `SpotSignalEngineService::buildIfvgAnalysis()`.

## 5 Kriteria Penilaian (dipakai di setiap tingkat grade)

1. **Sweep Likuiditas dan/atau Deliveri dari FVG** — apakah setup didahului
   liquidity sweep, pergerakan dari FVG (timeframe) lain, atau keduanya.
2. **Momentum** — bersih tanpa "chop" (A+/A) vs momentum dengan banyak
   chop atau nyaris tidak ada momentum (B-/C).
3. **Target Jelas** — salah satu dari 7: EQH, EQL, ITH, ITL, Data Highs,
   Data Lows, atau likuiditas trendline (LRLR).
4. **FVG Harus Tunggal** — kalau tidak tunggal, cek timeframe lain apakah
   kombinasinya membentuk satu FVG valid.
5. **Premium/Discount** — **long biasanya di area Discount, short di area
   Premium** dari dealing range (bonus tambahan kalau ada SMT).

## Tingkatan Grade

| Grade | Sweep/Delivery | Momentum | Premium/Discount |
|---|---|---|---|
| **A+** | Sweep **DAN** delivery dari FVG lain (keduanya) | Kuat, tanpa chop | Long selalu di Discount, short di Premium (bonus SMT) |
| **A** | Sweep **ATAU** delivery (salah satu) | Jelas saat break, tidak choppy | FVG asal harus jelas di Discount (long) / Premium (short) |
| **A-** (dan B+ di hari sangat choppy) | Salah satu, tapi tidak setegas A | Ada momentum tapi sedikit choppy; entry hanya jika candle body close penuh di atas/bawah FVG | Long mungkin di Premium, short di Discount (kebalikan A+/A — sinyal kualitas menurun) |
| **B** | Tidak ada sweep atau delivery dari area penting, tapi mungkin searah bias market | Ada momentum tapi banyak chop | Long muncul di Premium, short di Discount |
| **B-** | Tidak ada sweep besar/pergerakan jelas dari FVG manapun | Momentum rendah/nyaris tidak ada | Tidak ada target jelas, R:R jelek |
| **C** | Tidak ada sama sekali — entry di tengah market tanpa arah | Momentum tidak stabil, sulit diprediksi | Entry di harga sudah terlalu tinggi/rendah — arah salah |

Catatan penting: mulai dari grade **A-** ke bawah, arah Premium/Discount
untuk long/short justru **terbalik** dari kaidah ideal (long di Premium,
bukan Discount) — ini pertanda kualitas setup menurun, bukan aturan baru
yang valid untuk diikuti. Engine ini hanya memberi bobot positif untuk
kondisi "long di Discount" (kaidah A+/A/B yang benar), sesuai desain asli.

## Pemetaan ke Implementasi Kode

`SpotSignalEngineService::buildIfvgAnalysis()` (mode strategi `ifvg`,
default) mengimplementasikan versi long-only dari kriteria di atas:

- **Premium/Discount** — `isInDiscount = currentPrice <= equilibrium`
  (Equilibrium = 50% dari Swing High–Swing Low dealing range, lookback 35
  candle). `isInDeepDiscount` = di dalam OTE golden pocket 61.8%–78.6%
  retracement. Discount biasa: +15 skor; Deep Discount: +25 skor; di luar
  Discount (Premium): −20 skor.
- **Liquidity Sweep** — deteksi SSL grab pada 8 candle terakhir
  (`low < priorMinLow && close > priorMinLow`): +20 skor jika terdeteksi.
- **iFVG valid** — HANYA dihitung jika didahului liquidity sweep
  (`ifvgValid = ifvgDetected && sweepDetected`) — inversi FVG tanpa sweep
  cuma gap flip biasa, bukan pola "smart money reversal" yang dinilai
  panduan ini: +20 skor.
- **Target Jelas** — didekati dengan `potentialUpside >= targetMinPct`
  (ruang ke swing high/resisten terdekat masih cukup untuk target
  profit yang dipilih user): +10 skor. (Engine belum membedakan EQH vs
  EQL vs ITH/ITL/Data Highs/Data Lows/LRLR secara eksplisit — future
  work jika dibutuhkan presisi lebih tinggi.)
- **Momentum** didekati dengan RVOL (Relative Volume) ≥ 1.25 (+10) dan
  tren HTF (Higher Timeframe) — bullish HTF +10, bearish HTF −15 (falling
  knife warning).
- **Status Triggered** = `isInDiscount && score >= 70`. Watchlist = sisanya
  — dan narasinya sekarang membedakan dua kasus watchlist yang berbeda
  (lihat `STRATEGY_MODE_SELECTOR.md`):
  1. Harga masih di Premium → tunggu koreksi turun ke Equilibrium.
  2. Harga sudah di Discount tapi skor < 70 → tunggu konfirmasi tambahan
     (sweep/iFVG/RVOL/HTF), bukan tunggu "koreksi" (karena sudah di sana).
