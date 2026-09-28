# 🗺️ Master Plan (Revisi v2.0): Integrasi AI OmniRoute & TradingView MCP Live Analysis
## AlphaMachine Pro Trading Terminal — Triangulation Quant Architecture & Resilient Zero-Failure System

**Versi:** 2.0 (Updated dengan Graceful Fallback, Remote Launcher Button, & Gemini Flash 3.8)  
**Tanggal:** 2026-09-25  
**Status:** Architecture Blueprint & Implementation Specification  
**Scope:** Backend (`app/Services/`), Frontend Dashboard (`resources/views/`), MCP TradingView (`mcp/tradingview`), AI Router (`OmniRoute`).

---

## 1. Executive Summary & Filosofi "Resilient Zero-Failure"

Tujuan utama dari sistem ini adalah menciptakan sinergi 3 pilar (*Triangular Confirmation*):
1. **Quant Engine Matematis (Laravel)**: Menghitung presisi teknikal iFVG & PADAWAE dalam hitungan milidetik.
2. **TradingView Desktop (MCP Bridge)**: Mengendalikan chart langsung via remote port 9222, tata letak multi-timeframe (4H + 15M), gambar setup otomatis, dan ekstraksi screenshot.
3. **OmniRoute AI (Gemini Flash 3.8 Antigravity)**: Melakukan penalaran multimodal (audit wicks candle, deteksi trap likuiditas, konfirmasi narasi).

### 🛡️ Prinsip Utama: Transparan & Zero-Failure Fallback
Sistem harus **selalu beroperasi 100% normal tanpa error** dalam kondisi apa pun:
- **Kondisi Ideal (Full Triangulation)**: Quant + TradingView Live + AI OmniRoute aktif $\to$ Sinyal mendapat verifikasi komprehensif 3 arah.
- **Kondisi OmniRoute Mati / Tidak Ada Kuota**: Sistem **otomatis fallback** ke kalkulasi strategi default bawaan (`SpotSignalEngineService` / `FuturesSignalEngineService`). Tidak ada error 500, tidak ada delay.
- **Kondisi TradingView Tertutup / Port 9222 Putus**: Sistem **melewati tahapan visualisasi TradingView** tanpa memblokir pemindaian atau modal analisa koin. Dashboard menampilkan chart web fallback (TradingView Lightweight Chart) dan menyediakan tombol 1-Click Launch.

```
┌────────────────────────────────────────────────────────────────────────┐
│                   STATUS SERVICE HARNESS / MATRIX                      │
├─────────────────┬──────────────────┬───────────────────────────────────┤
│ TradingView     │ OmniRoute AI     │ Mode Operasi Sistem               │
├─────────────────┼──────────────────┼───────────────────────────────────┤
│ 🟢 Connected    │ 🟢 Active        │ 🔥 FULL TRIANGULAR MODE           │
│ 🔴 Disconnected │ 🟢 Active        │ ⚡ QUANT + AI AUDIT (Web Chart)   │
│ 🟢 Connected    │ 🔴 Offline/Empty │ 📊 QUANT + TRADINGVIEW LIVE (No AI)│
│ 🔴 Disconnected │ 🔴 Offline/Empty │ 🛡️ DEFAULT QUANT CORE (Zero Lag)  │
└─────────────────┴──────────────────┴───────────────────────────────────┘
```

---

## 2. Fitur Baru 1: Tombol Dashboard "Launch & Connect TradingView Remote"

### 2.1 Masalah di Lapangan
TradingView Desktop yang dibuka secara normal melalui icon macOS dock tidak membuka port debugging `9222`. Hal ini menyebabkan MCP TradingView gagal terhubung (`ECONNREFUSED 127.0.0.1:9222`).

### 2.2 Solusi Tombol Cepat di Header Dashboard
Pada header/topbar dashboard (bersebelahan dengan jam & status Reverb), tambahkan widget kontrol TradingView:
- **Status Badge**:
  - `[TV: 🟢 Connected :9222]` (Jika port 9222 merespon handshake CDP).
  - `[TV: 🔴 Offline - Klik Launch]` (Jika port 9222 mati).
- **Aksi Tombol "Launch & Connect"**:
  Saat tombol ditekan, frontend memanggil endpoint `POST /api/tradingview/launch-remote`.

### 2.3 Mekanisme Eksekusi Backend (`TradingViewRemoteService.php`)
Backend Laravel mengeksekusi perintah shell macOS secara aman:

```bash
# 1. Cek apakah ada proses TradingView lama yang berjalan tanpa remote port
# 2. Jalankan TradingView dengan flag remote-debugging-port di latar belakang
nohup /Applications/TradingView.app/Contents/MacOS/TradingView --remote-debugging-port=9222 > /dev/null 2>&1 &
```

### 2.4 Self-Healing & Health Check Polling
1. Setelah perintah dieksekusi, backend melakukan polling verifikasi ke `http://127.0.0.1:9222/json/version` selama maksimal 5 detik.
2. Jika dalam 5 detik port merespon status `200 OK` dengan metadata browser CDP, kembalikan response:
   ```json
   { "status": "success", "message": "TradingView connected successfully on port 9222", "connected": true }
   ```
3. Tombol di UI langsung berganti warna menjadi hijau tanpa perlu reload halaman (`Alpine.js reactive state`).
4. Disediakan juga opsi menu dropdown: **"Restart Clean"** (menjalankan `pkill -f TradingView` terlebih dahulu jika proses sebelumnya *hang*).

---

## 3. Fitur Baru 2: OmniRoute AI (Provider: Gemini Flash 3.8 Antigravity)

### 3.1 Pemilihan Model & Keunggulan
- **Model**: `gemini-flash-3.8-antigravity` (via OmniRoute Gateway).
- **Keunggulan**:
  - **Super Fast Latency**: Respons rata-rata `< 1.2 detik`.
  - **Native Multimodal**: Dapat menerima teks konteks kuantitatif sekaligus *base64 screenshot chart* resolusi tinggi dalam satu request tunggal.
  - **Biaya Sangat Ringan**: Cocok untuk pemindaian rutin puluhan koin.

### 3.2 Pemisahan Strategi pada Prompt AI

#### A. Prompt iFVG (Inversed Fair Value Gap & Discount Hunting)
```text
Role: Senior ICT/SMC Institutional Quantitative Auditor.
Strategy: iFVG (Inversed Fair Value Gap).
Rules to verify:
1. Dealing Range & Equilibrium: Is price truly in the Discount Zone (< 50% range)?
2. Liquidity Sweep: Did the wick sweep Sell-Side Liquidity (SSL) cleanly and reject upwards?
3. FVG Displacement: Is the inversed FVG formed with genuine buying momentum, not a slow bleed?
Input Data: [Dealing Range, Equilibrium, Current Price, RVOL, Net Money Flow, HTF Trend, Chart Screenshot]
Output Format (JSON only):
{
  "ai_verdict": "CONFIRMED" | "REJECTED" | "CAUTION",
  "confidence_pct": 88,
  "score_modifier": 5,
  "sweep_quality": "HIGH" | "LOW" | "FAKE",
  "reasoning_summary": "Clean sweep below 142.1 with long bottom wick. HTF 4H uptrend intact."
}
```

#### B. Prompt PADAWAE (Trendline Breakout Reversal & Retest)
```text
Role: Senior Classical Technical Analyst & Wave Momentum Auditor.
Strategy: PADAWAE (3x Rejection Trendline Breakout & Retest).
Rules to verify:
1. Trendline Structure: Are there at least 3 distinct, descending rejection touches?
2. Breakout 1 & Retest: Did low retest the broken trendline without closing back inside?
3. Delivery Momentum: Is volume on Breakout 2 confirming buyer control?
Input Data: [Trendline coordinates, Retest Price, RVOL, Net Money Flow, Chart Screenshot]
Output Format (JSON only):
{
  "ai_verdict": "CONFIRMED" | "REJECTED" | "CAUTION",
  "confidence_pct": 82,
  "score_modifier": 0,
  "breakout_cleanliness": "CLEAN" | "CHOPPY",
  "reasoning_summary": "Valid 3x touch. Retest holds on 15m. Watch resistance at local swing high."
}
```

---

## 4. Mekanisme Validasi & Circuit Breaker (Graceful Degradation)

Untuk mencegah error, sistem menerapkan pola **Circuit Breaker** bertingkat:

```
                  [ REQUEST ANALISA KOIN DI COIN HUNTER ]
                                     │
                                     ▼
                  ┌─────────────────────────────────────┐
                  │ 1. CEK STATUS KONEKSI TRADINGVIEW   │
                  └──────────────────┬──────────────────┘
                         Aktif?      │      Mati?
             ┌───────────────────────┴───────────────────────┐
             ▼                                               ▼
┌─────────────────────────┐                     ┌─────────────────────────┐
│ • Panggil MCP TV        │                     │ • Bypass MCP TV         │
│ • Set Multi-Timeframe   │                     │ • Ambil web chart data  │
│ • Gambar Zona & Target  │                     │ • Tampilkan badge:      │
│ • Ambil Screenshot PNG  │                     │   "TV: Disconnected"    │
└────────────┬────────────┘                     └────────────┬────────────┘
             │                                               │
             └───────────────────────┬───────────────────────┘
                                     ▼
                  ┌─────────────────────────────────────┐
                  │ 2. CEK STATUS OMNIROUTE AI          │
                  └──────────────────┬──────────────────┘
                         Aktif?      │      Mati / Timeout?
             ┌───────────────────────┴───────────────────────┐
             ▼                                               ▼
┌─────────────────────────┐                     ┌─────────────────────────┐
│ • Kirim Data + Gambar   │                     │ • Bypass OmniRoute      │
│   ke Gemini Flash 3.8   │                     │ • Gunakan Skor Quant    │
│ • Tampilkan AI Verdict  │                     │   Default Murni         │
│   & Review Komprehensif │                     │ • Tampilkan badge:      │
│                         │                     │   "AI: Fallback Mode"   │
└────────────┬────────────┘                     └────────────┬────────────┘
             │                                               │
             └───────────────────────┬───────────────────────┘
                                     ▼
                  ┌─────────────────────────────────────┐
                  │ 3. RENDER MODAL RESPON INTERAKTIF   │
                  │ (Selalu sukses tanpa error 500)     │
                  └─────────────────────────────────────┘
```

### Kode Validasi Fallback di Controller / Service:
```php
try {
    $aiAnalysis = $this->omniRouteClient->isEnabled() 
        ? $this->omniRouteClient->auditSignal($signalData, $screenshotBase64)
        : null;
} catch (\Throwable $e) {
    Log::warning("OmniRoute AI fallback triggered: " . $e->getMessage());
    $aiAnalysis = null; // Tetap lanjut menggunakan default strategi engine
}

try {
    $tvData = $this->tradingViewBridge->isConnected()
        ? $this->tradingViewBridge->inspectAndDraw($signalData)
        : null;
} catch (\Throwable $e) {
    Log::warning("TradingView MCP fallback triggered: " . $e->getMessage());
    $tvData = null; // Lewati chart live, tetap tampilkan data quant
}
```

---

## 5. Workflow Interaktif Coin Hunter <-> Multi-Timeframe TradingView

Ketika pengguna mengklik tombol **"Analisa / Inspect"** koin di Coin Hunter:

1. **Sinkronisasi Multi-Timeframe di TradingView**:
   - `pane_set_layout(layout: "2v")`: Membuka 2 chart bersisian di TradingView Desktop.
   - **Pane 1 (Kiri - HTF 4H / 1D)**: Menampilkan gambaran besar market structure, EMA 200, dan support/resistance utama.
   - **Pane 2 (Kanan - LTF 15M)**: Menampilkan candle eksekusi, kotak iFVG (dengan Equilibrium 50%), atau garis trendline PADAWAE beserta label TP1, TP2, dan SL.
2. **Ekstraksi Indikator Real-Time**:
   - Mengambil data indikator live dari TradingView via `data_get_study_values` (RSI, Bollinger Bands, Volume) untuk melengkapi kalkulasi lokal Gate.io.
3. **Capture & Triangulasi AI**:
   - TradingView MCP mengambil tangkapan layar melalui `capture_screenshot`.
   - Gambar + data indikator dikirim ke **Gemini Flash 3.8** di OmniRoute.
   - Hasil ulasan AI muncul di modal Coin Hunter dalam kurun waktu `< 2 detik`.

---

## 6. Fitur & Saran Tambahan yang Ditambahkan ke Sistem

Berdasarkan revisi kebutuhan, berikut daftar penambahan fitur yang dimasukkan ke dalam arsitektur:

1. **Header System Health Widget**:
   - Indikator status live untuk Reverb, TradingView port 9222, dan OmniRoute AI.
   - Tombol instan 1-Click Launch TradingView dengan status spinner loading.
2. **Asynchronous Decoupling (Non-Blocking UI)**:
   - Pemindaian 2.000 koin di Coin Hunter **tetap berjalan murni in-memory (< 2 detik)** tanpa memanggil OmniRoute per koin untuk mencegah tagihan token berlebih dan lag.
   - OmniRoute dan TradingView MCP hanya dipanggil saat:
     - Trader mengklik **"Analisa"** pada koin tertentu, ATAU
     - Koin mencapai kriteria **Grade A+ (Skor $\ge 90$)** secara otomatis di latar belakang.
3. **1-Click Sync Watchlist & Set Alert**:
   - Di dalam modal analisa koin, sediakan 2 tombol aksi:
     - `[+ Tambah ke Watchlist TV]`: Memanggil `watchlist_add` di TradingView Desktop.
     - `[🔔 Pasang Alert di Level Entry]`: Memanggil `alert_create` di TradingView Desktop.
4. **Auto Proof-of-Trade Visual Jurnal**:
   - Saat order dieksekusi di simulasi/jurnal, sistem otomatis menyimpan screenshot TradingView ke tabel `trade_attachments` sebagai bukti analisis sebelum posisi berjalan.

---

## 7. Roadmap Rencana Implementasi Bertahap

| Fase | Fokus Pekerjaan | Berkas / Komponen Terkait |
|---|---|---|
| **Fase 1** | **TradingView Remote Launcher & Healthcheck** | • Service: `app/Services/TradingView/TradingViewRemoteService.php`<br>• Controller: `app/Http/Controllers/TradingViewBridgeController.php`<br>• UI Button di Header Dashboard ([`resources/views/layouts/app.blade.php`](file:///Users/adybudi/Project/project-tools/trading-dashboard/resources/views/layouts/app.blade.php)) |
| **Fase 2** | **OmniRoute Client (Gemini Flash 3.8) & Circuit Breaker** | • Config: `config/services.php` & `.env`<br>• Service: `app/Services/Ai/OmniRouteClient.php`<br>• Prompt Builder: iFVG vs PADAWAE terpisah<br>• Fallback handler jika AI offline / disable |
| **Fase 3** | **TradingView MCP Multi-Timeframe Bridge** | • Bridge interaksi MCP: `chart_set_symbol`, `pane_set_layout("2v")`, `draw_strategy_setup`, `capture_screenshot`<br>• Fallback handler jika TV tertutup |
| **Fase 4** | **UI Interaktif Coin Hunter Modal & Live Sync** | • Modal analisa koin di [`resources/views/signals/index.blade.php`](file:///Users/adybudi/Project/project-tools/trading-dashboard/resources/views/signals/index.blade.php)<br>• Menampilkan screenshot chart, skor quant, indikator TV, dan badge AI Verdict |
| **Fase 5** | **Testing Komprehensif** | • Unit test fallback (TV mati, AI mati, keduanya mati)<br>• Feature test tombol remote launcher dan analisa koin |
