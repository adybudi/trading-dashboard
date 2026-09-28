# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**AlphaJournal** is a Laravel 12 trading journal & performance monitoring dashboard, with a self-contained
crypto signal-scanning/backtesting engine ("Coin Hunter") bolted on. It is not a generic Laravel skeleton —
most of the domain logic lives in `app/Services/`, backed by design docs in `plan_architecture/` and `brain/`.

## Commands

```bash
# Full dev environment (server + queue worker + log tail + vite), single command:
composer dev

# Or individually:
php artisan serve --port=8001      # HTTP server
php artisan queue:listen --tries=1 --timeout=0
php artisan pail --timeout=0       # log tailing
npm run dev                        # Vite

# Custom launcher (prints dashboard URLs, then runs `artisan serve`):
php artisan start --port=8001 --host=127.0.0.1

# Tests
composer test                      # clears config cache, then `php artisan test`
php artisan test --filter=TestClassName::test_method_name
php artisan test tests/Feature/SpotSignalTest.php

# Frontend build
npm run build

# Coin Hunter / Autonomous Spot Learner CLI
php artisan spot:learn --symbol=BTCUSDT --timeframe=1h --iterations=30
php artisan spot:learn --scan-all       # scan all coins for validated Golden Entries
```

Tests run against in-memory SQLite (`phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`,
`QUEUE_CONNECTION=sync`, `BROADCAST_CONNECTION=null`) regardless of the `.env` driver. Local dev `.env` is
configured for **MySQL** (`trading_dashboard_db`) plus **Laravel Reverb** for websockets — don't assume
SQLite is used outside the test suite.

## Documentation Layout (read before planning changes)

This repo enforces a strict split between two doc directories — respect it when adding new markdown docs:

- **`brain/`** — exclusively trading logic, algorithms, math specs, and engine PRDs (iFVG, Liquidity Sweep,
  PADAWAE, OTE Fibonacci, win-rate/PF/drawdown formulas, the autonomous learner's math). Two subfolders:
  `brain/engine_v1/` (the rule-based signal engine spec) and `brain/engine_learner/` (the self-learning /
  calibration system spec).
- **`plan_architecture/`** — exclusively system architecture plans, UI/UX plans, and pre-execution proposals
  for feature changes. Not trading-strategy content.
- Rule of thumb from `brain/MEMORY_RULES.md`: new trading-logic/formula docs go in `brain/`; new
  architecture/UI/refactor plan docs go in `plan_architecture/`.

Key docs worth reading before touching related code:
- `plan_architecture/ARCHITECTURE.md` — tech stack, Reverb channel/event design, directory layout, dual-theme design tokens.
- `plan_architecture/DATABASE_SCHEMA.md` — full ERD or `accounts/strategies/tags/trades/trade_attachments`.
- `brain/engine_v1/ENGINE_ARCHITECTURE.md` + `ALGORITHM_SPECIFICATION.md` — Coin Hunter scoring pipeline.
- `brain/engine_learner/AUTONOMOUS_SPOT_LEARNER_BRAIN.md` — the adaptive-weight learner (perceptron-style
  weight updates, market regime classification, repeatable-profit validation gates).
- `plan_architecture/PLAN_AUTO_LEARN_250_COINS_BACKGROUND_DAEMON.md` — planned background daemon for
  screening 250 coins on `php artisan start` (check current state before assuming this is fully built).

## Architecture

### Two independent domains sharing one app

1. **Trade Journal** (the "core" product): `Account`, `Trade`, `Strategy`, `Tag`, `TradeAttachment` models.
   Manual trade logging with auto-calculated PnL/ROI/RR via `TradeCalculatorService`, multi-account
   portfolio views, `AnalyticsService` for KPI/equity-curve/heatmap data. Controllers: `TradeController`,
   `AccountController`, `AnalyticsController`, `DashboardController`.

2. **Coin Hunter / Spot Signal Engine** (the "quant" side): scans crypto spot pairs for high-confluence
   entries and can auto-convert a signal into a journal entry or a paper trade.
   - `MarketDataFetcherService` — pulls OHLCV (Binance klines / Gate.io / CoinGecko depending on doc vs.
     code — verify which source is actually wired before relying on either), with a 60s cache and an
     offline fallback generator.
   - `SpotSignalEngineService` — runs the iFVG / liquidity-sweep / PADAWAE / OTE confluence scoring
     (0–100), assigns a grade (A+/A/B/C), and projects TP1 (+10%), TP2 (+20%), invalidation level.
   - `SignalNotificationService` — persists Grade A/A+ signals to `spot_signals` and broadcasts
     `SpotSignalDetected` over Reverb.
   - `AutonomousSpotLearnerService` — the self-calibrating layer described in
     `brain/engine_learner/*`: adjusts feature weights from historical replay outcomes, gates a signal as
     "golden" only after repeatable multi-cycle profit validation (win rate ≥ 80%, profit factor ≥ 2.5).
   - `BacktestEngineService` / `PaperTradingService` — power the `/simulation` backtesting + paper-trading
     UI (`SimulationController`), separate from live signal scanning.

Controller entry points for this side: `SpotSignalController` (`/signals/*`) and `SimulationController`
(`/simulation/*`).

### Real-time layer (Laravel Reverb)

- Private per-user channel: `private-user.{userId}`, authorized in `routes/channels.php`.
- Broadcast events: `TradeCreated`, `TradeUpdated`, `TradeDeleted`, `AccountBalanceUpdated`,
  `SpotSignalDetected`. These update dashboard KPIs/equity curve and the signal radar without a page reload.
- Client side: `resources/js/echo.js` wires Laravel Echo + Pusher-js to Reverb; Alpine.js components consume
  the events for live DOM/chart updates.

### Auth & routing shape

- No API guard — session-based `auth`/`guest` middleware groups in `routes/web.php`.
- Root `/` and `/dashboard` both resolve to `DashboardController@index` when authenticated; unauthenticated
  users land on `/login`. There's a `/demo-login` route for 1-click demo access.
- `trades` and `accounts` are largely resource routes; signal/simulation endpoints are bespoke action routes
  (scan, chart-data, journal-instant, paper/buy, paper/close, etc.) rather than REST resources.

### Frontend conventions

- Tailwind CSS v4 via `@theme` tokens in `resources/css/app.css` — dual light/dark themes with semantic
  trading colors (emerald = profit/long, rose = loss/short) and `font-mono`/tabular-nums for all numeric
  data.
- Alpine.js + Blade components for interactivity (no SPA framework). ApexCharts for equity curve / PnL
  charts. SweetAlert2 for all confirmation dialogs and toasts.
- Icons are exclusively Lucide SVGs (via `blade-ui-kit/blade-icons`) — no emoji indicators anywhere in the UI.

## Conventions to preserve

- Keep new trading-logic/formula documentation in `brain/`, new architecture/UI-plan documentation in
  `plan_architecture/` — this split is an explicit project rule, not a suggestion.
- Money/price fields are high-precision decimals (`decimal(18,8)` for prices/quantities in `trades` and
  `spot_signals`); don't downcast to float when doing PnL/score math server-side.
- When adding a broadcastable domain event, follow the existing pattern (`app/Events/*`) and dispatch over
  the user's private channel, not a public one.
