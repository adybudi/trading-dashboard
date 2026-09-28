# Money Flow Confluence (2026-08-29)

## Latar Belakang

Setelah Coin Hunter dipecah jadi 2 mode strategi terpisah
(`STRATEGY_MODE_SELECTOR.md`), dibahas apakah ada confluence tambahan yang
bisa menaikkan probabilitas entry **tanpa** mencampur kembali kedua
metodologi. Money Flow & Whale Tracker (menu terpisah, `brain` belum
punya dokumen untuknya sampai sekarang) sudah menghitung **arus dana
institusional riil** dari data Gate.io — ini genap sifatnya
methodology-agnostic (bukan konsep milik iFVG maupun PADAWAE), sehingga
aman diterapkan ke **kedua strategi secara identik**.

## Sumber Data

`MoneyFlowWhaleTrackerService` (sudah ada, dipakai menu `/money-flow`):
net flow dihitung dari 24 candle 1H terakhir, tiap candle diberi tanda
+/- berdasarkan arah close-vs-open (hijau = net buy, merah = net sell),
dijumlahkan lalu dinormalisasi jadi persentase dari total volume 24H
(`flow_pct_24h`). Ini proxy transparan untuk taker flow institusional —
bukan data tick-level asli (yang butuh feed berbayar), tapi tetap data
Gate.io real, bukan simulasi.

Coin Hunter memakai 2 method:
- `getNetFlowMatrix($limit)` — batch untuk scan 30 koin sekaligus
  (dipakai `getLiveStreamSignals()`), berbagi cache dengan menu Money Flow.
- `getNetFlowForSymbol($symbol)` — lookup 1 koin saja (dipakai `scan()`
  single-symbol dan `chartData()`), supaya tidak perlu fetch seluruh
  matrix cuma untuk 1 angka.

## Aturan Skoring (Identik di Kedua Mode)

`SpotSignalEngineService::applyMoneyFlowConfluence()`:

| `flow_pct_24h` | Efek Skor | Confluence Text |
|---|---|---|
| `>= +15%` | **+10** | "Net Inflow Institusional Kuat (X% dari volume 24H) — indikasi akumulasi" |
| `-15%` s.d. `+15%` | 0 (netral) | (tidak ada) |
| `<= -15%` | **-10** | "Peringatan: Net Outflow / Distribusi Terdeteksi (X% dari volume 24H)" |

Threshold pakai **persentase dari volume 24H**, bukan nominal dolar
mentah — supaya bisa dibandingkan apple-to-apple antar koin besar (BTC)
dan koin kecil (memecoin), sesuai cara Money Flow menampilkan datanya di
menu aslinya juga.

Diterapkan di kedua `buildIfvgAnalysis()` dan `buildPadawaeAnalysis()`,
persis di titik yang sama seperti confluence RVOL/HTF Trend yang sudah
ada — hanya menambah/mengurangi skor, tidak mengubah deteksi pola inti
strategi sama sekali.

## Kalau Fetch Money Flow Gagal

`$moneyFlow = []` (default) → `applyMoneyFlowConfluence()` mengembalikan
delta 0 tanpa confluence text — sinyal tetap dianalisis normal seperti
sebelum fitur ini ada, cuma tanpa bonus/penalti tambahan. Tidak pernah
membuat scan gagal/error hanya karena Money Flow API sedang lambat.

## Batasan yang Perlu Divalidasi (Belum Terbukti Otomatis Menaikkan Win Rate)

Confluence ini **belum tentu independen** dari RVOL — keduanya
sama-sama diturunkan dari data volume candle, jadi ada risiko
"menghitung dua kali" sinyal yang sebenarnya sama, bukan informasi baru
sungguhan. Sebelum dipercaya menaikkan probabilitas entry secara nyata,
**wajib divalidasi lewat Validasi Massal (Layer 10 QA)** di menu
Simulasi Trade — bandingkan win rate/profit factor gabungan sebelum vs
sesudah confluence ini aktif, untuk banyak koin sekaligus, bukan cuma
dipercaya karena "terlihat masuk akal" secara teori.

## Regression Test

`tests/Unit/SpotSignalEngineTest.php::test_money_flow_confluence_applies_identically_in_both_strategy_modes`
— memverifikasi bonus/penalti/netral bekerja identik di kedua mode
dengan data candle yang sama persis (hanya `flow_pct_24h` yang dibedakan).
