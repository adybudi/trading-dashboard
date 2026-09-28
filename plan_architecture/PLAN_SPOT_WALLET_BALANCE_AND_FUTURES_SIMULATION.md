# 📐 Arsitektur Mekanisme Dompet Spot (Free Cash vs Locked Margin) & Mode Trading Futures (Leverage)

**Tanggal:** 27 Agustus 2026  
**Status:** Approved & Ready for Execution  
**Dokumen Referensi:** `brain/MEMORY_RULES.md`, `brain/engine_v1/SPOT_SIGNAL_ENGINE_PRD.md`

---

## 1. Latar Belakang & Masalah

### 1.1. Perhitungan Saldo Dompet Spot (Kas Bebas vs Nilai Koin Terbuka)
Pada perdagangan **Spot**, saat pengguna memiliki saldo akun awal **$100.00** dan membuka posisi beli koin (misal XRP) sebesar **$50.00**:
- **Kas Bebas (Available / Free Cash)** harus terpotong menjadi **$50.00** (karena $50 telah dibelikan aset XRP).
- **Modal Terkunci di Posisi (Locked in Spot Coins)**: **$50.00**.
- **Nilai Koin Terkini (Position Market Value)**: `$50.00 + Floating PnL` (misal pergerakan pasar -$0.01 $\rightarrow$ **$49.99**).
- **Total Virtual Equity (Total Nilai Portofolio)**: `Kas Bebas ($50.00) + Nilai Koin ($49.99) = $99.99`.
- Ketika posisi ditutup secara manual, modal pokok + realisasi laba/rugi langsung dikembalikan ke **Saldo Kas Bebas**.

### 1.2. Dukungan Mode Trading: SPOT (Default) vs FUTURES (Leverage)
Pengguna menginginkan default tetap **Spot Trading** (aman tanpa risiko likuidasi), namun menambahkan opsi **Futures Trading** dengan fitur:
- Pemilihan **Leverage**: `1x`, `2x`, `5x`, `10x`, `20x`, `50x`, `100x`.
- Pemilihan Arah: **LONG (Buy)** atau **SHORT (Sell)**.
- Perhitungan **Margin Dibutuhkan**: `Margin = Position Size / Leverage`.
- Perhitungan **Harga Likuidasi (Liquidation Price)**.
- Perhitungan **Floating PnL Futures**: `PnL ($) = Margin * (Price Change % / 100) * Leverage`.

---

## 2. Struktur Database (`simulated_trades`)

Menambahkan kolom-kolom berikut melalui migration baru:
```php
Schema::table('simulated_trades', function (Blueprint $table) {
    $table->string('trading_type', 10)->default('spot')->after('mode'); // 'spot', 'futures'
    $table->string('side', 10)->default('long')->after('trading_type'); // 'long', 'short'
    $table->integer('leverage')->default(1)->after('side'); // 1 (spot), 2..100 (futures)
    $table->decimal('margin_amount', 15, 2)->default(1000.00)->after('allocated_amount');
    $table->decimal('liquidation_price', 18, 8)->nullable()->after('stop_loss');
});
```

---

## 3. Matematika Perhitungan Finansial

### A. Formula Spot Trading (1x Leverage)
$$\text{Margin} = \text{Allocated Amount}$$
$$\text{Position Size} = \text{Margin}$$
$$\text{Quantity} = \frac{\text{Margin}}{\text{Entry Price}}$$
$$\Delta P\% = \frac{\text{Current Price} - \text{Entry Price}}{\text{Entry Price}} \times 100\%$$
$$\text{Unrealized PnL (\textdollar)} = \text{Margin} \times \frac{\Delta P\%}{100}$$
$$\text{Liquidation Price} = \$0.00 \text{ (Tidak ada likuidasi pada Spot)}$$

### B. Formula Futures Trading (Nx Leverage)
$$\text{Position Size (\textdollar)} = \text{Margin} \times \text{Leverage}$$
$$\text{Quantity} = \frac{\text{Position Size}}{\text{Entry Price}}$$

**Untuk Arah LONG:**
$$\Delta P\% = \frac{\text{Current Price} - \text{Entry Price}}{\text{Entry Price}} \times 100\%$$
$$\text{Unrealized PnL (\textdollar)} = \text{Margin} \times \frac{\Delta P\%}{100} \times \text{Leverage}$$
$$\text{Liquidation Price} = \text{Entry Price} \times \left(1 - \frac{1}{\text{Leverage}} + \text{MMR}\right)$$

**Untuk Arah SHORT:**
$$\Delta P\% = \frac{\text{Entry Price} - \text{Current Price}}{\text{Entry Price}} \times 100\%$$
$$\text{Unrealized PnL (\textdollar)} = \text{Margin} \times \frac{\Delta P\%}{100} \times \text{Leverage}$$
$$\text{Liquidation Price} = \text{Entry Price} \times \left(1 + \frac{1}{\text{Leverage}} - \text{MMR}\right)$$

---

## 4. Struktur Ringkasan Saldo Dompet (*Wallet Summary Breakdown*)

| Komponen Metrik | Rumus / Definisi | Contoh Kasus ($100 Awal, Beli $50 Spot) |
|---|---|---|
| **Saldo Akun / Realized Balance** | Saldo kas saat tidak ada posisi terbuka | **$100.00** |
| **Saldo Kas Bebas (Available Cash)** | `virtual_balance - sum(openTrades.margin_amount)` | **$50.00** |
| **Modal Terpakai (Locked in Trades)** | `sum(openTrades.margin_amount)` | **$50.00** |
| **Nilai Koin / Posisi Terkini** | `Locked Margin + sum(Floating PnL)` | **$49.99** (karena floating -$0.01) |
| **Floating Unrealized PnL** | `sum(openTrades.unrealized_pnl_amount)` | **-$0.01** |
| **Total Virtual Equity (Net Worth)** | `Available Cash + Nilai Posisi Terkini` | **$99.99** |

---

## 5. Rencana Tampilan Antarmuka (UI/UX)

### A. Modal Buka Posisi Simulasi (Coin Hunter & Simulasi)
- **Tab Switcher:** `[ SPOT (Default) ]` | `[ FUTURES ]`
- **Pilihan Tipe Order:**
  - **Spot:** Pembelian aset murni, 1x leverage, alokasi kas langsung mengurangi kas bebas dompet.
  - **Futures:** Pilihan `LONG` atau `SHORT`, selector leverage (`1x`, `2x`, `5x`, `10x`, `20x`, `50x`, `100x`), menampilkan *Estimated Liquidation Price* dan *Leveraged ROI Target*.

### B. Dashboard PnL di `simulation/index.blade.php`
- **Card 1: Total Virtual Equity ($)** $\rightarrow$ Total nilai bersih dompet real-time.
- **Card 2: Saldo Kas Bebas ($)** $\rightarrow$ Kas USDT yang tersedia untuk dipakai trading baru.
- **Card 3: Modal Terpakai di Posisi ($)** $\rightarrow$ Total dana yang sedang berjalan di Spot / Margin Futures.
- **Card 4: Floating Unrealized PnL ($ / %)** $\rightarrow$ Laba/rugi berjalan live dengan indikator kedip real-time.
- **Card 5: Win Rate & Rekor Simulasi** $\rightarrow$ Rekor kemenangan & tombol kustomisasi modal awal.

### C. Tabel Posisi Terbuka
- Kolom Tipe: Badge `SPOT` (Biru) atau `FUTURES 10x` (Ungu) & `LONG` / `SHORT`.
- Kolom Modal / Margin: Margin yang dialokasikan.
- Kolom Harga Live & Floating PnL: Diperbarui real-time setiap 3 detik.
- Kolom Likuidasi: Menampilkan `-` untuk Spot, dan angka harga likuidasi untuk Futures.
- Kolom Aksi: Tombol **"Tutup Posisi"** manual.
