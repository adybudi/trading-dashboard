# PLAN ARCHITECTURE DIRECTORY

Direktori ini berisi seluruh dokumen **Rencana Arsitektur**, **Perubahan Sistem**, dan **Rencana Tampilan / UI / UX** untuk proyek AlphaJournal Trading Dashboard.

---

## Daftar Dokumen Perencanaan:
- [`PLAN_REALTIME_INMEMORY_STREAM.md`](./PLAN_REALTIME_INMEMORY_STREAM.md): Rencana arsitektur transisi sistem sinyal Coin Hunter dari Static Database Persistence menjadi Pure Real-Time In-Memory Stream untuk mengeliminasi data nyangkut dan menjamin akurasi 100% harga real-time.
- [`PLAN_FUTURES_MODE_BACKTEST_VALIDASI_MASSAL.md`](./PLAN_FUTURES_MODE_BACKTEST_VALIDASI_MASSAL.md): Rencana menambahkan pilihan Mode Trading (Spot/Futures + Leverage) ke fitur Backtest & Validasi Massal (Layer 10 QA), yang saat ini murni spot/long-only. Status: **BELUM DIKERJAKAN**, ditahan untuk referensi implementasi mendatang.
- [`PLAN_MAX_HOLDING_PERIOD_VALIDASI_MASSAL.md`](./PLAN_MAX_HOLDING_PERIOD_VALIDASI_MASSAL.md): Rencana menambahkan opsi "Rentang Waktu Maksimal Holding" (Scalping/Intraday/Swing/Custom) ke Validasi Massal & Backtest, untuk mengatasi artifact "trade dipaksa tutup karena data candle habis" yang membuat Win Rate/Profit Factor agregat bias optimis. Status: **BELUM DIKERJAKAN**, rancangan untuk didiskusikan dulu.

---

> **Aturan**: Untuk dokumen logika strategi dan algoritma trading matematika, silakan rujuk ke folder [`../brain/`](../brain/).
