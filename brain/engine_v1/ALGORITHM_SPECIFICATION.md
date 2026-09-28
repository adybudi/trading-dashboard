# Algoritma Spesifikasi Teknis: Coin Hunter v1.4
## Presisi Matematis PnL & Real-Time Ticker Synchronization

---

## 1. Rumus Presisi Sinkronisasi Profit & Loss

### A. Level Take Profit (TP1 & TP2)
Untuk setiap sinyal yang terdeteksi dengan harga entry $P_{\text{entry}}$:

$$TP_1 = P_{\text{entry}} \times \left(1 + \frac{Target_{\text{min}}}{100}\right)$$

$$TP_2 = P_{\text{entry}} \times \left(1 + \frac{Target_{\text{max}}}{100}\right)$$

### B. Level Invalidation / Stop Loss (SL)
Level Stop Loss ditentukan dengan formula proteksi risiko asimetris:

$$SL = \min\left( \text{SSL\_Sweep\_Low}, \, P_{\text{entry}} \times \left(1 - \frac{Target_{\text{min}}}{RRR_{\text{min}} \times 100}\right) \right)$$

Di mana:
- $RRR_{\text{min}} \ge 2.5$
- $\text{Risk\%} = \frac{P_{\text{entry}} - SL}{P_{\text{entry}}} \times 100\%$
- $\text{RRR} = \frac{Target_{\text{max}}}{\text{Risk\%}}$

---

## 2. Kalkulasi Titik Harga Tunggu (Wait Zone) untuk Koin Non-Triggered

Jika harga pasar $P_{\text{current}} > \text{Equilibrium}$ (masih di area Premium):

$$\text{DealingRange} = \text{SwingHigh} - \text{SwingLow}$$
$$\text{WaitPrice}_{\text{Ideal}} = \text{SwingLow} + 0.50 \times \text{DealingRange} \quad (\text{Equilibrium})$$
$$\text{WaitPrice}_{\text{OTE}} = \text{SwingLow} + 0.382 \times \text{DealingRange} \quad (\text{OTE 61.8\% Retracement})$$
$$\text{Distance\%} = \frac{P_{\text{current}} - \text{WaitPrice}_{\text{Ideal}}}{P_{\text{current}}} \times 100\%$$

---

## 3. Real-Time Auto-Ticker Data Pipeline

Setiap interval $t = 5\text{s}$:
1. Frontend memanggil `GET /signals/live-prices`.
2. Backend mengambil data ter-cache dari CoinGecko/Gate.io API.
3. Frontend membandingkan $P_{\text{new}}$ vs $P_{\text{old}}$:
   - Jika $P_{\text{new}} > P_{\text{old}} \implies \text{Flash Green}$
   - Jika $P_{\text{new}} < P_{\text{old}} \implies \text{Flash Red}$
   - Jika $P_{\text{new}} == P_{\text{old}} \implies \text{No Flash}$
4. Mengupdate seluruh baris tabel koin dan nilai harga pada kartu sinyal secara instan tanpa reload browser.
