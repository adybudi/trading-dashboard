# ALPHAMACHINE & Coin Hunter Trading Terminal

**ALPHAMACHINE** adalah platform trading profesional, pemindai diskon aset kripto real-time (**Coin Hunter**), simulator paper trading, dan mesin pembelajaran strategi otonom (**Autonomous Spot Strategy Learner**).

---

## 📌 Panduan Menjalankan Aplikasi & Terminal Commands

### 1. Menjalankan Dashboard Web & Coin Hunter
Untuk menyalakan server lokal dan membuka dashboard di browser:
```bash
php artisan start
```
- **Web Dashboard:** `http://localhost:8001`
- **Coin Hunter (Spot Discount Scanner):** `http://localhost:8001/signals`
- **Simulasi Trade & Backtester:** `http://localhost:8001/simulation`
- **Trade Journal:** `http://localhost:8001/trades`

*(Kustomisasi Port: `php artisan start --port=8080`)*

---

### 2. Menjalankan Autonomous Strategy Learner (Terpisah dari Web Server)

Modul **Autonomous Spot Learner Brain** dapat dijalankan secara terpisah kapan saja dari terminal:

#### A. Screening Otomatis 250 Koin Spot (Simpan Strategi Valid ke Database)
Memindai 250 koin spot teratas, menguji kriteria keuntungan berulang (*Repeatable Profit* $\ge 80\% - 100\%$), dan mencatat strategi valid ke database `validated_spot_strategies`:
```bash
# Sekali jalan (One-shot Full Screening 250 Koin):
php artisan spot:auto-learn

# Berjalan berkala di latar belakang (Daemon Mode setiap 5 menit):
php artisan spot:auto-learn --daemon
```

#### B. Kalibrasi Strategi pada Koin Tertentu
Melatih dan mengkalibrasi bobot konfluensi (iFVG, SSL Sweep, PADAWAE, OTE 61.8%) pada koin spesifik:
```bash
# Contoh untuk Bitcoin pada Timeframe 1H dengan 30 iterasi:
php artisan spot:learn --symbol=BTCUSDT --timeframe=1h --iterations=30

# Contoh untuk Solana pada Timeframe 4H:
php artisan spot:learn --symbol=SOLUSDT --timeframe=4h --iterations=50

# Memindai Golden Valid Entries terverifikasi pada pasar spot:
php artisan spot:learn --scan-all
```

---

## 📂 Struktur Dokumentasi Proyek (*Memory Rules*)

Sesuai aturan arsitektur proyek, file dokumentasi disimpan pada folder khusus:
- **`brain/`** (`/brain/`): Berisi seluruh **Logika Sistem, Algoritma Trading, Spesifikasi Matematika, dan PRD Engine** (iFVG, PADAWAE, OTE 61.8%, Dealing Range, Backtest Engine, QA Matrix, dan Learner Specs).
- **`plan_architecture/`** (`/plan_architecture/`): Berisi seluruh **Rencana Arsitektur, Desain UI/UX, Proposal Perubahan, dan Roadmap Fitur**.

---

## 🧪 Menjalankan Automated Testing Suite
Untuk memvalidasi seluruh fungsionalitas sistem (42+ automated tests):
```bash
php artisan test
```
