# Epal ERP — DESIGN.md

Design system for **new** screens and **Wood Art Interiors** (`Modules/WoodArt/`).
Tokens are taken from what the Wood Art / Construction screens already use, so
new work matches them.

> **Scope (hard rule, see CLAUDE.md "LOCKED"):** this file governs new screens
> and files under `Modules/WoodArt/`. Never restyle another company's existing
> views (Travels, HR, Finance…) to match it — report differences instead.
> Every Wood Art selector carries a `wa-` / `wap-` prefix; no bare element
> selectors, no edits to `resources/css/app.css`.

## 1. Character
Calm, dense, professional back-office tool. Light surfaces, one teal accent,
slate text. Information first: tables, KPI tiles, status chips. No decoration
for its own sake.

## 2. Colour tokens

| Role | Token | Value |
|---|---|---|
| Accent (Construction) | `--wap-accent` | `#009b8e` (teal) |
| Accent (Wood Art default) | `--wap-accent` | `#0f766e` |
| Wood Art brand — timber | `--wa-timber` | `#a86a2e` |
| Wood Art brand — lamp | `--wa-lamp` / `--wa-lamp-hot` | `#f0a848` / `#ffd08a` |
| Wood Art brand — draft blue | `--wa-draft` | `#1a43bf` |
| Wood Art brand — leaf | `--wa-accent` | `#6f9c1c` |
| Page background | `--wap-bg` | `#f6f9fb` (construction) · `#e8edf6` |
| Surface (card) | `--wap-surface` | `#ffffff` |
| Surface 2 (inset, table head) | `--wap-surface-2` | `#f8fafc` |
| Surface hi (hover) | `--wap-surface-hi` | `#f1f5f9` |
| Text | `--wap-text` | `#1e293b` |
| Text dim | `--wap-text-dim` | `#475569` |
| Text mute | `--wap-text-mute` | `#64748b` |
| Border | `--wap-border` | `#e2e8f0` (construction `#dce6ed`) |
| Border strong | `--wap-border-strong` | `#cbd5e1` |
| Danger / stage fallback | — | `#c22945` |

Rules: accent is for primary actions, active nav and key figures only. Status
colours (green / amber / red) must also carry a text label or icon — never
colour alone. Text on surfaces ≥ 4.5:1 contrast (`--wap-text-mute` is the
lightest allowed body text).

## 3. Typography
- Family: `Inter, "Segoe UI", system-ui, sans-serif` (construction screens);
  shell default is Instrument Sans. Do not load new web fonts from Wood Art views.
- Page title: 23px / 750 / letter-spacing −0.03em.
- Section title: 15–16px / 650. Body: 13–14px / 400. Table: 13px.
- Small label / chip: 11–12px / 600, uppercase only for tiny column heads.
- Numbers in tables and KPIs: `font-variant-numeric: tabular-nums`, right-aligned.
- Money: `৳` prefix, thousands separators, 2 decimals where it is a ledger figure.

## 4. Spacing, radius, elevation
- Spacing scale (px): 4 · 8 · 12 · 16 · 24 · 32. Card padding 16–20.
- Radius: controls 8px, cards 12px, chips 999px.
- Card shadow: `0 2px 7px rgba(15,45,63,.04)` (construction) or
  `0 1px 2px rgba(5,22,80,.05), 0 5px 14px -4px rgba(5,22,80,.08)`.
  Modals may go one step up; nothing heavier.
- Borders over shadows for structure: 1px `--wap-border`.

## 5. Components
- **Button primary:** accent fill, white text, 36px high (min hit target 24px,
  44px on touch), radius 8. **Secondary:** white, 1px `--wap-border-strong`.
  **Danger:** only for destructive actions, always confirmed.
- **Inputs:** 36px high, 1px `--wap-border-strong`, focus ring 2px accent at
  ~35% alpha. State `box-sizing: border-box` explicitly.
- **Table:** sticky head on `--wap-surface-2`, row hover `--wap-surface-hi`,
  1px row dividers, zebra not used. Empty state says what to do next.
- **KPI tile:** one fact each, label above value, no redundancy
  (owner preference: fewer tiles, one row).
- **Status chip:** pill, tinted background + same-hue text + label.
- **Modal:** fixed frame, body scrolls, footer actions pinned; must work at
  1365×718 (laptop ≈ 620px viewport).
- **Icons:** Font Awesome / Bootstrap Icons already loaded by the shell. Use
  icons for actions, not as emoji tiles above headings.

## 6. Interaction & motion
- Every async action shows a loading state on its button and disables it.
- Transitions 120–200ms ease-out on colour/opacity/transform only.
- Respect `prefers-reduced-motion`.
- Never disable browser zoom.
- Navigation inside Wood Art swaps `[data-wa-view]` only — no layout shift or
  blink; keep scripts outside that region.

## 7. Anti-patterns (do not produce)
- Purple→blue gradient headings, glassmorphism, neon glows.
- Emoji or icon tiles above feature headings.
- Global selectors (`*`, `body`, `a`, `table`…) from Wood Art files.
- Hard-coded one-off hex values — add a token here first.
- Overflowing close (×) buttons or clipped last field in a modal grid row.

## 8. Checklist before finishing a UI change
1. Only Wood Art / new files touched; shared files edited behind a guard.
2. Tokens from this file; selectors `wa-` / `wap-` prefixed.
3. Viewed at 1365×718 and ≈ 390px; no overflow or overlap.
4. Assets re-published (`php artisan vendor:publish --tag=woodart-assets --force`)
   if `Modules/WoodArt/resources/assets/` changed.
