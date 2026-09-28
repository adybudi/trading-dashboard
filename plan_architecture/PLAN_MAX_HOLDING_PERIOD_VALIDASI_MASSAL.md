# Plan Architecture: Maksimal Holding Period (Rentang Waktu) untuk Validasi Massal & Backtest

**Author**: AlphaJournal System Architect
**Status**: PROPOSED PLAN (Belum Dikerjakan — Rancangan Dahulu)
**Location**: `plan_architecture/PLAN_MAX_HOLDING_PERIOD_VALIDASI_MASSAL.md`

---

## 1. Latar Belakang & Masalah yang Diselesaikan

Dari diskusi debugging sebelumnya, ditemukan bahwa mesin Backtest/Validasi Massal saat ini **tidak punya batas waktu menahan posisi**. Forward-scan loop di `BacktestEngineService::simulate()` akan terus mengecek candle demi candle sampai salah satu dari 3 kondisi ini terjadi:

1. Harga sentuh Stop Loss
2. Harga sentuh Take Profit 1/2
3. **Data candle di array habis** (tidak ada elemen tersisa untuk dicek)

Kondisi #3 ini adalah **artifact keterbatasan data**, bukan representasi disiplin trading pengguna. Akibatnya:
- Sinyal yang trigger dekat ujung data yang di-fetch bisa "dipaksa tutup" dalam hitungan candle, menghasilkan angka net profit kecil (6%, 7%, 8.67%) yang tidak mencerminkan apakah strategi sebenarnya akan mencapai target 10-20% jika dikasih waktu lebih.
- Win Rate & Profit Factor agregat jadi bias optimis, karena trade yang "kepepet ditutup untung tipis karena kehabisan data" tetap terhitung "win" — padahal itu bukan kemenangan strategi yang sesungguhnya.
- Hasil backtest **tidak merepresentasikan gaya trading pengguna sungguhan** — pengguna secara manual menahan posisi maksimal beberapa hari (sesuai gaya intraday/swing/scalping) lalu tutup manual kalau target belum tercapai; mesin backtest saat ini tidak meniru perilaku ini sama sekali.

Tujuan plan ini: menambahkan opsi **"Rentang Waktu Maksimal Holding"** di form Validasi Massal (dan idealnya juga Backtest single-coin), supaya user bisa menentukan "kalau dalam X hari/jam/menit belum kena TP/SL, tutup paksa di situ" — persis meniru disiplin exit manual mereka.

---

## 2. Desain UI yang Diusulkan

### A. Checkbox Aktivasi

```
☐ Gunakan Rentang Waktu Maksimal Holding
```

Kalau **tidak dicentang** (default): perilaku backtest **tetap seperti sekarang** (scan sampai TP/SL/data habis) — supaya tidak mengubah behavior existing users secara tiba-tiba.

Kalau **dicentang**: muncul opsi tambahan di bawahnya.

### B. Pilihan Gaya Trading (Select Option)

| Gaya Trading | Rentang Waktu Default |
|---|---|
| **Scalping** | 5 menit – 1 jam |
| **Intraday** | 3 – 7 hari |
| **Swing** | 20 – 30 hari |
| **Custom** | Bebas ditentukan (Hari / Jam / Menit) |

Untuk 3 preset pertama (Scalping/Intraday/Swing), tampilkan slider atau input angka **dalam rentang tersebut** dengan nilai default di tengah rentang (misal Intraday default 5 hari, bisa digeser 3-7). Ini memberi fleksibilitas tanpa harus pilih "Custom" untuk penyesuaian kecil.

Untuk **Custom**, tampilkan 3 input terpisah: `[ Hari ] [ Jam ] [ Menit ]` — dijumlahkan jadi total durasi maksimal holding.

### C. Contoh Tampilan (Mockup Teks)

```
☑ Gunakan Rentang Waktu Maksimal Holding

  Gaya Trading:  [ Intraday ▾ ]
  Durasi Maks:   [====●=====] 5 hari (rentang 3-7 hari)

  — atau jika pilih Custom —
  Durasi Maks:   [ 3 ] Hari  [ 12 ] Jam  [ 0 ] Menit
```

---

## 3. Logika Teknis yang Diperlukan

### A. Konversi Durasi ke Jumlah Candle

Durasi yang dipilih (hari/jam/menit) perlu dikonversi ke **jumlah candle** berdasarkan timeframe yang sedang dipakai:

| Timeframe | Menit per Candle | Contoh: 3 Hari = Berapa Candle? |
|---|---|---|
| 15m | 15 | 3 hari × 24 jam × 4 candle/jam = 288 candle |
| 1h | 60 | 3 hari × 24 candle/hari = 72 candle |
| 4h | 240 | 3 hari × 6 candle/hari = 18 candle |
| 1d | 1440 | 3 hari = 3 candle |

Formula umum: `maxHoldCandles = floor(total_menit_durasi / menit_per_candle_timeframe)`

### B. Perubahan pada Forward-Scan Loop (`BacktestEngineService::simulate()`)

**Sebelum** (saat ini):
```php
for ($j = $i + 1; $j < $totalCandles; $j++) {
    // cek SL, TP2, TP1 ...
}
```

**Sesudah** (dengan max holding):
```php
$scanLimit = $maxHoldCandles !== null
    ? min($totalCandles, $i + 1 + $maxHoldCandles)
    : $totalCandles;

for ($j = $i + 1; $j < $scanLimit; $j++) {
    // cek SL, TP2, TP1 ...
}

// Kalau loop selesai tanpa outcome DAN itu karena max-hold tercapai
// (bukan karena data habis), tandai outcome secara eksplisit:
if (!$outcome && $maxHoldCandles !== null && $scanLimit < $totalCandles) {
    $outcome = ($rawExitPrice >= $rawEntryPrice) ? 'closed_max_hold_win' : 'closed_max_hold_loss';
}
```

### C. Kategori Outcome Baru — Transparansi Hasil

Tambahkan label outcome baru yang **terpisah** dari `win_tp1`/`win_tp2`/`loss_sl` yang sudah ada:
- `closed_max_hold_win` — ditutup paksa karena batas waktu, kebetulan harga di atas entry
- `closed_max_hold_loss` — ditutup paksa karena batas waktu, kebetulan harga di bawah entry

Ini **beda** dari kasus "data historis benar-benar habis" (`ran_out_of_data`, kasus lama) — supaya user bisa lihat statistik terpisah: "berapa % trade benar-benar kena TP/SL vs berapa % ditutup karena waktu habis vs berapa % kena limitasi data mentah".

### D. Kecukupan Data yang Di-fetch

Supaya max-holding period benar-benar teruji (bukan malah balik ke masalah lama "data habis duluan"), jumlah candle yang di-fetch (`candle_count`) perlu otomatis disesuaikan minimal:

```
candle_count_minimum = warmup_period (25) + maxHoldCandles + buffer_pengaman
```

Kalau user pilih preset Swing (30 hari) di TF 15m, itu butuh 30×96=2880 candle hanya untuk holding period-nya — perlu dicek apakah ini masih dalam batas wajar API Gate.io (limit per-request) dan tidak membuat request jadi lambat.

---

## 4. Statistik Tambahan di Hasil Validasi Massal

| Metrik Baru | Kegunaan |
|---|---|
| `closed_by_target` (jumlah & %) | Trade yang benar-benar kena TP1/TP2/SL — ini yang paling bisa dipercaya |
| `closed_by_max_hold` (jumlah & %) | Trade yang ditutup paksa karena waktu habis — sinyal "belum sempat capai target" |
| `win_rate_closed_by_target_only` | Win rate **HANYA** dari trade yang benar-benar kena TP/SL, tanpa campur tangan trade timeout — ini angka paling jujur untuk menilai kekuatan strategi |

---

## 5. Checklist Implementasi (Kalau Nanti Dikerjakan)

1. [ ] UI: tambah checkbox "Gunakan Rentang Waktu Maksimal Holding" di panel Validasi Massal
2. [ ] UI: select Gaya Trading (Scalping/Intraday/Swing/Custom) + slider/input durasi
3. [ ] UI: 3 input terpisah (Hari/Jam/Menit) untuk mode Custom
4. [ ] Backend: fungsi konversi durasi → jumlah candle berdasarkan timeframe
5. [ ] `BacktestEngineService::simulate()`: tambah parameter `$maxHoldCandles`, batasi forward-scan loop
6. [ ] Tambah kategori outcome baru (`closed_max_hold_win`/`closed_max_hold_loss`) terpisah dari outcome existing
7. [ ] Sesuaikan `candle_count` yang di-fetch otomatis berdasarkan durasi max-hold yang dipilih (dengan batas wajar)
8. [ ] Tambah statistik baru di hasil: closed_by_target vs closed_by_max_hold, dan win_rate_closed_by_target_only
9. [ ] UI hasil: tampilkan breakdown baru ini di panel Ringkasan PnL / Aggregate KPIs
10. [ ] Terapkan juga ke Backtest single-coin (bukan cuma Validasi Massal) untuk konsistensi
11. [ ] Testing dengan berbagai kombinasi timeframe + gaya trading untuk pastikan konversi candle akurat
12. [ ] Update `penggunaan/PANDUAN_PENGGUNAAN.md`

---

## 6. Catatan Penting / Pertimbangan

- **Default tetap OFF (checkbox tidak dicentang)** — supaya tidak mengubah behavior yang sudah ada secara tiba-tiba untuk user yang belum familiar dengan fitur ini.
- Preset Swing (20-30 hari) di timeframe kecil (15m/1h) bisa butuh candle dalam jumlah besar — perlu pengujian apakah ini masih responsif/tidak timeout saat fetch data dari Gate.io.
- Metrik `win_rate_closed_by_target_only` kemungkinan akan **jauh lebih rendah** dari Win Rate Agregat yang tampil sekarang — ini justru **tujuannya**: memberi angka yang lebih jujur, bukan yang paling tinggi.

---

## 7. Status

**BELUM DIKERJAKAN** — dokumen ini adalah rancangan untuk didiskusikan/disetujui dulu sebelum implementasi. Tidak ada perubahan kode yang dibuat terkait plan ini.
