# PRD & SPESIFIKASI MATEMATIKA ENGINE: SMART SCREEN FUTURE (iFVG 2-ARAH & MONEY FLOW CONFLUENCE)

> **Modul:** Futures Signal Engine (`FuturesSignalEngineService`)  
> **Klasifikasi:** Trading 2 Arah (LONG & SHORT)  
> **Metodologi Strategi:** Inversion Fair Value Gap (iFVG) + Liquidity Sweep + Fibonacci Dealing Range + Real-Time Money Flow Confluence  
> **Eksklusi:** Strategi PADAWAE dieksklusikan (PADAWAE hanya untuk Breakout/Retest Trendline 1 Arah Spot, sedangkan iFVG dirancang murni untuk dynamic orderflow 2 arah).

---

## 1. Konsep Dasar & Alur Logika Trading 2 Arah (LONG & SHORT)

Engine **SMART SCREEN FUTURE** mengevaluasi struktur harga pada 250 koin secara instan dalam memori (*in-memory stream*) untuk menghasilkan sinyal presisi tinggi dengan kriteria matematis:

```mermaid
graph TD
    A[OHLCV Candles & Live Gate.io Ticker] --> B[Dealing Range Calculation: Swing High & Swing Low 35 Bars]
    B --> C{Evaluasi Arah Pasar}
    
    subgraph Skenario LONG (Bullish Setup)
        C -->|Price in Discount Zone <= 50%| D1[1. Deteksi SSL Liquidity Sweep Low]
        D1 --> D2[2. Deteksi Bullish iFVG Flip]
        D2 --> D3[3. Money Flow Inflow Confluence > 0]
        D3 --> D4[4. OTE Retracement 61.8% - 78.6%]
        D4 --> D5[Kalkulasi Skor Setup LONG -> Setup B / A / A+]
    end

    subgraph Skenario SHORT (Bearish Setup)
        C -->|Price in Premium Zone >= 50%| E1[1. Deteksi BSL Liquidity Sweep High]
        E1 --> E2[2. Deteksi Bearish iFVG Flip]
        E2 --> E3[3. Money Flow Outflow Confluence < 0]
        E3 --> E4[4. OTE Retracement 61.8% - 78.6%]
        E4 --> E5[Kalkulasi Skor Setup SHORT -> Setup B / A / A+]
    end
```

---

## 2. Spesifikasi Rumus Matematis & Deteksi iFVG

### A. Dealing Range & Equilibrium (Fibonacci 50%)
Untuk deret lilin (lookback $N = 35$ periode):
$$\text{Swing High} = \max_{i=0}^{N-1}(H_i), \quad \text{Swing Low} = \min_{i=0}^{N-1}(L_i)$$
$$\text{Dealing Range} = \text{Swing High} - \text{Swing Low}$$
$$\text{Equilibrium (50\%)} = \text{Swing Low} + 0.50 \times \text{Dealing Range}$$

- **Zona Diskon (Syarat LONG):** $\text{Current Price} \le \text{Equilibrium}$
- **Zona Premium (Syarat SHORT):** $\text{Current Price} \ge \text{Equilibrium}$

---

### B. Rumus Bullish iFVG (Skenario LONG)
1. **Identifikasi Bearish FVG Masa Lalu:**
   Terjadi pada bar $i$ ke bar $i+2$ jika:
   $$\text{Gap Top} = L_i, \quad \text{Gap Bottom} = H_{i+2} \quad (\text{dengan } L_i > H_{i+2})$$
2. **Inversion Breakout (Displacement):**
   Harga kemudian menembus ke atas Gap Top ($C_k > \text{Gap Top}$ untuk $k > i+2$).
3. **Retest Support (Bullish Inversion Trigger):**
   Lilin saat ini kembali menguji area gap dari atas ($L_{\text{curr}} \le \text{Gap Top}$ dan $C_{\text{curr}} \ge \text{Gap Bottom}$).

---

### C. Rumus Bearish iFVG (Skenario SHORT)
1. **Identifikasi Bullish FVG Masa Lalu:**
   Terjadi pada bar $i$ ke bar $i+2$ jika:
   $$\text{Gap Bottom} = H_i, \quad \text{Gap Top} = L_{i+2} \quad (\text{dengan } H_i < L_{i+2})$$
2. **Inversion Breakdown (Displacement):**
   Harga kemudian menembus ke bawah Gap Bottom ($C_k < \text{Gap Bottom}$ untuk $k > i+2$).
3. **Retest Resistance (Bearish Inversion Trigger):**
   Lilin saat ini kembali menguji area gap dari bawah ($H_{\text{curr}} \ge \text{Gap Bottom}$ dan $C_{\text{curr}} \le \text{Gap Top}$).

---

### D. Deteksi Liquidity Sweep (SSL Sweep vs BSL Sweep)
- **Sell-Side Liquidity (SSL) Grab (Untuk LONG):**
  Lilin baru membuat titik terendah baru di bawah level support sebelumnya tetapi langsung ditutup (*close*) kembali di atas level support tersebut:
  $$L_t < \min(\text{Prior Lows}) \quad \text{dan} \quad C_t > \min(\text{Prior Lows})$$
- **Buy-Side Liquidity (BSL) Grab (Untuk SHORT):**
  Lilin baru membuat titik tertinggi baru di atas level resistance sebelumnya tetapi langsung ditutup (*close*) kembali di bawah level resistance tersebut:
  $$H_t > \max(\text{Prior Highs}) \quad \text{dan} \quad C_t < \max(\text{Prior Highs})$$

---

### E. Integrasi Konfluensi Arus Uang Paus (Money Flow Confluence)
Data arus modal bersih dari `MoneyFlowWhaleTrackerService` ($24\text{h Net Flow} = \text{Taker Buy Vol} - \text{Taker Sell Vol}$):
- **LONG Confluence:**
  - $\text{Net Flow} > 0$ dan $\text{Flow Ratio} \ge +5\%$ $\rightarrow$ Bonus Skor $+15$ poin.
  - Paus sedang melakukan akumulasi bersih (*Whale Inflow*).
- **SHORT Confluence:**
  - $\text{Net Flow} < 0$ dan $\text{Flow Ratio} \le -5\%$ $\rightarrow$ Bonus Skor $+15$ poin.
  - Paus sedang melakukan distribusi/dumping bersih (*Whale Outflow*).

---

## 3. Matriks Skor Kualitas Setup (Setup Quality Tiering)

Total Skor Konfluensi dihitung dari skala 0 – 100:

| Parameter Pengujian | Poin Bobot (LONG) | Poin Bobot (SHORT) |
|---|---|---|
| **iFVG Retest Alignment** | 30 Poin | 30 Poin |
| **Liquidity Sweep (SSL/BSL Grab)** | 25 Poin | 25 Poin |
| **Dealing Range OTE (61.8%–78.6%)** | 15 Poin | 15 Poin |
| **Money Flow Net Alignment** | 15 Poin | 15 Poin |
| **Higher Timeframe Trend Alignment** | 15 Poin | 15 Poin |

### Label Klasifikasi Setup
- **Setup A+ (Golden Master Setup, Skor $\ge 85$):**
  Sinyal terkonfirmasi oleh 4–5 faktor konfluensi: iFVG valid + Sweep terkonfirmasi + Arus Paus (*Money Flow*) searah + HTF Trend alignment.
- **Setup A (Strong Setup, Skor $75 - 84$):**
  Sinyal terkonfirmasi oleh 3–4 faktor konfluensi kuat: iFVG valid + Sweep atau Money Flow kuat.
- **Setup B (Good Setup, Skor $65 - 74$):**
  Sinyal iFVG valid dengan minimal 2 konfluensi pendukung.
- *Skor $< 65$:* Diabaikan / masuk daftar pantau (*Watchlist*), tidak ditampilkan sebagai sinyal aksi aktif.

---

## 4. Parameter Target Profit (TP) & Stop Loss (SL) Futures

1. **Skenario LONG:**
   $$\text{Entry Price} = \text{Current Price} \text{ (atau iFVG Re-test Level)}$$
   $$\text{Stop Loss (SL)} = \min(\text{SSL Sweep Low}, \text{iFVG Bottom}) \times 0.995$$
   $$\text{Take Profit 1 (TP1)} = \text{Entry Price} \times (1 + \frac{\text{Target Min \%}}{100})$$
   $$\text{Take Profit 2 (TP2)} = \text{Entry Price} \times (1 + \frac{\text{Target Max \%}}{100})$$

2. **Skenario SHORT:**
   $$\text{Entry Price} = \text{Current Price} \text{ (atau iFVG Re-test Level)}$$
   $$\text{Stop Loss (SL)} = \max(\text{BSL Sweep High}, \text{iFVG Top}) \times 1.005$$
   $$\text{Take Profit 1 (TP1)} = \text{Entry Price} \times (1 - \frac{\text{Target Min \%}}{100})$$
   $$\text{Take Profit 2 (TP2)} = \text{Entry Price} \times (1 - \frac{\text{Target Max \%}}{100})$$

3. **Perkiraan Harga Likuidasi (Estimated Liquidation Price):**
   $$\text{Liq LONG} = \text{Entry} \times \left(1 - \frac{1}{\text{Leverage}} \times 0.90\right)$$
   $$\text{Liq SHORT} = \text{Entry} \times \left(1 + \frac{1}{\text{Leverage}} \times 0.90\right)$$
