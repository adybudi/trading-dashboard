# Design System & UI/UX Guidelines
## Project: Trading Journal & Performance Monitoring Dashboard

---

## 1. Design Philosophy: High-Craft, Minimalist, Anti-AI Slop

Aplikasi ini mengadopsi estetika **Fintech & Pro Trader Terminal** (terinspirasi dari platform kelas dunia seperti *TradingView*, *Raycast*, *Linear*, dan *Bloomberg Terminal*). 

### Prinsip Utama:
1. **Dual Theme (Dark & Light Mode):** Mendukung mode Gelap (default untuk sesi trading malam/intensif) dan mode Terang (bersih, kontras tinggi untuk siang hari). Pergantian tema instan dengan toggle icon `lucide-sun` / `lucide-moon` dan disimpan di `localStorage` tanpa efek kedip (anti-FOUC).
2. **Zero Emojis / Emotes:** Semua simbol status, navigasi, dan indikator **WAJIB** menggunakan icon library profesional (**Lucide Icons / Heroicons** berupa SVG). Dilarang keras menggunakan emoji teks (seperti 🚀, 📈, 💰, ❌, ✅) di antarmuka aplikasi.
3. **High Information Density with Visual Breathing Room:** Border tipis 1px presisi, padding terukur, hierarchy tipografi jelas, tanpa gradien mencolok (*gaudy gradients*) atau efek glow berlebihan yang tidak fungsional.
4. **Tabular Numerics for Financial Data:** Semua angka moneter, persentase, harga, lot, dan PnL menggunakan font monospaced / tabular numerals (`font-mono font-medium tracking-tight`) agar sejajar rapi di tabel dan KPI card.
5. **Left Sidebar Architecture:** Navigasi di sebelah kiri yang terstruktur, memisahkan navigasi utama, pemilih akun, CTA cepat, dan profil pengguna.

---

## 2. Color Palette & Dual-Theme Variables (Tailwind CSS v4)

Warna didefinisikan sebagai token CSS semantik (`CSS Variables`) dengan dukungan penuh untuk `:root` (Light Mode) dan `.dark` (Dark Mode).

### 2.1. Dual-Theme Palette Matrix

| Semantic Token | Light Mode (`:root`) | Dark Mode (`.dark`) | Deskripsi & Penggunaan |
| :--- | :--- | :--- | :--- |
| `--bg-canvas` | `#F8FAFC` (Slate-50) | `#080B10` (Deep Obsidian) | Background dasar halaman aplikasi |
| `--bg-surface` | `#FFFFFF` (Pure White) | `#0F141C` (Dark Slate) | Background card, panel widget, sidebar, header |
| `--bg-surface-elevated` | `#F1F5F9` (Slate-100) | `#161C26` (Elevated Slate) | Background modal, dropdown menu, SweetAlert popup |
| `--bg-surface-hover` | `#F1F5F9` (Slate-100) | `#1C2430` (Hover Slate) | Hover state pada baris tabel & tombol sekunder |
| `--border-subtle` | `#E2E8F0` (Slate-200) | `#1A222D` (Subtle Dark) | Border tipis 1px untuk pemisah kontainer & card |
| `--border-muted` | `#CBD5E1` (Slate-300) | `#263342` (Muted Dark) | Border form input, tab separator |
| `--text-primary` | `#0F172A` (Slate-900) | `#F1F5F9` (Slate-100) | Teks judul, angka KPI utama, isi teks penting |
| `--text-secondary` | `#475569` (Slate-600) | `#94A3B8` (Slate-400) | Subtitle, label form, header kolom tabel |
| `--text-muted` | `#94A3B8` (Slate-400) | `#64748B` (Slate-500) | Timestamp, caption, inactive icon |
| `--trade-profit` | `#059669` (Emerald-600) | `#10B981` (Emerald-500) | Nilai profit (+PnL), Win badge, Long entry |
| `--trade-profit-bg` | `rgba(5, 150, 105, 0.08)` | `rgba(16, 185, 129, 0.12)` | Subtle background untuk tag/badge profit |
| `--trade-loss` | `#E11D48` (Rose-600) | `#F43F5E` (Rose-500) | Nilai loss (-PnL), Loss badge, Short entry |
| `--trade-loss-bg` | `rgba(225, 29, 72, 0.08)` | `rgba(244, 63, 94, 0.12)` | Subtle background untuk tag/badge loss |
| `--trade-neutral` | `#0284C7` (Sky-600) | `#38BDF8` (Sky-400) | Break-even, Pending status, Info highlight |
| `--accent-primary` | `#2563EB` (Blue-600) | `#3B82F6` (Blue-500) | Primary action button, active tab, active chart line |
| `--accent-focus` | `#3B82F6` (Blue-500) | `#60A5FA` (Blue-400) | Outline focus ring, active link hover |

---

## 3. Tailwind CSS Configuration Snippet

File: `resources/css/app.css` (Tailwind CSS v4 Standard with Dual-Theme CSS Variables)

```css
@import "tailwindcss";

:root {
  --color-canvas: #F8FAFC;
  --color-surface: #FFFFFF;
  --color-surface-elevated: #F1F5F9;
  --color-surface-hover: #F1F5F9;
  --color-border-subtle: #E2E8F0;
  --color-border-muted: #CBD5E1;
  --color-text-primary: #0F172A;
  --color-text-secondary: #475569;
  --color-text-muted: #94A3B8;
  --color-trade-profit: #059669;
  --color-trade-profit-bg: rgba(5, 150, 105, 0.08);
  --color-trade-loss: #E11D48;
  --color-trade-loss-bg: rgba(225, 29, 72, 0.08);
  --color-trade-neutral: #0284C7;
  --color-trade-neutral-bg: rgba(2, 132, 199, 0.08);
  --color-accent: #2563EB;
  --color-accent-hover: #1D4ED8;
}

.dark {
  --color-canvas: #080B10;
  --color-surface: #0F141C;
  --color-surface-elevated: #161C26;
  --color-surface-hover: #1C2430;
  --color-border-subtle: #1A222D;
  --color-border-muted: #263342;
  --color-text-primary: #F1F5F9;
  --color-text-secondary: #94A3B8;
  --color-text-muted: #64748B;
  --color-trade-profit: #10B981;
  --color-trade-profit-bg: rgba(16, 185, 129, 0.12);
  --color-trade-loss: #F43F5E;
  --color-trade-loss-bg: rgba(244, 63, 94, 0.12);
  --color-trade-neutral: #38BDF8;
  --color-trade-neutral-bg: rgba(56, 189, 248, 0.10);
  --color-accent: #3B82F6;
  --color-accent-hover: #2563EB;
}

@theme {
  --color-canvas: var(--color-canvas);
  --color-surface: var(--color-surface);
  --color-surface-elevated: var(--color-surface-elevated);
  --color-surface-hover: var(--color-surface-hover);
  --color-border-subtle: var(--color-border-subtle);
  --color-border-muted: var(--color-border-muted);
  --color-text-primary: var(--color-text-primary);
  --color-text-secondary: var(--color-text-secondary);
  --color-text-muted: var(--color-text-muted);
  --color-trade-profit: var(--color-trade-profit);
  --color-trade-profit-bg: var(--color-trade-profit-bg);
  --color-trade-loss: var(--color-trade-loss);
  --color-trade-loss-bg: var(--color-trade-loss-bg);
  --color-trade-neutral: var(--color-trade-neutral);
  --color-accent: var(--color-accent);
  --color-accent-hover: var(--color-accent-hover);

  --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
  --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
}
```

---

## 4. Theme Switcher Architecture (Anti-FOUC)

Untuk mencegah efek *Flash of Unstyled Content (FOUC)* saat load halaman:

### 4.1. Head Script (`resources/views/layouts/app.blade.php`)
```html
<head>
  <script>
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  </script>
</head>
```

---

## 5. UI Component Anatomy

### 5.1. Left Sidebar Layout Anatomy
- **Width:** `w-64` (Desktop persistent, `fixed inset-y-0 left-0 bg-surface border-r border-border-subtle z-30`).
- **Mobile Drawer:** Slide-out overlay using Alpine.js (`x-show="sidebarOpen"` with smooth transition and backdrop overlay).
- **Navigation Links:**
  - Active: `bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs`
  - Inactive: `text-text-secondary hover:text-text-primary hover:bg-surface-hover transition`
  - Icon: Lucide SVG (`w-4 h-4 mr-2.5`)
- **Quick Action CTA:** Prominent `+ Log Trade` button in sidebar.
- **Sidebar Footer:** User badge with avatar initials, email, theme switcher toggle, and logout button.

### 5.2. Login & Registration Screen Anatomy
- Centered minimalist card (`max-w-md w-full mx-auto bg-surface border border-border-subtle rounded-2xl p-8 shadow-2xl`).
- Branding banner with AlphaJournal logo & fintech terminal aesthetic.
- High-contrast inputs with monospace placeholders and dark focus rings (`focus:border-accent focus:ring-1 focus:ring-accent`).
- 1-Click Quick Demo Login button for swift developer testing.

### 5.3. Minimalist KPI Metric Card
```html
<div class="bg-surface border border-border-subtle rounded-lg p-4 transition hover:border-border-muted">
  <div class="flex items-center justify-between text-text-secondary text-xs uppercase font-medium tracking-wider mb-2">
    <span>Net Cumulative P&L</span>
    <x-app-icon name="trending-up" class="w-4 h-4 text-trade-profit" />
  </div>
  <div class="flex items-baseline gap-2">
    <span class="font-mono text-2xl font-semibold text-trade-profit">+$14,820.50</span>
    <span class="font-mono text-xs text-trade-profit bg-trade-profit-bg px-1.5 py-0.5 rounded">+18.4%</span>
  </div>
  <p class="text-text-muted text-xs mt-2">Calculated from 142 closed trades</p>
</div>
```

### 5.4. Trading Table Row
- **Header:** Sticky, `bg-surface`, `text-text-secondary`, `text-xs font-medium uppercase`.
- **Row:** `border-b border-border-subtle hover:bg-surface-hover transition-colors`.
- **Numbers:** `font-mono text-right tabular-nums`.

### 5.5. Calendar Heatmap
- Grid 7 hari (Mon - Sun).
- Kotak tanggal berukuran presisi (aspect-square), background gelap netral untuk hari non-trade, saturasi hijau bertingkat untuk profit, dan saturasi merah bertingkat untuk loss.

---

## 6. Iconography Guidelines (Zero Emote Policy)

Gunakan **Lucide Icons** (`<x-app-icon name="..." />` berupa SVG) dengan stroke width `1.75px` untuk estetika clean dan presisi.

### Standard Icon Mapping:
- **Theme Mode:** `lucide-sun` / `lucide-moon`
- **Dashboard:** `lucide-bar-chart-3`
- **Journal:** `lucide-book-open`
- **Analytics:** `lucide-target`
- **Accounts:** `lucide-wallet`
- **Logout:** `lucide-log-out`
- **Login / User:** `lucide-user` / `lucide-lock`
- **Profit / Loss:** `lucide-trending-up` / `lucide-trending-down`

---

## 7. Alert & Notification System: SweetAlert2 (Adaptive Dual-Theme)

SweetAlert2 di [`resources/js/alerts.js`](file:///Users/adybudi/Project/project-tools/trading-dashboard/resources/js/alerts.js) menyesuaikan tampilannya secara dinamis berdasarkan status tema yang sedang aktif (`light` vs `dark`).
