# Smart Screen Future — Engine As-Implemented

> Status: implemented 2026-08-29. Supersedes the numeric parameters proposed
> in `plan_architecture/PRD_SMART_SCREEN_FUTURE.md` and
> `brain/engine_v1/PRD_SMART_SCREEN_FUTURE_IFVG_ENGINE.md` (kept in place as
> the original design sketch) — this document records what was actually
> built and *why* it deviates from that sketch, per explicit instruction to
> harmonize the new engine with Coin Hunter and to use engineering judgment
> to maximize the design rather than implement the PRD numbers verbatim.

## 1. What changed vs. the original PRD, and why

| Parameter | PRD draft | As implemented | Why |
|---|---|---|---|
| Money Flow threshold | ±5% flow_pct_24h | **±15%** (same as Coin Hunter) | "Samakan semua dengan Coin Hunter" — one whale-flow threshold across the whole app, not a second tunable that could silently drift out of sync with the already-validated Coin Hunter figure. |
| Money Flow score delta | +15 | **±10** (same as Coin Hunter) | Same reasoning — reuses `SpotSignalEngineService::applyMoneyFlowConfluence()` directly instead of a second implementation. |
| Grade thresholds | A+ ≥85, A 75–84, B 65–74 | **A+ ≥90, A ≥70, B else** (same as Coin Hunter) | Same reasoning — one grading scale for both products, and it's the figure already validated against 960+ real combinations in Coin Hunter's audit (2026-08-29). |
| Liquidation price formula | `Entry × (1 ∓ 1/Leverage × 0.90)` (flat 0.90 approximation) | **Reuses `PaperTradingService::estimateLiquidationPrice()`** — real 0.5% maintenance-margin-rate formula | The approximation would show a different liquidation estimate on the signal card than what actually gets persisted when the position is opened via `/simulation/paper/buy` (same service, same formula either way). One formula, one place. |
| Scan pool | "250 koin real-time" | **50 coins per live cycle** (raised from Coin Hunter's original 30 per explicit user request 2026-08-29), 250 = eligible universe size shown in the coin list table below the signal grid | Coin Hunter's own live scan uses a 30-coin pool per cycle for the batched-candle-fetch latency reason; this module was deliberately raised to 50 as a one-off (Coin Hunter itself was left at 30, unchanged) since the user asked specifically for Smart Screen Future. Promising a literal 250-coin *live* scan would still be the over-promise the Money Flow module already avoids for its own coin count — honesty preserved in the UI copy. |
| PADAWAE | excluded | **excluded (unchanged)** | Confirmed correct in review — PADAWAE is a 1-directional trendline break/retest concept with no premium/discount mirror; forcing a SHORT-side PADAWAE would require inventing a mirror the source material (`STRATEGI_PADAWAE.md`) doesn't describe. |

## 2. Architecture: reuse, not duplication

`FuturesSignalEngineService` does **not** reimplement dealing-range /
equilibrium / OTE math, RVOL, HTF trend, ATR, or money-flow confluence. It
calls the exact same, already-tested static methods on
`SpotSignalEngineService` (elevated from `protected` to `public static` for
this purpose, with zero behavior change to any existing caller — verified by
running the full pre-existing Coin Hunter test suite after the extraction):

- `SpotSignalEngineService::calculateDealingRange($candles, $lookback)` — now
  also returns the mirrored `ote_premium` / `ote_premium_deep_limit` fields
  the SHORT side needs (symmetric retracement from swing low: 61.8%/78.6%
  *up* from the low, mirroring the existing 61.8%/78.6% *down from the
  high* discount fields).
- `SpotSignalEngineService::calculateRvol($candles)`
- `SpotSignalEngineService::calculateHtfTrend($htfCandles)`
- `SpotSignalEngineService::calculateATR($candles)`
- `SpotSignalEngineService::applyMoneyFlowConfluence($moneyFlow, $direction)`
  — gained a `$direction` parameter (`'long'` default, so every existing
  caller is unaffected). For `'short'`, the sign of the bonus/penalty flips:
  net outflow (distribution) becomes the *bullish-for-a-short* confluence,
  net inflow (accumulation) becomes the warning.

What genuinely is new to `FuturesSignalEngineService`:
- Bearish iFVG detection (mirror of the existing bullish detector — past
  bullish FVG that price breaks below and retests from underneath as
  resistance).
- BSL (Buy-Side Liquidity) sweep detection (mirror of the existing SSL
  sweep detector).
- The 2-way scoring model and entry/TP/SL construction for SHORT.

This mirrors the same lesson learned earlier this session with the
IFVG/PADAWAE strategy-mode split: duplicate logic across layers or products
drifts out of sync (the Blade-template hardcoded-narrative bug was a
duplicate of backend logic that diverged). Elevating the shared primitives
once, rather than copy-pasting them into a second engine, removes that
failure class here.

## 3. Scoring model

Same base-50-plus-deltas model as Coin Hunter's IFVG path, mirrored for
direction:

| Confluence | LONG delta | SHORT delta |
|---|---|---|
| Deep discount/premium zone (OTE 61.8–78.6%) | +25 | +25 |
| Discount/premium zone (past Equilibrium) | +15 | +15 |
| Wrong side of Equilibrium | -20 | -20 |
| SSL/BSL sweep detected | +20 | +20 |
| Valid iFVG (requires sweep first) | +20 | +20 |
| Potential move room ≥ target min % | +10 | +10 |
| RVOL ≥ 1.25 | +10 | +10 |
| RVOL < 0.7 | -10 | -10 |
| HTF trend aligned (bullish for LONG / bearish for SHORT) | +10 | +10 |
| HTF trend against ("falling knife" / "short squeeze" risk) | -15 | -15 |
| Money Flow aligned (inflow for LONG / outflow for SHORT) | +10 | +10 |
| Money Flow against | -10 | -10 |

Grade: A+ ≥90, A ≥70, B otherwise. Only Grade A/A+ signals surface in the
live-scan grid (identical filter to Coin Hunter's
`in_array($analysis['grade'], ['A+', 'A'])`).

## 4. The entry-price invariant, mirrored correctly for SHORT

Coin Hunter's spot engine had a real production bug (fixed 2026-08-29):
a "wait for entry" price could be suggested ABOVE the current price on a
strong ongoing downtrend, because the wait target (Equilibrium) is computed
from a 35-candle Swing High/Low window and can be stale relative to where
price has since moved. Fix: `$waitPriceIdeal = min($equilibrium,
$currentPrice)` — a LONG's wait target can never exceed current price.

The SHORT side needs the **mirrored** invariant, built in from day one
rather than discovered as a second bug later: a SHORT wants price to rise
further into the premium zone before entering, so its wait target can never
be **below** current price:

```php
$waitPriceIdeal = max($equilibrium, $currentPrice);
```

Verified with a reflection-based test (`tests/Unit/FuturesSignalEngineTest.php`
`test_wait_price_invariant_is_correctly_mirrored_for_both_directions`) and
with 2 live real-data checks against Gate.io BTCUSDT (LONG and SHORT) before
shipping.

## 5. What the UI deliberately does not add

- No spot/futures toggle in the simulation modal — this module is
  futures-only, and the SHORT-computed entry/TP/SL numbers would be
  incoherent if opened as a spot (long-only) position. The simulation modal
  locks side to the analyzed signal's direction.
- No "Jurnal" (journal-to-Trade) button — the existing `Trade` journal model
  path assumes `direction: 'long'` (see `SpotSignalController::journalInstant()`);
  extending it to short/leveraged futures trades wasn't requested and isn't
  a natural fit for the spot-oriented trade journal, so it was left out
  rather than half-built.
- No duplicate "all 250 coins" ticker table — that data is identical to
  Coin Hunter's own ticker table one click away; repeating it here would be
  pure duplication with no new information.
- No USD/IDR currency toggle — Coin Hunter itself doesn't have one; adding
  it only to this new menu would be an inconsistency, not a feature.
