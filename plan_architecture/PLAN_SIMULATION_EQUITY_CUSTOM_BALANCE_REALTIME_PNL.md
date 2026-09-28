# Plan Architecture: Simulasi Trade & Coin Hunter Interactive Equity, Custom Balance & Real-Time PnL Dashboard

**Author**: AlphaJournal System Architect  
**Status**: PROPOSED PLAN (Ready for Approval & Execution)  
**Location**: `plan_architecture/PLAN_SIMULATION_EQUITY_CUSTOM_BALANCE_REALTIME_PNL.md`

---

## 1. Latar Belakang & Kebutuhan Pengguna

Pengguna meminta penyempurnaan menyeluruh pada ekosistem **Simulasi Trade & Paper Trading Virtual**:
1. **Coin Hunter (Input Equity Simulasi Fleksibel):**
   - Saat klik tombol **"Simulasi"** pada kartu sinyal, pengguna dapat menentukan modal alokasi equity (misal: `$100`, `$250`, `$500`, `$1,000`, `$5,000` atau nominal kustom), tidak langsung dipatok kaku `$1,000`.
2. **Dashboard PnL Komprehensif di Menu Simulasi:**
   - Menampilkan ringkasan metrik PnL lengkap: Total Equity, Realized PnL, Floating Unrealized PnL, Win Rate, Win/Loss Count, dan Risk:Reward terakumulasi.
3. **Kustomisasi Saldo Awal Akun Simulasi:**
   - Pengguna dapat membuka atau mereset akun simulasi dengan modal awal yang bebas ditentukan (misal start dari `$500`, `$10,000`, `$50,000`, dll.).
4. **Real-Time Live PnL Stream pada Paper Trading:**
   - Perubahan floating PnL (profit/loss) pada posisi terbuka bergerak dan berkedip secara live (real-time 3-4 detik) mengikuti pergerakan harga pasar dunia tanpa perlu reload halaman.

---

## 2. Diagram Alur Interaksi & Arsitektur

```mermaid
flowchart TD
    subgraph Coin Hunter (Sinyal Koin)
        A["Klik Tombol 'Simulasi' pada Kartu Sinyal"] --> B["Buka Modal Interaktif 'Buka Posisi Simulasi'"]
        B --> C["Input Equity ($100, $250, $500, $1K, Kustom)"]
        C --> D["Kalkulasi Otomatis: Jumlah Koin, Proyeksi TP (+$$), Risiko SL (-$$)"]
        D -->|Submit POST /simulation/paper/buy| E["Posisi Simulasi Terbuka di Paper Trading"]
    end

    subgraph Simulasi Trade & Paper Trading
        E --> F["Dashboard Live PnL & Virtual Equity"]
        F --> G["Tab Paper Trading: Posisi Terbuka"]
        
        subgraph Real-Time Poller (Setiap 3 Detik)
            H["GET /simulation/live-status"] --> I["Update Harga Terkini & Kedip Hijau/Merah"]
            I --> J["Update Floating PnL ($ dan %) Dinamis"]
            I --> K["Auto-Finalize jika Menyentuh TP1 / TP2 / SL"]
        end
        
        L["Tombol 'Atur Saldo Awal'"] --> M["Modal Set Saldo ($500, $1K, $10K, $50K, dll.)"]
        M -->|POST /simulation/reset?initial_balance=X| N["Akun Ter-reset dengan Saldo Kustom"]
    end
```

---

## 3. Rincian Komponen Teknis yang Akan Dibangun

### A. Coin Hunter: Modal Interaktif Alokasi Equity
- **Lokasi:** `resources/views/signals/index.blade.php`
- **Fitur:**
  - Saat tombol **"Simulasi"** diklik, memunculkan modal Alpine.js dengan rincian sinyal: Koin, Grade, Harga Entry, TP1, TP2, SL.
  - Pilihan cepat preset chip modal: `$100`, `$250`, `$500`, `$1,000`, `$2,500`, `$5,000` + input kustom.
  - Kalkulator real-time:
    - $\text{Estimasi Koin} = \frac{\text{Modal}}{\text{Entry Price}}$
    - $\text{Potensi Profit TP2} = \text{Modal} \times \left(\frac{\text{TargetMax}}{100}\right)$
    - $\text{Batas Risiko SL} = \text{Modal} \times \left(\frac{\text{RiskPct}}{100}\right)$

### B. Dashboard PnL Komprehensif di Menu Simulasi
- **Lokasi:** `resources/views/simulation/index.blade.php`
- **Metrik Utama yang Ditampilkan:**
  1. **Total Virtual Equity:** $\text{Saldo Kas} + \text{Floating Unrealized PnL}$.
  2. **Saldo Realized (Kas Virtual):** Saldo kas setelah posisi ditutup.
  3. **Floating Unrealized PnL:** Laba/rugi berjalan dari posisi aktif (dengan warna Emerald/Rose dinamis).
  4. **Win Rate (%):** Persentase kemenangan paper trade.
  5. **Total Posisi:** Total posisi selesai (Menang vs Kalah) & Posisi aktif.

### C. Kustomisasi Saldo Awal Akun Simulasi
- **Backend:** Update `SimulationController::resetAccount` & `PaperTradingService::resetAccount` untuk menerima parameter `initial_balance` (default `$10,000.00`, minimum `$100.00`, maksimum `$1,000,000.00`).
- **Frontend UI:** Tombol **"⚙️ Atur Saldo Awal"** dengan modal pilihan preset (`$500`, `$1,000`, `$5,000`, `$10,000`, `$50,000`, `$100,000`) dan input manual.

### D. Real-Time PnL Stream pada Paper Trading
- **Backend:** Update endpoint `/simulation/live-status` untuk mengembalikan:
  - Harga terkini live dari seluruh posisi terbuka.
  - Floating PnL ($ dan %) per posisi.
  - Total floating unrealized PnL akun.
  - Evaluasi otomatis penutupan posisi (*Auto-Hit TP1, TP2, SL*).
- **Frontend:** Polling Alpine.js setiap 3 detik dengan animasi kedip hijau/merah (*price flash*) pada tabel posisi terbuka dan kartu ringkasan PnL.

---

## 4. Rencana File & Modifikasi

1. **`app/Services/PaperTradingService.php`**:
   - Update `resetAccount(User $user, float $initialBalance = 10000.00)`.
   - Pastikan alokasi equity fleksibel diterima tanpa batasan statis.
2. **`app/Http/Controllers/SimulationController.php`**:
   - Update `resetAccount(Request $request)` untuk memproses `initial_balance`.
   - Update `livePaperStatus()` untuk menyertakan kalkulasi total floating unrealized PnL.
3. **`resources/views/signals/index.blade.php`**:
   - Tambahkan Modal Alokasi Equity Simulasi interaktif.
4. **`resources/views/simulation/index.blade.php`**:
   - Bangun Dashboard PnL Komprehensif di bagian atas.
   - Tambahkan Modal Kustomisasi Saldo Awal.
   - Tambahkan real-time polling Alpine.js dengan efek kedip harga dan PnL dinamis.
5. **Testing & QA**:
   - Unit test di `tests/Unit/PaperTradingServiceTest.php` & Feature test di `tests/Feature/SimulationTest.php`.
