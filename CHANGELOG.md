# Changelog

All notable changes to AgeWire are documented in this file.
Entries are in reverse chronological order (newest first).

---

## 1.2.2

### Changed
- Admin config UI restructured into a two-column layout using `columnWidth`
- **Row 1** (always open): General Settings 50% | Framework & Theme 50%
- **Row 2** (collapsed): Modal Content full-width — Title 30% · Body Text 40% · Confirm 15% · Deny 15% in one horizontal line
- **Row 3** (collapsed): Date Picker 50% | Terms & Privacy Agreement 50%
- **Row 4** (collapsed): Exclusions full-width — Excluded Templates 50% · Excluded Pages 50%
- Redirect URL moved into General Settings (was duplicated in Modal Content)
- `$modules` variable renamed to `$m` for brevity inside `getModuleConfigInputfields()`
- Custom CSS textarea reduced to 3 rows (was 5) to balance column heights in row 1

---

## 1.2.1

### Fixed
- **AJAX JSON corruption** — `$event->cancelAction = true` on a `Before` hook for
  `ProcessPageView::execute` does not prevent ProcessWire from rendering the full
  page after the hook returns. The rendered HTML was appended to the output buffer
  after the JSON payload, causing `JSON.parse` to fail on the client with
  "unexpected non-whitespace character after JSON data".
  Fix: replaced the `cancelAction` pattern with a dedicated `sendJson()` helper
  that calls `ob_clean()` (discards any buffered output) followed by `exit`.
  This matches how ProcessWire's own Ajax handlers (FormBuilder, etc.) terminate.
- `processAgeVerification()` no longer accepts a `HookEvent` parameter (unused after removing `cancelAction`)
- `sendJson()` declared with `never` return type (PHP 8.1+) — reflects that it always terminates execution

---

## 1.2.0

### Added
- Multi-framework support: Vanilla CSS, Tailwind CSS, Bootstrap 5, UIkit 3
- `css_framework` config field — choose the framework per-install
- `load_cdn` checkbox — load the selected framework from CDN, or rely on what your templates already include
- Bootstrap renderer: uses `card`, `btn`, `form-control`, `d-grid`, `alert`, `border-top` utilities
- UIkit renderer: uses `uk-card`, `uk-button`, `uk-text-*`, `uk-alert-danger`, `uk-grid`
- Vanilla renderer: fully self-contained CSS injected inline, zero dependencies
- CDN constants for Bootstrap (jsDelivr) and UIkit (jsDelivr + icons bundle)
- `requires => PHP>=8.2` declared in `getModuleInfo()`

### Changed
- `load_external_css` (Tailwind-only) replaced by `load_cdn` (applies to all frameworks)
- `theme_style` and `animation_style` fields now have `showIf: css_framework=tailwind`
- `load_cdn` field has `showIf: css_framework!=vanilla` (Vanilla has no CDN)
- All PHP array syntax migrated to short `[]` form
- All method signatures use PHP 8.x typed return types (`void`, `bool`, `string`, `array`)
- `catch (\Exception $e)` replaced with `catch (\Exception)` (PHP 8.0 unnamed catch)
- `strpos()` + `substr()` replaced with `str_contains()` / `str_starts_with()` where applicable
- `getThemeClasses()`, `getAnimationClass()`, `getDatePickerFields()` refactored to `match` expressions
- Shared JS extracted to `getSharedJs()` — used by all four renderers
- All four renderers use unified element IDs: `#aw-overlay`, `#aw-confirm`, `#aw-deny`, `#aw-d1`, `#aw-d2`, `#aw-d3`, `#aw-error`
- Module summary updated to reflect multi-framework support

---

## 1.1.0

### Security
- Added CSRF token validation on the AJAX verification endpoint — all POST requests now require a valid ProcessWire session token
- Replaced `new \DateTime($input)` with `\DateTime::createFromFormat('Y-m-d', ...)` and strict round-trip check — relative strings like `"tomorrow"` or `"-18 years"` are rejected
- Added `getSafeRedirectUrl()` — only `http://`, `https://`, or site-relative paths accepted; arbitrary schemes blocked
- Added `getSafeCookieName()` — cookie name sanitized to `[a-zA-Z0-9_\-]` characters only
- CSRF token injected into frontend JavaScript so all fetch requests carry it automatically

### Fixed
- Replaced bare `exit` in AJAX handler with `$event->cancelAction = true` + `$event->return = ''`
- Replaced `addHookAfter('Page::render', ...)` (fires on every nested render) with a single `addHookBefore('ProcessPageView::execute', ...)` entry point; the `Page::render` hook is registered only when overlay injection is needed
- XSS via Custom CSS field — `</style` sequences now escaped before injection into the `<style>` block

### Improved
- All `console.log` / `console.error` calls gated behind `_debug` flag from `$config->debug` — no console noise in production
- Missing `</body>` tag now emits `$log->warning()` in debug mode instead of silently skipping injection
- Tailwind CDN description updated to accurately describe Play CDN limitations

### Documentation
- Added Security Notes section to README
- Clarified that Yes/No button mode is a UX-only barrier
- Moved all version history to CHANGELOG

---

## 1.0.9

### Added
- Terms & Privacy Agreement section in the modal
- Configurable privacy policy and terms of use links
- Agreement styling adapted for all 13 themes

---

## 1.0.8

### Added
- International date format support: MDY (American), DMY (European), YMD (ISO)
- Dynamic field reordering in the date picker based on selected format

---

## 1.0.7

### Added
- Split date input fields (MM / DD / YYYY)
- Auto-focus navigation between date fields
- Centered modal content layout
- Cookie lifetime examples in the admin description

---

## 1.0.6

### Added
- Full Tailwind CSS color system
- 13 unique visual themes

### Removed
- Custom color picker settings (replaced by theme presets)

---

## 1.0.5

### Added
- Initial public release
