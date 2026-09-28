# Product Requirements Document (PRD)
## Spot Signal Engine v1.6 — Expanded 250-500 Liquid Spot Coins Sorted by 24h Volume
**Fitur: Skrining 250–500 Koin Spot Likuid Teratas Dunia, Urutan Otomatis Berdasarkan Volume 24 Jam Tertinggi (BTC, ETH, SOL, XRP, BNB...), & Pagination Dinamis**

---

## 1. Executive Summary & Problem Statement

### 1.1. Latar Belakang Permintaan Pengguna
- **Cakupan Koin Lebih Luas:** Pengguna meminta perluasan daftar koin spot dari 100 koin menjadi **Top 250 – 500 koin spot teratas** di pasar dunia.
- **Urutan Berdasarkan Volume Tertinggi:** Pengguna meminta daftar koin **diurutkan secara mutlak berdasarkan Volume Transaksi 24 Jam Tertinggi (*Volume 24h Descending*)**, sehingga koin pemimpin likuiditas global seperti **Bitcoin (BTC), Ethereum (ETH), Solana (SOL), XRP, BNB, Dogecoin (DOGE)** selalu berada di urutan teratas (#1, #2, #3, dst.).

---

## 2. Arsitektur Data Pipeline 250–500 Koin

```mermaid
graph TD
    subgraph Data Source (Bebas Rate Limit & Bebas Blokir)
        GATE[Gate.io Spot Tickers API: 2.200+ Pasangan Spot USDT]
        CG[CoinGecko Reference Agregator]
    end

    subgraph Data Processing Layer
        GATE --> Filter[Filter Stablecoins & Vol > $50.000]
        Filter --> VolumeSort[Sort Descending: 24h Volume High-to-Low]
        VolumeSort --> Top300Slice[Slice Top 250-500 Liquid Crypto Spot Assets]
        Top300Slice --> FastCache[Low-Latency Cache 5 Detik]
    end

    subgraph User Interface Layer
        FastCache --> TickerEndpoint[GET /signals/live-prices]
        TickerEndpoint --> AlpinePaginator[Alpine.js Pagination: 15 / 20 / 25 / 50 / 100 per page]
        AlpinePaginator --> RenderTable[Tabel Koin Urut Volume: #1 BTC, #2 ETH, #3 SOL, ...]
    end
```

---

## 3. Spesifikasi Teknis & Urutan Koin

### 3.1. Algoritma Pengurutan Volume 24 Jam

```php
// Filter stablecoin murni
$excluded = ['USDT_USD', 'USDC_USDT', 'DAI_USDT', 'USDS_USDT', 'USDE_USDT', 'FDUSD_USDT', 'WBTC_USDT', 'STETH_USDT', 'WETH_USDT'];

// Urutkan volume 24 jam tertinggi ke terendah
usort($filtered, function ($a, $b) {
    return $b['volume_24h'] <=> $a['volume_24h'];
});

// Ambil 300 koin terlikuid
$topCoins = array_slice($filtered, 0, 300);
```

### 3.2. Urutan Koin di Layar (Contoh Hasil):
1. **#1 BTC/USDT:** Volume ~\$350M+ (Harga ~$78,800)
2. **#2 ETH/USDT:** Volume ~\$230M+ (Harga ~$2,490)
3. **#3 SOL/USDT:** Volume ~\$105M+ (Harga ~$101)
4. **#4 XRP/USDT:** Volume ~\$58M+ (Harga ~$1.40)
5. **#5 HYPE/USDT:** Volume ~\$33M+ (Harga ~$81)
6. ...diikuti oleh 250+ altcoin dengan volume transaksi tertinggi secara berurutan.

---

## 4. Spesifikasi Pagination & Search

- **Jumlah Total Koin:** 250 – 300 koin likuid.
- **Pilihan Per Halaman:** `15`, `20`, `25`, `50`, `100` koin.
- **Global Search:** Mengetik nama koin (misal: `PEPE`, `NEAR`, `INJ`, `SUI`, `RENDER`) langsung mencari di seluruh 300 koin secara instan.
- **Live Auto-Ticker:** Memperbarui harga real-time setiap 4 detik dengan efek kedip hijau/merah.
