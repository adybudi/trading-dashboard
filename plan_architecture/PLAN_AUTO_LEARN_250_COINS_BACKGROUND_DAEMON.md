# Plan Architecture: Auto-Learn 250 Coins Background Daemon & Persistent High-Accuracy Repository

**Author**: AlphaJournal System Architect  
**Status**: PROPOSED PLAN (Ready for Approval & Execution)  
**Location**: `plan_architecture/PLAN_AUTO_LEARN_250_COINS_BACKGROUND_DAEMON.md`

---

## 1. Latar Belakang & Tujuan
Pengguna menginginkan agar **Autonomous Spot Strategy Learner** dapat langsung memindai (*screening*) **250 koin spot teratas** secara otomatis ketika perintah `php artisan start` dijalankan. Sistem akan mengevaluasi seluruh koin di latar belakang (*background daemon*), menguji konsistensi profit berulang (**Repeatable Profit $\ge 80\% - 100\%$**), dan **mencatat (*persist*) strategi serta titik entri yang terbukti valid** ke dalam database agar siap dieksekusi secara instan.

---

## 2. Diagram Alur Sistem (System Architecture Flow)

```mermaid
flowchart TD
    A["php artisan start"] --> B["Start Local Web Server (:8001)"]
    A --> C["Spawn Background Autonomous Learner Daemon"]
    
    subgraph Background Autonomous Learner (Non-Blocking)
        C --> D["Fetch Top 250 Liquid Spot Coins (Gate.io Stream)"]
        D --> E["Batch Chunk Processor (10-25 Coins / Cycle)"]
        E --> F["Autonomous Replay & Multi-Cycle Profit Validator"]
        F --> G{"Lolos Uji Profit Berulang? (Win Rate >= 80% & Win Streak >= 3)"}
        G -->|Ya (Valid)| H["Simpan ke Tabel validated_spot_strategies"]
        G -->|Belum Cukup| I["Tandai sebagai Probation / Watchlist"]
    end
    
    subgraph User Web Interface (Coin Hunter & Simulasi)
        H --> J["Tab/Filter: 🔥 Golden Valid (Auto-Learned)"]
        J --> K["1-Click Jurnal / 1-Click Simulasi Paper Trade"]
    end
```

---

## 3. Komponen Teknis yang Akan Dibangun

### A. Tabel Database & Model `ValidatedSpotStrategy`
Tabel untuk mencatat rekam jejak strategi yang telah terbukti menghasilkan profit berulang:
- `symbol` (e.g. `BTCUSDT`, `SOLUSDT`, `PEPEUSDT`)
- `timeframe` (`15m`, `1h`, `4h`, `1d`)
- `calibrated_win_rate` (e.g. `87.5%`)
- `profit_consistency_score` (e.g. `92.0%`)
- `max_win_streak` (e.g. `4x menang beruntun`)
- `calibrated_weights` (JSON bobot `ifvg, ssl, ote, padawae, rvol, htf`)
- `golden_entry_price`, `take_profit_1`, `take_profit_2`, `stop_loss`
- `repeatability_status` (`VALIDATED_REPEATABLE`, `ELITE_GOLDEN`)
- `last_calibrated_at`

### B. Background Daemon di `php artisan start`
- Perintah `php artisan start` diperbarui untuk meluncurkan proses background worker `php artisan spot:auto-learn` secara *non-blocking*.
- Worker memindai 250 koin dalam *batch chunk* (misal 10 koin per batch dengan jeda 2 detik) agar tidak membebani CPU / I/O jaringan dan menghindari *rate limit*.

### C. UI Badge & Tab di Coin Hunter
- Menampilkan tab/filter **"🔥 Strategi Emas Valid (Auto-Learned Repeatable Profit)"** di menu Coin Hunter, sehingga trader bisa langsung melihat koin mana saja dari 250 koin yang saat ini memiliki akurasi validitas $80\% - 100\%$.

---

## 4. Rencana Tahapan Eksekusi

1. **Database Migration:** Buat migration & model `ValidatedSpotStrategy`.
2. **Service Enhancement:** Tambahkan method `screenAndPersistTop250Coins()` pada `AutonomousSpotLearnerService`.
3. **Console Command Daemon:** Bangun `RunAutonomousLearnerDaemon` (`spot:auto-learn`) dan integrasikan ke `php artisan start`.
4. **UI Integration:** Tambahkan tab/filter visual di `resources/views/signals/index.blade.php`.
5. **Testing & QA:** Buat unit/feature test untuk memvalidasi screening 250 koin dan penyimpanan strategi valid.
