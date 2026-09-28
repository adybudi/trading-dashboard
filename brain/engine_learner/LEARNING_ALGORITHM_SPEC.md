# LEARNING ALGORITHM SPECIFICATION
## Mathematical Formulations & Heuristic Adaptation for Valid Spot Entries

**Module**: Autonomous Spot Strategy Learner  
**Location**: `brain/engine_learner/LEARNING_ALGORITHM_SPEC.md`

---

## 1. Mathematical Formulations

### 1.1. Feature Vector Representation $\mathbf{x}$
Untuk setiap urutan candle pada titik waktu $t$:

$$\mathbf{x} = \begin{bmatrix} x_{\text{ifvg}} \\ x_{\text{ssl}} \\ x_{\text{ote}} \\ x_{\text{padawae}} \\ x_{\text{rvol}} \\ x_{\text{htf}} \end{bmatrix} \in [0, 1]^6$$

Di mana:
1. $x_{\text{ifvg}} = \min\left(1.0, \frac{\text{Candle Body}}{\text{ATR}_{14}}\right)$ (Kekuatan Displacement FVG).
2. $x_{\text{ssl}} = \begin{cases} 1.0 & \text{jika low menembus prior low lalu close di atasnya} \\ 0.0 & \text{lainnya} \end{cases}$ (Liquidity Sweep).
3. $x_{\text{ote}} = \max\left(0.0, 1.0 - \frac{|\text{Price} - \text{Fib}_{0.618}|}{\text{Dealing Range}}\right)$ (Kedekatan OTE 61.8%).
4. $x_{\text{padawae}} = \min(1.0, \text{Rejection Count} / 3.0)$ (Pola 3 Penolakan).
5. $x_{\text{rvol}} = \min\left(1.0, \frac{\text{Volume}}{\text{SMA(Volume, 20)}}\right)$ (Volume Relatif).
6. $x_{\text{htf}} = \begin{cases} 1.0 & \text{jika HTF 4h/1d di atas EMA 50} \\ 0.5 & \text{jika netral} \\ 0.0 & \text{jika bearish ekstrem} \end{cases}$.

---

### 1.2. Scoring Function $S(\mathbf{x})$

$$S(\mathbf{x}) = \sum_{i=1}^{6} w_i \cdot x_i$$

Di mana $\sum w_i = 100$ dan bobot awal:
- $w_{\text{ifvg}} = 25$
- $w_{\text{ssl}} = 25$
- $w_{\text{ote}} = 20$
- $w_{\text{padawae}} = 15$
- $w_{\text{rvol}} = 10$
- $w_{\text{htf}} = 5$

---

### 1.3. Valid Spot Entry Decision Boundary

$$\text{Decision} = \begin{cases} \text{GOLDEN ENTRY (Akurasi 80\% – 100\%)} & \text{jika } S(\mathbf{x}) \ge 85 \text{ dan } x_{\text{ssl}} = 1.0 \text{ dan } \text{Price} < \text{Equilibrium} \\ \text{STANDARD ENTRY} & \text{jika } 70 \le S(\mathbf{x}) < 85 \\ \text{WAIT \& WATCH} & \text{jika } S(\mathbf{x}) < 70 \end{cases}$$

---

### 1.4. Invalidation & Target Formulations

$$\text{Entry Price} = P_{\text{current}}$$
$$\text{Take Profit 1 (TP1)} = P_{\text{current}} \times (1 + \text{Target}_{\text{min}} / 100)$$
$$\text{Take Profit 2 (TP2)} = P_{\text{current}} \times (1 + \text{Target}_{\text{max}} / 100)$$
$$\text{Stop Loss (SL)} = \text{Sweep Low} - (0.5 \times \text{ATR}_{14})$$
$$\text{Risk:Reward Ratio (RRR)} = \frac{\text{TP2} - \text{Entry}}{\text{Entry} - \text{SL}}$$
