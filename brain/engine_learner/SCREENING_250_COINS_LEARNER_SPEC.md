# SCREENING 250 COINS AUTONOMOUS LEARNER SPECIFICATION
## Multi-Asset Autonomous Strategy Learning & Persistent Knowledge Repository

**Module**: Autonomous Spot Strategy Learner  
**Location**: `brain/engine_learner/SCREENING_250_COINS_LEARNER_SPEC.md`

---

## 1. Algoritma Screening 250 Koin

### 1.1. Batch Chunk Ingestion Pipeline
Untuk memproses 250 koin tanpa hambatan I/O atau *rate limiting*, sistem menggunakan pembagian batch (*chunking*):
- **Ukuran Batch:** 10–25 koin per siklus.
- **Jeda Antar-Batch:** 1.5–2.0 detik.
- **Waktu Total 1 Siklus Penuh 250 Koin:** ~30–45 detik.

---

### 1.2. Kriteria Kelulusan Strategi Valid Masuk Database

Setiap koin dari 250 koin dievaluasi dengan formula:

$$\text{Validitas Strategi} = \begin{cases} \text{PERSIST KE DATABASE} & \text{jika } \text{Win Rate} \ge 80.0\% \text{ dan } \text{Win Streak} \ge 2 \text{ dan } S(\mathbf{x}) \ge 80 \\ \text{ABAIKAN / SKIP} & \text{lainnya} \end{cases}$$

---

### 1.3. Struktur Penyimpanan Terkalibrasi

Koin yang lolos disimpan ke tabel `validated_spot_strategies` dengan detail:
- Target Take Profit 1 (+10%) & Take Profit 2 (+20%).
- Stop Loss Protektif di bawah *Sweep Low* ($+ 0.5 \times \text{ATR}$).
- Bobot parameter yang telah terbukti menghasilkan profit pada koin tersebut.
