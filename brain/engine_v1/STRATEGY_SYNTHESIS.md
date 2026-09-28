> **STATUS: SUPERSEDED (2026-08-29).** Menggabungkan iFVG dan PADAWAE ke dalam
> satu skor/narasi tunggal (seperti dirancang di dokumen ini) terbukti
> menghasilkan pesan yang tidak koheren di UI — misalnya harga sudah berada
> di area Diskon (di bawah Equilibrium) tapi teks tetap menampilkan
> "masih berada di area Premium", karena PADAWAE (breakout/retest
> trendline murni, lihat `STRATEGI_PADAWAE.md`) sebetulnya tidak punya
> konsep Premium/Discount sama sekali — itu murni istilah iFVG/ICT (lihat
> `IFVG_RATING_GUIDE.md`). Engine sekarang menjalankan **dua mode terpisah
> yang bisa dipilih pengguna** (bukan diblend otomatis); lihat
> `STRATEGY_MODE_SELECTOR.md` untuk arsitektur & root-cause bug lengkap.
> Dokumen di bawah ini dipertahankan sebagai catatan sejarah desain awal.

# Strategi Synthesis & Analisis Trader Profesional
## Spot Signal Engine v1: IFVG Rating + PADAWAE Confluence + ICT Discount

---

## 1. Analisis & Sintesis Materi Rujukan

### 1.1. Materi 1: Inversion Fair Value Gap (iFVG) Rating Framework
Berdasarkan panduan *Jack Trades (2025)*, iFVG adalah salah satu setup dengan probabilitas tertinggi di pasar keuangan:
- **Mekanisme iFVG:**
  1. Terbentuk sebuah *Bearish FVG* (ketidakseimbangan harga di mana ada gap antara *Low candle 1* dan *High candle 3*).
  2. Alih-alih memantul turun, harga justru menembus (*displace*) ke atas melalui Bearish FVG tersebut dengan *body candle* yang ditutup penuh di atas area gap.
  3. Gap yang sebelumnya menjadi resisten sekarang mengalami **inversi (flip)** menjadi area **Support** baru. Saat harga melakukan *retest / pullback* ke area iFVG ini, trader masuk posisi *BUY*.
- **Kunci Pembeda Rating A+ vs Setup Gagal:**
  - Setup A+ **WAJIB** diawali oleh *Liquidity Sweep* (pengambilan likuiditas di bawah level low sebelumnya) dan terjadi di area **DISCOUNT**.
  - Setup yang terjadi di area *Premium* (harga sudah terlalu tinggi) otomatis turun grade menjadi B atau C dan harus dihindari.

### 1.2. Materi 2: Strategi PADAWAE (3x Rejection, 2x Break, 1x Retest)
Strategi PADAWAE menggunakan struktur gelombang koreksi (Elliot Waves) pada garis tren:
- **Cheat Formula PADAWAE:**
  1. **3x Rejection pada Trendline:** Harga menguji garis tren resisten turun sebanyak 3 kali, mengonfirmasi bahwa fase koreksi telah jenuh (gelombang 1-2-3-4-5 atau A-B-C selesai).
  2. **Breakout 1 (Impulse Candle):** Terjadi lonjakan volume dan momentum yang menembus garis tren resisten.
  3. **Pengukuran Rentang Harga (Price Range Projection):** Mengukur tinggi vertikal dari *swing low* ke puncak *impulse breakout* ($\Delta P$).
  4. **Retest 1 ke Support:** Harga kembali menguji garis tren yang ditembus atau area support/iFVG terdekat.
  5. **Breakout 2 (Target Delivery):** Proyeksi rentang harga $\Delta P$ ditempelkan pada titik retest sebagai target kenaikan utama (+10% s.d. +20%).

---

## 2. Penggabungan Konfluensi: "The Golden Confluence Spot Buy"

Ketika kedua materi di atas digabungkan dengan konsep **ICT / Smart Money Concepts (SMC)**, terbentuklah model sinyal spot yang sangat presisi:

```mermaid
graph TD
    A[1. Higher Timeframe Downtrend/Retracement] --> B[2. Liquidity Sweep at Key Support / EQL]
    B --> C[3. 3x Rejections on Descending Trendline (PADAWAE)]
    C --> D[4. Bullish Impulse Breaks Trendline & Flips Bearish FVG to iFVG]
    D --> E[5. Price Enters Deep Discount Zone: 0.618 - 0.786 Fib]
    E --> F[6. Retest into iFVG Support + Volume Absorption]
    F -->|TRIGGER BUY SIGNAL| G[ENTRY BUY AT DISCOUNT]
    G --> H[Target TP 1: +10% | Target TP 2: +20% / EQH Target]
    G --> I[Invalidation / SL: Below Liquidity Sweep Low]
```

---

## 3. Matriks Penentuan Area Diskon (Discount Matrix)

Dalam spot trading, kita **HANYA** membeli di area diskon:

$$\text{Range High} = \text{Tertinggi Terakhir (HTF Swing High)}$$
$$\text{Range Low} = \text{Terendah Terakhir (Sweep Low)}$$
$$\text{Equilibrium (50\%)} = \frac{\text{Range High} + \text{Range Low}}{2}$$

- **Area Premium ($> 50\%$ Range):** Dilarang melakukan entry Buy baru (zona penjualan/take profit).
- **Area Discount ($< 50\%$ Range):** Zona aman untuk mulai mencari setup iFVG.
- **Deep Discount / OTE ($61.8\% - 78.6\%$ Retracement):** Zona ideal dengan *Risk:Reward* paling optimal untuk target keuntungan spot 10% s.d. 20%.

---

## 4. Struktur Sinyal Spot 10% - 20%

Setiap sinyal yang dihasilkan oleh Engine v1 memuat 6 komponen esensial:
1. **Pair & Market:** Contoh: `BTC/USDT (Spot)` atau `SOL/USDT (Spot)`.
2. **Quality Rating:** `A+ (Score: 95/100)` atau `A (Score: 85/100)`.
3. **Discount Buy Zone:** Rentang harga entry yang presisi (berdasarkan batas atas & bawah iFVG).
4. **Target Take Profit (TP):**
   - **TP 1:** +10.0% (Amankan modal / partial take profit).
   - **TP 2:** +20.0% (Target utama / Equal Highs EQH).
5. **Invalidation Level (Stop Loss):** Level penutupan candle di bawah *Sweep Low* (risiko terukur ~3% - 5%).
6. **Confluence Checklist:**
   - [x] SSL Liquidity Swept
   - [x] PADAWAE 3-Rejections Trendline Break
   - [x] iFVG Support Flipped & Respected
   - [x] Deep Discount Zone (< 0.50 Range)
   - [x] Minimum 10% Upside Potential to Next Resistance
