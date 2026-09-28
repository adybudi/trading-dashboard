# Product Requirements Document (PRD)
## Project: Trading Journal & Performance Monitoring Dashboard

---

## 1. Executive Summary & Product Vision
Aplikasi **Trading Journal & Performance Monitoring Dashboard** adalah platform berbasis web modern yang dirancang untuk membantu trader (Forex, Crypto, Saham, Futures/Komoditas) mencatat, menganalisis, dan memantau performa trading mereka secara real-time.

Tujuan utama dari aplikasi ini adalah meningkatkan disiplin, konsistensi, dan profitabilitas trader melalui:
1. **Sistem Autentikasi & Keamanan:** Akses awal diarahkan ke halaman login (`/login`) dengan proteksi middleware untuk seluruh data portofolio.
2. **Layout Sidebar Kiri (Left Sidebar Navigation):** Navigasi profesional bergaya terminal fintech (Linear, Raycast, Bloomberg) yang efisien, terorganisir, dan responsif di perangkat mobile dan desktop.
3. **Pencatatan Cepat & Terstruktur (Journaling):** Form efisien dengan kalkulasi otomatis PnL, RR, ROI, serta dukungan screenshot Before & After.
4. **Visualisasi Performa Real-Time:** Dashboard responsif dengan KPI metrics, Equity Curve, Drawdown tracker, dan Calendar Heatmap.
5. **Konektivitas Real-Time via WebSocket (Laravel Reverb):** Pembaruan data instan tanpa perlu refresh halaman (live status trade, live metrics, multi-device synchronization).
6. **Desain Minimalis Dual-Theme (Anti-AI Slop):** UI terinspirasi terminal finansial pro, palet warna presisi dengan variabel Tailwind CSS (Light & Dark mode), dan icon library profesional (Lucide) tanpa emoji teks.

---

## 2. Target Pengguna (User Personas)
- **Retail Trader (Forex / Crypto / Stock):** Trader mandiri yang butuh mencatat trade harian dan mengevaluasi strategi mingguan/bulanan.
- **Prop Firm / Funded Trader:** Trader yang membutuhkan pemantauan ketat terhadap *Daily Drawdown*, *Max Drawdown*, dan *Risk Management Rules*.
- **Part-Time Trader / Swing Trader:** Trader dengan volume sedang yang fokus pada analisis setup dan rasio *Risk:Reward (RRR)*.

---

## 3. Fitur Utama (Core Features)

### 3.1. Autentikasi & Akses Pertama (Authentication Flow)
- **Default Entry Redirect:** Pengguna baru atau yang belum login saat membuka root URL (`/`) akan langsung dialihkan ke `/login`.
- **Halaman Login & Register Minimalis:**
  - Tampilan modern fintech dengan dukungan penuh Dark & Light mode.
  - Input Email & Password dengan visual focus ring presisi.
  - Opsi *Remember Me* untuk sesi tersimpan.
  - *1-Click Demo Login:* Tombol login instan untuk akun demo testing (`trader@example.com`).
  - Error validation otomatis terintegrasi dengan toast / modal SweetAlert2.
- **Session Management & Logout:** Fitur logout aman dengan dialog konfirmasi SweetAlert2.

### 3.2. Left Sidebar Navigation Architecture
- **Sidebar Kiri Tetap (Persistent Desktop Sidebar):**
  - **Header Sidebar:** Logo AlphaJournal Pro Terminal + Status Indikator WebSocket Live.
  - **Account Quick Switcher:** Dropdown pemilihan akun trading langsung di sidebar untuk navigasi cepat.
  - **Menu Navigasi Utama:**
    - `Dashboard` (Icon: `lucide-bar-chart-3`)
    - `Trade Journal` (Icon: `lucide-book-open`)
    - `Analytics & Psychology` (Icon: `lucide-target`)
    - `Trading Accounts` (Icon: `lucide-wallet`)
  - **Primary CTA:** Tombol `+ Log New Trade` yang mencolok dan mudah diakses.
  - **Footer Sidebar:**
    - Profile trader (Nama, email, avatar badge).
    - Theme Switcher toggle (Sun/Moon).
    - Tombol Logout dengan konfirmasi SweetAlert2.
- **Mobile Responsive Drawer:** Sidebar dapat dibuka/tutup melalui tombol hamburger menu pada layar kecil dengan backdrop blur overlay.

### 3.3. Dashboard & Monitoring Real-Time (WebSocket Enabled)
- **Live KPI Summary Cards:**
  - Total Net P&L ($ dan %)
  - Win Rate (% Menang vs Kalah vs Break-even)
  - Profit Factor (Gross Profit / Gross Loss)
  - Total Trades & Average Trade Duration
  - Current Drawdown & Max Drawdown
  - Average Risk:Reward Ratio (Actual vs Planned)
- **Interactive Visualizations:**
  - **Equity Curve & Growth Chart:** Grafik akumulasi saldo dan pertumbuhan modal dari waktu ke waktu (ApexCharts).
  - **Daily P&L Heatmap / Calendar View:** Tampilan kalender dengan warna hijau (profit) / merah (loss) beserta nominal harian.
  - **Strategy & Pair Breakdown:** Diagram perbandingan performa per pair/instrumen dan per setup strategi.

### 3.4. Trade Journaling (Pencatatan Transaksi)
- **Input Data Trade:**
  - Akun trading, simbol/pair, arah (long/short), status (open/closed).
  - Entry & Exit prices, quantity/lots, stop loss, take profit, fees.
  - Kalkulasi otomatis: Gross/Net PnL, ROI %, Planned RR, Actual RR.
  - Tagging strategi, sesi market, emosi, dan upload screenshot Before/After chart.

### 3.5. Multi-Account Management
- Mengelola akun terpisah (Personal Crypto, Prop Firm FTMO, Forex Broker).
- Filter dashboard per akun atau tampilan gabungan (*Consolidated View*).

### 3.6. Real-Time WebSocket Infrastructure (Laravel Reverb)
- Pembaruan instan saat trade dibuat/diedit via Private Channel user (`private-user.{id}`).

---

## 4. UI/UX & Design Guidelines (Fintech Minimalist)
- **Layout Struktur:** Layout Sidebar Kiri (Left Sidebar) + Top Header Sub-Bar + Main Content.
- **Strictly No Emojis:** Wajib menggunakan icon library profesional (**Lucide Icons / SVG**).
- **Dual-Theme Support (Dark & Light Mode):** Dark mode default dengan mode Light yang bersih & kontras tinggi, dilengkapi toggle switch instan (`lucide-sun` / `lucide-moon`) dan anti-FOUC script.
- **Semantic Financial Colors:** Emerald Green (`#10B981` / `#059669`) untuk profit/long, Rose/Crimson (`#F43F5E` / `#E11D48`) untuk loss/short, Muted Slate untuk teks dan background sekunder.
- **Tabular Font Typography:** Menggunakan font monospaced (`font-mono`) untuk seluruh angka moneter, persentase, dan harga agar rata presisi.
- **Alert & Confirmation Dialogs:** Menggunakan **SweetAlert2** adaptif.

---

## 5. Roadmap Pengembangan
1. **Fase 1: Authentication & Auth Controller**
   - Auth routes (`/login`, `/register`, `/logout`).
   - `AuthController.php` dengan validasi, session management, dan demo 1-click login.
   - Blade views untuk Login dan Register dengan styling fintech dual-theme.
   - Middleware `auth` pada semua route aplikasi (`/dashboard`, `/trades`, `/accounts`, `/analytics`).
2. **Fase 2: Left Sidebar Navigation Layout Refactor**
   - Refactor layout [`resources/views/layouts/app.blade.php`](file:///Users/adybudi/Project/project-tools/trading-dashboard/resources/views/layouts/app.blade.php) menjadi Left Sidebar Layout.
   - Implementasi drawer responsif untuk mobile dan sticky sidebar untuk desktop.
   - Integrasi profil user, theme toggle, dan logout SweetAlert2 di sidebar footer.
3. **Fase 3: Verification & Automated Tests**
   - Unit & feature tests untuk autentikasi dan redirect `/` -> `/login`.
   - Uji verifikasi visual untuk tema Light dan Dark di halaman login dan sidebar.
