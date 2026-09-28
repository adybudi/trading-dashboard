# 📊 Laporan Analisis Probabilitas Strategi Trading
## AlphaMachine Coin Hunter — Audit 2 Strategi Engine v1

**Tanggal:** 2026-09-25
**Scope:** `SpotSignalEngineService` (iFVG + PADAWAE), `FuturesSignalEngineService`, `BacktestEngineService`, `AutonomousSpotLearnerService`

---

## 1. Dua Strategi yang Tertanam dalam Sistem

### Strategi 1: iFVG (Inversed Fair Value Gap) — Mean Reversion / Discount Hunting
**Lokasi:** `buildIfvgAnalysis()` di `SpotSignalEngineService.php`

**Komponen konfluensi yang sudah diimplementasi:**
| # | Komponen | Bobot Skor | Status |
|---|----------|-----------|--------|
| 1 | Dealing Range (Swing High/Low 35 candles) | Struktural | ✅ Ada |
| 2 | OTE Discount Zone (61.8% – 78.6% retracement) | +15 / +20 | ✅ Ada |
| 3 | SSL Sweep (Liquidity Sweep — fakeout low) | +20 | ✅ Ada |
| 4 | iFVG (Inversed FVG — gap yang terisi sebagai support) | +20 | ✅ Ada |
| 5 | RVOL (Relative Volume ≥ 1.25x) | +10 / -10 | ✅ Ada |
| 6 | HTF Trend Confluence (timeframe lebih tinggi) | +10 / -15 | ✅ Ada |
| 7 | Potential Upside Room (ruang ke swing high) | +10 | ✅ Ada |
| 8 | Money Flow Confluence (net flow ≥ ±15%) | ±10 | ✅ Ada |

**TP/SL:**
- TP1: +Target Min% (default 10%)
- TP2: +Target Max% (default 20%)
- SL: min(Buffered Sweep Low − 0.5×ATR, formula RRR)

---

### Strategi 2: PADAWAE — Breakout Reversal / Trendline Break & Retest
**Lokasi:** `buildPadawaeAnalysis()` + `detectPadawaePattern()` di `SpotSignalEngineService.php`

**Komponen konfluensi yang sudah diimplementasi:**
| # | Komponen | Bobot Skor | Status |
|---|----------|-----------|--------|
| 1 | 3x Rejection (descending local highs) | Struktural (wajib) | ✅ Ada |
| 2 | Breakout 1 (close > trendline level) | Struktural (wajib) | ✅ Ada |
| 3 | Retest (low menyentuh trendline ±1.5%, close tetap di atas) | +12 | ✅ Ada |
| 4 | Breakout 2 / Target Delivery (close > retest high) | +8 | ✅ Ada |
| 5 | Potential Upside Room | +10 | ✅ Ada |
| 6 | RVOL (≥ 1.25x) | +10 / -10 | ✅ Ada |
| 7 | HTF Trend Confluence | +10 / -15 | ✅ Ada |
| 8 | Money Flow Confluence | ±10 | ✅ Ada |

**TP/SL:**
- TP1/TP2: sama formula dengan iFVG (persentase dari entry)
- SL: di bawah trendline level − 0.5×ATR
- Wait Price: trendline level (bukan OTE)

---

## 2. Kelemahan & Celah yang Ditemukan (Menurunkan Probabilitas)

### 🔴 KRITIKAL — Harus Diperbaiki

#### K-1: Tidak Ada Deteksi Market Structure (BOS / CHoCH / MSS)
**Masalah:** Kedua strategi sama sekali tidak mendeteksi **Break of Structure (BOS)** atau **Change of Character (CHoCH)** — konsep SMC (Smart Money Concept) fundamental yang membedakan apakah market sedang dalam tren atau sedang membalik arah.
- iFVG membeli di "discount" tapi tidak mengkonfirmasi bahwa **struktur pasar sudah berubah bullish** (ada higher high / higher low terbentuk).
- PADAWAE mendeteksi breakout trendline tapi tidak mengkonfirmasi apakah breakout tersebut diikuti **perubahan struktur** yang legitimate atau hanya spike/fakeout.

**Dampak:** Banyak sinyal akan triggered di area discount yang masih dalam tren turun kuat (catching falling knives). Tanpa BOS/CHoCH, probabilitas entry bisa turun 15-25%.

**Rekomendasi:** Tambahkan deteksi BOS (higher high menembus swing high terakhir pada candle terbaru) sebagai konfluensi tambahan (+15 skor jika BOS bullish terdeteksi, -20 jika masih bearish structure).

---

#### K-2: Tidak Ada Multi-Timeframe Structure Alignment
**Masalah:** HTF Trend menggunakan klasifikasi sederhana (`classifyHtfTrend()`) yang hanya melihat apakah candle HTF membentuk higher-high/higher-low secara kasar. Ini **bukan** analisis struktur HTF yang sebenarnya.

Yang dibutuhkan tapi belum ada:
- Apakah **HTF sedang di zona premium/discount sendiri?** (alignment vertikal)
- Apakah ada **HTF Order Block** di dekat entry point?
- Apakah **HTF baru saja BOS/CHoCH?**

**Dampak:** Konfluensi HTF yang ada terlalu lemah (+10/-15 skor) untuk sesuatu yang seharusnya menjadi **filter utama** — seharusnya ini bisa menjadi veto (jika HTF bearish structure, JANGAN entry long sama sekali, bukan sekadar -15 skor).

**Rekomendasi:** Ubah HTF dari sekadar penambah/pengurang skor menjadi **hard filter**: jika HTF structure bearish dan belum CHoCH, sinyal LONG auto-reject (tidak peduli skor iFVG/PADAWAE-nya berapa).

---

#### K-3: Tidak Ada Order Block Detection
**Masalah:** Sistem mendeteksi iFVG (Fair Value Gap) tapi **tidak mendeteksi Order Block (OB)** — candle body terakhir sebelum displacement move yang menjadi zona supply/demand institusional.

**Kenapa penting:**
- iFVG tanpa OB di dekatnya = gap biasa yang belum tentu punya signifikansi institusional
- OB + iFVG di zona discount = konfluensi paling kuat dalam SMC
- PADAWAE juga akan jauh lebih reliable jika retest terjadi **tepat di area OB**

**Dampak:** Mengurangi ability filter noise. Banyak iFVG yang terdeteksi tidak relevan karena bukan berada di zona OB.

**Rekomendasi:** Tambahkan `detectOrderBlock()` — identifikasi candle bullish/bearish terakhir sebelum impulse move (displacement), tandai zona body-nya, lalu cek apakah current price / entry area overlap dengan OB zone.

---

### 🟡 PENTING — Meningkatkan Probabilitas Signifikan

#### P-1: Tidak Ada Session/Killzone Filter
**Masalah:** Crypto market memiliki seasonality intraday yang kuat:
- **Asia Session (00:00-08:00 UTC):** Biasanya fase manipulation/accumulation
- **London Open (08:00-12:00 UTC):** Awal pergerakan sesungguhnya
- **NY Session / NYSE Open (13:30-16:00 UTC):** Highest-volume / displacement terbesar

Sistem saat ini memproses sinyal 24 jam tanpa memperhitungkan **kapan** sinyal muncul. Sinyal yang muncul di Asia Session (low volume, high manipulation) punya probabilitas jauh lebih rendah dibanding sinyal yang muncul di London/NY open.

**Dampak:** Tanpa filter ini, win rate tergerus oleh sinyal-sinyal false breakout/fakeout yang dominan di off-peak hours.

**Rekomendasi:** Tambahkan `session_context` ke analisis: bonus skor +5 jika sinyal muncul di London/NY killzone, penalti -5 jika Asia off-peak. Untuk timeframe ≥ 4H, session filter bisa dinonaktifkan.

---

#### P-2: Tidak Ada Candle Pattern Confirmation di Entry
**Masalah:** Engine melakukan entry hanya berdasarkan kondisi zone (di discount, ada iFVG, etc.) tapi **tidak mengkonfirmasi apakah ada candle bullish reversal** yang menunjukkan rejection dari zona tersebut.

Pola candle yang hilang:
- **Bullish Engulfing** di area OTE/iFVG
- **Pin Bar / Hammer** di sweep low
- **Morning Star** di support

**Dampak:** Entry tanpa konfirmasi candle reversal berarti engine sering masuk terlalu dini ("anticipation entry" bukan "confirmation entry"), menyebabkan banyak trade hit SL sebelum reversal benar-benar terjadi.

**Rekomendasi:** Tambahkan `detectReversalCandle()` sebagai konfluensi tambahan (+10 jika engulfing/hammer terdeteksi di zona entry, netral jika tidak). Ini sangat meningkatkan timing entry.

---

#### P-3: iFVG Sizing Validation Kurang Ketat
**Masalah:** Deteksi iFVG saat ini hanya mengecek `c1['low'] > c3['high']` (ada gap) dan price mengisinya. Tidak ada validasi:
- **Minimum gap size** relatif terhadap ATR (gap terlalu kecil = noise, bukan displacement)
- **Context of the FVG** — apakah FVG terbentuk setelah displacement move yang signifikan atau just normal volatility

**Dampak:** Terlalu banyak false-positive iFVG yang sebenarnya hanya volatilitas normal.

**Rekomendasi:** Tambahkan threshold: gap size minimal 0.5× ATR₁₄ agar dianggap sebagai iFVG yang valid. FVG yang lebih kecil dari 0.5× ATR diabaikan.

---

#### P-4: PADAWAE — Tolerance Terlalu Fixed
**Masalah:** Retest tolerance PADAWAE hardcoded di ±1.5% dari trendline level. Pada coin volatile (meme coin), 1.5% terlalu ketat — sering miss valid retest. Pada coin stabil (BTC), 1.5% mungkin terlalu longgar.

**Rekomendasi:** Gunakan dynamic tolerance berbasis ATR: `tolerance = 0.5 × ATR₁₄` alih-alih fixed percentage.

---

#### P-5: Swing High/Low Lookback Statis (35 Candles)
**Masalah:** Lookback 35 candles untuk Dealing Range bersifat fixed, tidak adaptif terhadap kondisi market:
- Di market trending kuat, 35 candle mungkin terlalu pendek (dealing range terlalu sempit, OTE zone salah)
- Di market ranging/choppy, 35 candle mungkin terlalu panjang (swing high/low terlampau jauh dari area relevant saat ini)

**Rekomendasi:** Implementasi adaptive lookback: gunakan pivot-point detection (swing high/low terakhir yang signifikan berdasarkan ATR) alih-alih fixed window.

---

### 🟢 MINOR — Nice to Have

#### M-1: Tidak Ada Divergence Detection (RSI/MACD Hidden Divergence)
**Masalah:** Divergence antara price action dan momentum oscillator (RSI, MACD) adalah salah satu konfirmasi terkuat untuk reversal — terutama **hidden bullish divergence** (price makes higher low, RSI makes lower low → continuation bullish). Engine saat ini 100% price-action murni tanpa oscillator.

**Rekomendasi:** Tambahkan RSI 14-period, deteksi bullish/bearish divergence, gunakan sebagai konfluensi bonus (+5 skor).

---

#### M-2: Tidak Ada Risk-per-Trade Position Sizing Adapatif
**Masalah:** Backtest menggunakan fixed `allocationPct` (default 25% per trade). Tidak ada:
- Kelly Criterion atau fractional Kelly
- Scaling size berdasarkan grade sinyal (A+ alokasi lebih besar vs B lebih kecil)
- Volatility-adjusted position sizing (ATR-based)

**Rekomendasi:** Implementasi position sizing per-grade: A+ = 30% allocation, A = 20%, B = 10%.

---

#### M-3: Korelasi RVOL dan Money Flow Belum Divalidasi
**Masalah:** Sistem sudah mendokumentasikan risiko ini di `MONEY_FLOW_CONFLUENCE.md`:
> "confluence ini belum tentu independen dari RVOL — keduanya sama-sama diturunkan dari data volume... ada risiko menghitung dua kali sinyal yang sebenarnya sama"

Tapi masih belum ada penanganan teknis. Keduanya masih masing-masing ±10 skor secara independen.

**Rekomendasi:** Jika RVOL ≥ 1.25x DAN Money Flow ≥ +15%, hanya ambil bonus terbesar (10, bukan 20). Hindari double-counting.

---

#### M-4: Tidak Ada Trailing Stop Loss
**Masalah:** Setelah trade masuk dan harga bergerak sesuai arah, SL tetap statis di posisi awal. Tidak ada mekanisme:
- Move SL ke breakeven setelah hit TP1
- Trailing stop berbasis ATR setelah profit tertentu

**Rekomendasi:** Setidaknya di backtest simulator, implementasi SL→breakeven setelah TP1 tercapai (sudah umum dan meningkatkan expectancy).

---

## 3. Peta Prioritas Perbaikan

```
                    DAMPAK PADA WIN RATE
                    ↑
                    │
         [K-1]     │  [K-2]      [K-3]
        BOS/CHoCH  │  HTF Hard   Order Block
                   │  Filter
    ─ ─ ─ ─ ─ ─ ─ ┼ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─→ EFFORT
                   │
         [P-2]     │  [P-1]      [P-3]
        Reversal   │  Session    iFVG Size
        Candle     │  Filter     Validation
                   │
         [P-5]     │  [P-4]      [M-1]
        Adaptive   │  Dynamic    Divergence
        Lookback   │  Tolerance
                   │
         [M-3]     │  [M-4]      [M-2]
        RVOL/MF    │  Trailing   Position
        Dedup      │  SL         Sizing
```

---

## 4. Estimasi Peningkatan Probabilitas

| Prioritas | Item | Win Rate Impact (Est.) |
|-----------|------|----------------------|
| 🔴 K-1 | BOS / CHoCH Detection | +8% ~ +12% |
| 🔴 K-2 | HTF Hard Filter | +5% ~ +10% |
| 🔴 K-3 | Order Block Detection | +5% ~ +8% |
| 🟡 P-1 | Session / Killzone Filter | +3% ~ +5% |
| 🟡 P-2 | Reversal Candle Confirmation | +5% ~ +8% |
| 🟡 P-3 | iFVG Minimum Size (ATR-based) | +2% ~ +4% |
| 🟡 P-4 | Dynamic PADAWAE Tolerance | +1% ~ +3% |
| 🟡 P-5 | Adaptive Lookback | +2% ~ +4% |
| 🟢 M-1 | RSI/MACD Divergence | +1% ~ +3% |
| 🟢 M-2 | Grade-based Position Sizing | Expectancy, bukan WR |
| 🟢 M-3 | RVOL/MF Double-count Fix | +1% ~ +2% |
| 🟢 M-4 | Trailing Stop / BE Stop | Expectancy, bukan WR |

**Total estimasi peningkatan kumulatif: +20% ~ +35% win rate** jika seluruh item kritikal dan penting diimplementasi.

---

## 5. Apa yang Sudah Bagus ✅

1. **Separation of Concerns** — dua strategi terpisah (iFVG vs PADAWAE), tidak dicampur, masing-masing punya path analisis sendiri.
2. **Money Flow Confluence** — methodology-agnostic, diterapkan identik di kedua strategi.
3. **ATR-buffered SL** — SL di-buffer 0.5×ATR di bawah sweep low (menghindari premature stopout dari normal wicks).
4. **Lookforward bias prevention** — backtest engine memvalidasi HTF candle menggunakan `alignHtfCandles()` untuk menghindari future data leak.
5. **Repeatable Profit Validation Framework** — syarat 80% win rate + 3 consecutive wins + profit factor ≥ 2.5 sebelum strategi dianggap valid.
6. **Batch Backtest (Layer 10 QA)** — validasi massal multi-koin dengan capital splitting realistis.

---

## 6. Rekomendasi Urutan Implementasi

### Phase 1 — Quick Wins (1-2 hari)
1. **P-3:** iFVG minimum gap size ≥ 0.5×ATR₁₄
2. **P-4:** Dynamic PADAWAE tolerance berbasis ATR
3. **M-3:** Fix RVOL/Money Flow double-counting

### Phase 2 — Core Improvements (3-5 hari)
4. **K-1:** Implementasi BOS/CHoCH detection
5. **P-2:** Reversal candle pattern confirmation
6. **K-2:** HTF dari soft-score menjadi hard filter

### Phase 3 — Advanced (5-7 hari)
7. **K-3:** Order Block detection + overlap dengan iFVG zone
8. **P-1:** Session/Killzone filter
9. **P-5:** Adaptive lookback

### Phase 4 — Optimization (opsional)
10. **M-1:** RSI divergence confluence
11. **M-2:** Grade-based position sizing
12. **M-4:** Trailing stop logic di backtest

---

*Report ini dibuat berdasarkan audit kode sumber engine v1 dan dokumentasi brain/, bukan berdasarkan hasil backtest aktual. Rekomendasi implementasi sebaiknya divalidasi lewat Batch Backtest (Layer 10 QA) sebelum vs sesudah untuk mengukur dampak nyata.*
