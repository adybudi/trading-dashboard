# Strategi PADAWAE (High Winrate Scalping)

> Sumber: `STRATEGI PADAWAE.pdf` (Crypto Trading Series, #beardivneverfail).
> Diringkas apa adanya untuk jadi rujukan implementasi
> `SpotSignalEngineService::buildPadawaeAnalysis()` / `detectPadawaePattern()`.

## Definisi

PADAWAE adalah strategi berbasis metode **Elliot Waves**: memanfaatkan
prinsip gelombang koreksi untuk mencari peluang trading jangka pendek
dengan winrate tinggi. **Tidak ada konsep Premium/Discount/OTE Fibonacci
di strategi ini** — itu istilah dari framework iFVG/ICT yang terpisah
(lihat `IFVG_RATING_GUIDE.md`). PADAWAE murni bekerja dengan trendline,
support/resistance, dan duplikasi rentang harga.

## Cheat Formula

> **3x Rejection, 2x Break, 1x Retest**

Urutan pola yang harus terjadi:
1. **3x Rejection** pada trendline (resisten untuk downtrend, support untuk uptrend).
2. **1x Breakout/Breakdown permulaan** (impulse candle pertama menembus trendline).
3. **1x Retest** ke support/resisten terdekat setelah breakout/breakdown pertama.
4. **1x Breakout/Breakdown kedua** (impulse candle kedua — "target delivery",
   konfirmasi kelanjutan tren).

## Langkah Kerja (kasus Downtrend / Bearish → dipakai untuk breakout reversal ke atas)

1. **Identifikasi Trend** — tarik trendline resisten menempel body candle,
   konfirmasi via multi-timeframe.
2. **Identifikasi Gelombang/Waves** — cari 3x rejection pada trendline
   tersebut (mengonfirmasi ini benar gelombang Elliot Wave, bukan noise).
3. **Identifikasi Rentang Harga (ΔP)** — tarik dari candle terendah di
   dalam tren sampai titik tertinggi setelah breakout (impulse candle).
   Pastikan sudah ada pembalikan setelah impulse untuk konfirmasi titik
   resisten di sana.
4. **Duplikasi Trendline** — copy trendline yang sama, tempel di
   candle rejection/koreksi saat breakout — untuk memetakan zona
   resistensi baru yang kemungkinan jadi area konsolidasi sebelum
   breakout kedua.
5. **Duplikasi Rentang Harga (ΔP) & Identifikasi Support** — proyeksikan
   ΔP dari langkah 3 ke ujung perkiraan breakout lanjutan, setelah
   support diidentifikasi lebih dulu. Rentang harga yang disalin ini jadi
   target kenaikan berikutnya.
6. **Buat Risk to Reward Ratio** — eksekusi saat konfirmasi/retest ke
   area support tercapai; pasang SL & TP.
7. **Biarkan Trading Plan Bekerja** — bila harga kembali ke titik entry
   sebelum target tercapai, itu bukan berarti gagal; masih ada
   kemungkinan harga lanjut menuju target utama selama SL belum tersentuh.

## Kasus Bullish (kebalikan)

Persis sama, dengan perspektif dibalik: trendline ditarik di **support**
(bukan resisten), 3x rejection terjadi di trendline support, breakout
naik menembus trendline, retest ke support/resisten terdekat, breakout
kedua konfirmasi kelanjutan naik.

## Pemetaan ke Implementasi Kode

`SpotSignalEngineService::detectPadawaePattern()` mengimplementasikan
varian **breakout reversal**: mencari 3x local-high rejection yang
membentuk deret menurun (descending trendline resisten khas downtrend),
lalu breakout naik menembusnya (`close > trendlineLevel`), ΔP dari
swing low ke breakout high, retest ke `trendlineLevel` (toleransi
±1.5%), lalu breakout kedua (`close` melewati `retestHigh`).

`SpotSignalEngineService::buildPadawaeAnalysis()` (mode strategi
`padawae`):
- **Entry point** = titik **retest** (bukan menunggu breakout kedua) —
  sesuai materi: "tunggu konfirmasi atau retest ke area support,
  jangan lupa setting SL dan TP." Breakout kedua adalah konfirmasi
  kelanjutan ("Biarkan Trading Plan Bekerja"), bukan sinyal entry itu
  sendiri.
- **Wait price** (saat belum retest) = `trendline_level` yang
  ditembus — bukan Equilibrium/OTE Fibonacci seperti di mode iFVG.
- **Stop Loss** = di bawah `trendline_level` (buffer 0.5x ATR) —
  invalidasi jika retest gagal ditahan.
- Skor tambahan (bukan bagian formula asli, ditambahkan agar konsisten
  dengan modul RVOL/HTF Trend engine lain): RVOL ≥ 1.25 dan konfirmasi
  tren HTF bullish menambah skor; HTF bearish mengurangi skor
  ("melawan arus utama").
