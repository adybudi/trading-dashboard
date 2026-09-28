# Panduan Penggunaan Sistem ALPHAMACHINE

Dokumen ini menjelaskan cara menjalankan server, command yang tersedia, dan cara memakai fitur Coin Hunter serta Simulasi Trade.

---

## 1. Prasyarat

- PHP 8.2+, Composer, Node.js, MySQL sudah jalan
- File `.env` sudah terisi (koneksi database, dll — lihat `.env.example` sebagai referensi)
- Migration sudah dijalankan minimal sekali:
  ```bash
  php artisan migrate
  ```

---

## 2. Menjalankan Server (Serve)

### Cara Tercepat — Semua Sekaligus

```bash
composer dev
```

Satu command ini menjalankan **sekaligus**: server HTTP (`:8001`), queue worker, log tail, dan Vite (asset build). Buka browser ke:

```
http://localhost:8001
```

Login pakai akun sendiri, atau klik **"1-Click Demo Login"** di halaman login untuk akun demo instan.

### Cara Manual — Satu per Satu

Kalau tidak mau pakai `composer dev`, bisa jalankan satu-satu di terminal terpisah:

```bash
php artisan serve --port=8001         # server HTTP
php artisan queue:listen --tries=1    # queue worker
php artisan pail                      # log tail (opsional)
npm run dev                           # vite asset build
```

Atau pakai launcher bawaan:

```bash
php artisan start --port=8001
```

---

## 3. Real-Time Push (WebSocket) — Opsional, untuk Kecepatan Maksimal

Secara default sistem sudah real-time via polling (ticker update tiap 1-4 detik). Untuk update harga **instan tanpa delay sama sekali** (push langsung dari Gate.io), jalankan 2 proses tambahan di **terminal terpisah**:

```bash
# Terminal tambahan #1 — server WebSocket
php artisan reverb:start

# Terminal tambahan #2 — daemon streaming harga live dari Gate.io
php artisan market:stream --limit=100
```

**Catatan penting**: kalau kedua proses ini TIDAK dijalankan, sistem tetap berfungsi normal — otomatis fallback ke polling REST. Jadi ini murni opsional untuk kecepatan ekstra, bukan wajib untuk sistem bisa jalan.

Kedua command ini harus **terus berjalan** di background selama Anda ingin real-time push aktif (biarkan terminal terbuka, atau jalankan lewat `nohup`/`screen`/`tmux` kalau ingin di-background-kan).

---

## 4. Menu Coin Hunter

**URL:** `/signals`

- Pilih **Timeframe** (15M / 1H / 4H / 1D) dan **Target Profit** (Scalp / Standard / Extended / Big Runner / Custom) di bagian atas.
- Kartu sinyal **Grade A+ / A** muncul otomatis di bawah judul "SINYAL TITIK BELI DISKON TERDETEKSI" — auto-refresh tiap 5 detik, badge **"Baru"** berkedip untuk sinyal yang baru masuk.
- Tombol **"Scan Semua Koin"** untuk memaksa refresh instan (bukan menunggu siklus otomatis).
- Klik **"Chart"** di kartu sinyal untuk lihat detail candlestick + breakdown confluence (iFVG, sweep, PADAWAE, RVOL, HTF trend).
- Tombol **"Simulasi"** di kartu sinyal → langsung buka posisi paper trade dari sinyal itu.

---

## 5. Menu Simulasi Trade

**URL:** `/simulation`

### A. Backtest Satu Koin (Tab "Historical Backtester")

1. Pilih koin, timeframe, target profit, modal awal virtual.
2. Atur **Fee & Slippage** per sisi kalau mau (default 0.1% + 0.05%, sudah realistis).
3. Klik **"Jalankan Backtest"** — hasil (win rate, profit factor, equity curve, log transaksi) muncul instan.

> **Tips**: mau setara data "1 bulan" atau lebih tanpa nunggu beneran sebulan? Pilih **Timeframe 4H atau 1D** dengan **Candle Count** besar (maksimal 300). Contoh: TF 1D + 300 candle ≈ setara ~10 bulan histori sekaligus.

### B. Validasi Massal / Layer 10 QA — Cek Statistik ke Banyak Koin Sekaligus

Masih di tab "Historical Backtester", scroll ke bawah, ada panel **"Validasi Massal (Layer 10 QA)"**.

1. Pilih **Jumlah Koin** (10 / 20 / 50 / 100 — diambil dari top volume liquiditas).
2. Pilih **Timeframe** dan **Target Profit**.
3. Klik **"Jalankan Validasi Massal"**.
4. Hasil yang muncul:
   - **Win Rate Agregat** & **Profit Factor** gabungan dari semua koin
   - **Status Layer 10**: TERVALIDASI (kalau win rate ≥80% & profit factor ≥2.5) atau BELUM TERVALIDASI
   - **Breakdown per kondisi pasar**: bullish / sideways / bearish — masing-masing dengan win rate & profit factor sendiri
   - **Tabel detail per koin** — bisa lihat koin mana yang paling kuat/lemah

Ini jauh lebih cepat dan lebih valid secara statistik dibanding memantau manual satu koin per satu selama berminggu-minggu.

### C. Paper Trading (Tab "Paper Trading Virtual")

- Buka posisi manual lewat modal "Simulasi" (juga bisa dari kartu sinyal Coin Hunter).
- Pilih mode **Spot** atau **Futures** (dengan leverage 2x–100x).
- Pilih sizing: **Modal Tetap ($)** atau **Risk % Akun** (margin dihitung otomatis dari jarak Entry-SL, standar risk management).
- Posisi terbuka dipantau live tiap 3 detik — funding rate otomatis terpotong untuk posisi futures leverage tiap ~8 jam.
- Tutup posisi kapan saja lewat tombol **"Tutup Posisi"** (sistem tidak auto-close saat TP/SL kena — ini desain yang disengaja untuk melatih disiplin manual exit).
- Tombol **"Reset"** untuk reset saldo virtual ke awal kalau mau mulai ulang.

---

## 6. Ringkasan Command CLI

| Command | Fungsi |
|---|---|
| `composer dev` | Jalankan semua (server + queue + log + vite) sekaligus |
| `php artisan start --port=8001` | Launcher bawaan (cetak URL dashboard, jalankan server) |
| `php artisan serve --port=8001` | Server HTTP saja |
| `php artisan reverb:start` | Server WebSocket (untuk real-time push) |
| `php artisan market:stream --limit=100` | Daemon streaming harga live dari Gate.io |
| `php artisan spot:learn --symbol=BTCUSDT --iterations=30` | Latih & kalibrasi strategi untuk 1 koin (CLI) |
| `php artisan spot:learn --scan-all` | Scan semua koin cari Golden Entry (CLI) |
| `php artisan migrate` | Jalankan migration database |
| `php artisan test` | Jalankan seluruh test suite |
| `npm run build` | Build asset frontend untuk produksi |

---

## 7. Troubleshooting Singkat

- **Badge "Mode Fallback — Data Tidak Live" muncul** → Gate.io API sedang lambat/timeout, sistem otomatis pakai data cadangan sementara. Tidak perlu panik, biasanya pulih sendiri dalam beberapa detik.
- **WebSocket tidak terasa instan** → pastikan `php artisan reverb:start` **dan** `php artisan market:stream` sedang berjalan di terminal terpisah. Kalau salah satu mati, sistem otomatis kembali ke polling (masih berfungsi, cuma sedikit lebih lambat).
- **Mau reset semua data simulasi** → tombol "Reset" di halaman Simulasi Trade (tab Paper Trading).
- **Muncul pesan error "Sudah ada posisi terbuka di [KOIN]"** → sistem sengaja mencegah buka 2 posisi di koin yang sama bersamaan. Tutup posisi lama dulu.
- **Server tidak mau start / port sudah dipakai** → ganti port lewat `--port=8002` (atau port lain) di command `serve`/`start`, sesuaikan juga `APP_URL` di `.env` kalau perlu.
