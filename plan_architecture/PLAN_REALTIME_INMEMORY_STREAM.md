# Architecture Plan: Pure Real-Time In-Memory Signal Stream Engine

**Author**: Antigravity AI Engine Architect  
**Status**: APPROVED & IMPLEMENTING  
**Module**: Coin Hunter (Spot Discount Scanner) & Simulasi Trade  
**Version**: 2.0 (Pure Real-Time Ephemeral Stream)

---

## 1. Executive Summary & Root Cause Analysis

### Masalah Sebelumnya (Static DB Persistence Flaw):
Pada versi 1.x, hasil scan sinyal disimpan permanen ke dalam tabel database MySQL `spot_signals`. Ketika data koin atau harga pasar dunia berubah cepat, database tetap menyimpan nilai lama (`entry_price = $52.83` untuk BNB). Ketika antarmuka memadukan data live ticker (`$705.50`) di atas data DB statis lama, terjadi anomali harga nyangkut dan kalkulasi floating gain yang tidak akurat (+1235%).

### Solusi Baru (Pure Real-Time In-Memory Stream Architecture):
Mengeliminasi ketergantungan penyimpanan sinyal statis pada database `spot_signals`. Seluruh sinyal **Sinyal Titik Beli Diskon Terdeteksi (Grade A+ & A)** dihitung secara *murni di memori (In-Memory Engine)* langsung dari data *real-time OHLCV candlestick* dan *live ticker stream*.

---

## 2. Diagram Alur Data Arsitektur Baru

```mermaid
flowchart TD
    A["Gate.io Live Spot Tickers (2,200+ Pairs)"] -->|Filter Stablecoins & Leveraged ETFs| B["Top Liquid Spot Coins In-Memory"]
    B --> C["SpotSignalEngineService (iFVG + PADAWAE + OTE 61.8%)"]
    C --> D["Active Real-Time Signals (In-Memory Stream)"]
    D --> E["Live UI View (Alpine.js Real-Time Reactive State)"]
    
    subgraph Client-Side Polling (Setiap 4 Detik)
        F["/signals/live-prices"] -->|Live Price Stream| E
        E -->|Animasi Kedip Hijau / Merah| E
        E -->|Real-Time Floating Gain & Target Update| E
    end
    
    subgraph Database Persistence (Hanya Saat Aksi Nyata)
        E -->|Klik Jurnal| G["POST /signals/journal-instant -> trades DB Table"]
        E -->|Klik Simulasi| H["POST /simulation/paper/buy -> simulated_trades DB Table"]
    end
```

---

## 3. Komponen Strategi Teknis (Sesuai Brain Engine v1 & Panduan PDF)

Seluruh logika strategi dari `brain/engine_v1/PRD_SIGNAL_ENGINE.md`, `IFVG RATING GUIDE.pdf`, dan `STRATEGI Scalping 1.pdf` tetap beroperasi 100% di memori:

1. **Dealing Range & Fibonacci Equilibrium / OTE (61.8% – 78.6%):**
   - Mengidentifikasi Swing High dan Swing Low 35 periode.
   - Sinyal beli terkonfirmasi jika harga berada di bawah Equilibrium (50% Dealing Range) atau masuk area OTE (61.8%).
2. **Inversion Fair Value Gap (iFVG):**
   - Mendeteksi Bearish FVG yang berhasil ditembus ke atas (*flipped*) dan kini bertindak sebagai support kuat.
3. **Liquidity Sweep (Sell-Side Liquidity / SSL):**
   - Mendeteksi *fakeout sweep* di bawah low sebelumnya untuk membersihkan likuiditas sebelum pantulan naik.
4. **PADAWAE Formula (3 Rejections & Breakout Retest):**
   - Mendeteksi minimal 2-3 penolakan garis tren turun dan breakout konfirmasi.
5. **Skor Konfluensi & Penentuan Grade:**
   - Skor 90–100: **Grade A+** (Prioritas Utama).
   - Skor 80–89: **Grade A** (Setup Sangat Baik).
   - Skor 65–79: **Grade A-** (Setup Cukup).
   - Skor < 65: **Grade B** (Watchlist / Tunggu Diskon).

---

## 4. Keunggulan Arsitektur Baru

| Aspek | Arsitektur Lama (DB Persistence) | Arsitektur Baru (Pure In-Memory Stream) |
| :--- | :--- | :--- |
| **Penyimpanan Sinyal** | Tersimpan di tabel `spot_signals` | Dihitung live di RAM / Cache 15-30s |
| **Ketepatan Harga Entry** | Berisiko nyangkut jika data lama | **100% selalu harga live saat ini** |
| **Sinkronisasi Chart** | Bisa berbeda dengan chart jika data DB usang | **100% identik dan presisi dengan candlestick chart** |
| **Token Derivatif** | Token ETF (BNB5L/BNB5S) sempat masuk | **Token ETF 100% difilter bersih** |
| **Pencatatan Transaksi** | Mengacu pada ID sinyal di DB | **Langsung mencatat harga live ke jurnal/simulasi** |

---

## 5. Implementasi & Rencana File

1. **`app/Services/MarketDataFetcherService.php`**:
   - Filter ketat token leveraged ETF `(3L|3S|5L|5S|2L|2S|BEAR|BULL|DOWN|UP)$`.
   - Pemformatan harga dinamis hingga 8 digit desimal untuk koin mikro (PEPE, SHIB, BONK).
2. **`app/Services/SpotSignalEngineService.php`**:
   - Menghitung sinyal in-memory secara efisien dan konsisten.
3. **`app/Http/Controllers/SpotSignalController.php`**:
   - Menyediakan sinyal real-time langsung ke Blade/JSON tanpa query `spot_signals` statis.
   - Endpoint `POST /signals/journal-instant` untuk mencatat transaksi live langsung dari kartu sinyal.
4. **`resources/views/signals/index.blade.php`**:
   - Alpine.js menerima stream sinyal live dan mengupdate harga, kedip hijau/merah, serta progress target setiap 4 detik.
