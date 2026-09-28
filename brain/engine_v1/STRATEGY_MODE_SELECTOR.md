# Strategy Mode Selector: iFVG vs PADAWAE (2026-08-29)

## Latar Belakang / Bug yang Ditemukan

Sebelum perubahan ini, `SpotSignalEngineService::analyze()` selalu
menggabungkan **iFVG** dan **PADAWAE** ke dalam satu skor dan satu narasi
("The Golden Confluence", lihat `STRATEGY_SYNTHESIS.md`). Pengguna
melaporkan (dengan screenshot modal SOLUSDT TF 1H): harga saat ini
**$104.05**, tapi sistem menampilkan "Area Tunggu Beli Diskon **$106.46**"
dan teks "Tunggu koreksi sekitar **--2.31%**" (tanda minus dobel) — padahal
$104.05 sudah **di bawah** Equilibrium $106.46, artinya harga sudah berada
di zona Discount, bukan Premium.

### Root cause (2 bug terpisah, satu akar penyebab)

Equilibrium = (Swing High + Swing Low) / 2 = $106.46 pada kasus ini.
$104.05 < $106.46 → `isInDiscount` sebenarnya **true**. Tapi cabang
"watchlist" (dipakai setiap kali `status !== 'triggered'`, yang terjadi
bila skor < 70 — **baik saat di Discount maupun di Premium**) menulis teks
narasi secara **hardcoded** tanpa mengecek `isInDiscount` sama sekali:

```php
// SEBELUM (bug):
$confluences = [
    "Harga saat ini masih berada di area Premium (> 50% Dealing Range TF {$timeframe})",
    "Disarankan WAIT & WATCH: Tunggu koreksi -{$distanceToWaitPct}% ke Area Diskon Ideal ({$formattedWait})",
    ...
];
```

Dua bug turunan:
1. **Label "Premium" selalu ditulis**, walau harga sudah di Discount
   (kasus: sudah di Discount tapi skor < 70 karena tidak ada
   sweep/iFVG/RVOL/HTF pendukung — status tetap `watchlist`, tapi bukan
   karena harga di Premium).
2. **Tanda minus dobel**: `distanceToWaitPct` dihitung
   `((currentPrice - waitPriceIdeal) / currentPrice) * 100` — bernilai
   **negatif** ketika harga *sudah di bawah* target (`waitPriceIdeal`).
   String literal `"-{$distanceToWaitPct}%"` lalu menghasilkan
   `"--2.31%"` alih-alih pesan yang masuk akal.

Bug yang sama persis ada **dua kali** — sekali di PHP
(`SpotSignalEngineService`) dan sekali lagi di-*hardcode ulang* di
`resources/views/signals/index.blade.php` (Card "Rekomendasi Titik
Tunggu" pada modal chart), independen dari backend. Ini kenapa bug tetap
muncul walau salah satu sisi diperbaiki — dua sumber kebenaran yang tidak
sinkron. Fix sekarang menghapus duplikasi ini: **narasi hanya dari
`confluences_list` (backend)**, ditampilkan lewat panel "Konfluensi
Teknikal"; panel "Status Rekomendasi" di modal cuma label status singkat.

## Kenapa PADAWAE dan iFVG Tidak Boleh Diblend

`STRATEGI_PADAWAE.md` (Elliot Wave trendline break/retest) **tidak
punya konsep Premium/Discount/Equilibrium/OTE Fibonacci sama sekali** —
istilah itu murni milik `IFVG_RATING_GUIDE.md` (framework ICT/SMC).
Ketika kedua metodologi ini diblend jadi satu skor tunggal, sinyal yang
sebenarnya valid secara PADAWAE (3x rejection + breakout, tanpa sweep/
iFVG apapun) tetap dibingkai lewat bahasa "area Diskon/Premium" iFVG —
yang secara konsep tidak relevan untuknya, dan sebaliknya. Inilah akar
dari laporan pengguna: "apa karena 2 strategi ini cukup berbeda?" — ya,
benar; blending-nya yang salah, bukan salah satu strateginya.

## Solusi: Dua Mode Terpisah (Bukan Blend)

`SpotSignalEngineService::analyze()` menerima parameter
`string $strategyMode = 'ifvg'` (`'ifvg'` atau `'padawae'`), lalu memanggil
salah satu dari dua builder yang sepenuhnya independen:

- `buildIfvgAnalysis()` — sweep + iFVG + Premium/Discount/OTE + RVOL + HTF
  (lihat `IFVG_RATING_GUIDE.md`). **Tidak ada kontribusi skor dari
  PADAWAE.**
- `buildPadawaeAnalysis()` — 3x Rejection/Breakout/Retest/Breakout2 murni
  + RVOL + HTF (lihat `STRATEGI_PADAWAE.md`). **Tidak ada konsep
  Premium/Discount/OTE/iFVG sama sekali** — "wait price" memakai level
  trendline yang ditembus, bukan Equilibrium.

Kedua mode tetap berbagi precompute yang generik (Swing High/Low, RVOL,
HTF trend, deteksi liquidity sweep mentah) tapi HANYA menggunakan bagian
yang relevan dengan metodologinya masing-masing untuk skor dan narasi.

### UI: Pilihan Terkunci per Sesi Browser

Sidebar Coin Hunter (`resources/views/signals/index.blade.php`) punya
toggle pill "Strategi: PADAWAE / IFVG" yang disimpan di
`localStorage['signals_strategy_mode']` — pola yang sama dengan toggle
mata uang USD/IDR di Money Flow & Simulasi Trade (persisten lintas
navigasi menu, tidak reset ke default saat pindah halaman). Parameter
`strategy` diteruskan ke semua endpoint terkait: `/signals` (index),
`/signals/live-signals`, `/signals/scan`, `/signals/chart-data/{symbol}`.
Cache key live-stream-signals (`live_stream_signals_{tf}_{min}_{max}_{strategy}`)
disegmentasi per mode supaya tidak saling menimpa.

## Bug Kedua: Entry/Wait Price Bisa Lebih Tinggi dari Harga Real-Time (2026-08-29)

Ditemukan lewat audit lanjutan setelah bug pertama diperbaiki. Laporan
pengguna: harga saat ini **$0.73**, tapi titik entry yang disarankan
**$0.9 atau $1.6** — lebih tinggi dari harga yang tersedia sekarang.
Untuk strategi **SPOT (long-only)**, ini tidak masuk akal: kalau harga
sudah bisa dibeli di $0.73, tidak boleh disuruh menunggu harga naik dulu
ke $0.9/$1.6 baru "layak" dibeli.

**Root cause**: `waitPriceIdeal` (iFVG: `= $equilibrium`) dan `waitPrice`
(PADAWAE: `= $trendlineLevel`) dihitung murni dari data historis (Swing
High/Low 35-candle lookback, level trendline yang ditembus) — **tanpa
pernah dibandingkan dengan harga saat ini**. Pada tren turun yang kuat,
harga bisa jatuh jauh melewati Equilibrium/OTE/trendline tersebut, tapi
formula tetap menyodorkan level lama yang sekarang justru lebih tinggi
dari harga pasar.

**Fix**: kedua path sekarang mengunci target dengan
`min($targetLevel, $currentPrice)` — kalau harga sudah lebih rendah dari
target hitungan (diskon lebih dalam dari yang diperkirakan), target =
harga saat ini, bukan angka historis yang sudah terlewati. Stop Loss juga
diberi pengaman tambahan (`if ($stopLoss >= $entryPrice) { ... }`) supaya
tidak pernah berada di atas entry akibat efek samping clamp ini.

Diverifikasi dengan 960 kombinasi data real (30 koin live × 4 timeframe ×
2 strategi × 4 target profit): 0 pelanggaran invarian
`entry_price <= current_price` saat status watchlist, `stop_loss < entry_price`,
dan urutan TP1 < TP2. Regression test:
`tests/Unit/SpotSignalEngineTest.php::test_entry_price_never_exceeds_current_price_on_deep_downtrend_overshoot`.

### Yang TIDAK Diubah

- Format target profit (Scalp/Standard/Extended/Big Runner/Custom),
  timeframe, dan struktur TP1/TP2/SL tetap sama di kedua mode.
- `detectPadawaePattern()` (algoritma deteksi pola 5 langkah) tidak
  diubah — hanya ditambah field `trendline_level` pada return value
  supaya `buildPadawaeAnalysis()` bisa memakainya sebagai wait-price.
- Mode default tetap `'ifvg'` untuk backward-compatibility (skor/threshold
  A+/A/B ≥90/≥70 sudah divalidasi test suite sebelumnya).
