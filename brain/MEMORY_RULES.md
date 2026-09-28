# MEMORY RULES & DOCUMENTATION ARCHITECTURE GUIDELINES

> **Aturan Wajib Struktur Dokumentasi & Standar UI Proyek AlphaJournal Trading Dashboard**

---

## 1. Folder `brain/` (`/Users/adybudi/Project/project-tools/trading-dashboard/brain/`)
Folder ini dikhususkan secara eksklusif untuk **Logika Sistem, Algoritma Trading, Spesifikasi Matematika, dan PRD Engine**:
- **Logika Strategi Trading:** Algoritma Inversion Fair Value Gap (iFVG), Liquidity Sweep (SSL Grab), PADAWAE 3-Rejection Formula, Optimal Trade Entry (OTE 61.8% – 78.6%), Dealing Range Fibonacci.
- **Spesifikasi Engine:** Algoritma kalkulasi Win Rate, Profit Factor, Max Drawdown, Risk-to-Reward Ratio (RRR), dynamic position sizing, Autonomous Spot Learner.
- **PRD Teknis Engine:** PRD Coin Hunter Engine, PRD Simulasi Trade & Strategy Backtester, Master QA Testing Matrix.

---

## 2. Folder `plan_architecture/` (`/Users/adybudi/Project/project-tools/trading-dashboard/plan_architecture/`)
Folder ini dikhususkan secara eksklusif untuk **Rencana Arsitektur, Rencana Perubahan Sistem, Desain Tampilan/UI/UX, dan Roadmap Fitur**:
- **Rencana Arsitektur & Refactoring:** Transisi arsitektur (misal: Live In-Memory Stream Engine, daemon screening, auto-learn).
- **Rencana UI/UX:** Rencana layout antarmuka, komponen Tailwind/Alpine.js, modal split view, kurva grafik ApexCharts, skema warna dark theme.
- **Proposal Perubahan:** Dokumen rencana sebelum eksekusi fitur baru.

---

## 3. Standar UI & Desain Antarmuka (Wajib Ditaati)
1. **Dilarang Menggunakan Emoticon (Emoji Unicode):**
   - Jangan gunakan emoji seperti `💎`, `⚡`, `📈`, `📉`, `💵`, `🇮🇩`, `🚀`, `🎯`, dll pada antarmuka/UI.
   - **Wajib Gunakan Library Icon Komponen:** Gunakan `<x-app-icon name="..." class="..." />` (SVG Feather/Lucide icons) yang bersih dan konsisten.
2. **Standar Kurs Konversi Rupiah (IDR):**
   - Gunakan kurs resmi **1 USD = Rp 17.756,00**.
   - Sediakan toggle mata uang USD ($) vs Rupiah (Rp) yang tersimpan secara *persistent* di `localStorage`.

---

## 4. Komitmen AI Agent
1. Setiap kali menyusun **Rencana Perubahan / UI / Arsitektur**, file markdown **WAJIB** disimpan ke dalam `plan_architecture/`.
2. Setiap kali menyusun **Logika Trading / Rumus / Spesifikasi Engine**, file markdown **WAJIB** disimpan ke dalam `brain/`.
3. Semua elemen visual tombol, badge, dan kartu **WAJIB** menggunakan `<x-app-icon>` tanpa emoji unicode.
