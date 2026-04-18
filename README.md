# AgeWire — Age Verification Module for ProcessWire

A customizable age verification module for ProcessWire CMS. Supports four CSS frameworks, multiple themes, date picker mode, CSRF protection, and a two-column admin interface.

## Features

**Verification methods**
- Yes/No buttons — simple UX barrier, zero friction
- Date picker — separated MM/DD/YYYY fields with strict server-side validation, resists bots

**CSS frameworks**
- Vanilla CSS — fully self-contained, no external dependencies
- Tailwind CSS — utility-first with 13 colour themes and 4 animations
- Bootstrap 5 — integrates with your existing Bootstrap setup
- UIkit 3 — integrates with your existing UIkit setup

Each framework can optionally load its assets from CDN, or rely on what you already include in your templates.

**Tailwind themes**
Modern, Dark, Classic, Minimal, Gradient, Neon, Elegant, Corporate, Vibrant, Nature, Sunset, Ocean, Purple

**Tailwind animations**
Fade In, Slide Up, Zoom In, Bounce In

**International date formats**
- MM/DD/YYYY — American
- DD/MM/YYYY — European
- YYYY/MM/DD — ISO

**Security**
- CSRF-protected AJAX endpoint
- `ob_clean()` + `exit` termination — prevents page HTML leaking into JSON responses
- Strict date parsing with `DateTime::createFromFormat` — rejects relative strings like `"tomorrow"`
- Redirect URL validation — only `http://`, `https://`, or site-relative paths accepted
- Cookie name sanitized to safe characters only
- Cookies issued with `HttpOnly`, `SameSite: Lax`, and `Secure` (on HTTPS)
- Custom CSS sanitized against `</style>` injection

**Admin UI**
- Two-column layout: General | Framework & Theme (always visible)
- Modal Content — full-width row with all text fields in one line
- Date Picker | Agreement — side-by-side collapsed row
- Exclusions — full-width collapsed row, Templates | Pages side by side

## Requirements

- ProcessWire 3.x
- PHP 8.2 or higher
- Modern browser with JavaScript enabled

## Installation

1. Download the module
2. Place the `AgeWire` folder in `/site/modules/`
3. Go to **Modules** in the ProcessWire admin
4. Click **Refresh**
5. Find **AgeWire** and click **Install**

## Configuration

Navigate to **Modules → Site → AgeWire**.

### General Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Enabled | on | Enable/disable site-wide |
| Minimum Age | 18 | Required age to access content |
| Cookie Name | age_verified | Letters, numbers, `-`, `_` only |
| Cookie Lifetime | 2592000 | Duration in seconds (30 days) |
| Redirect URL | responsibility.org | Destination for underage users |

Cookie lifetime reference: 1 day = 86400 · 7 days = 604800 · 30 days = 2592000 · 90 days = 7776000

### Framework & Theme

| Setting | Description |
|---------|-------------|
| CSS Framework | Vanilla CSS, Tailwind, Bootstrap 5, or UIkit 3 |
| Load from CDN | Load framework assets from CDN. Hidden for Vanilla (no CDN needed) |
| Theme | Tailwind only — 13 colour themes |
| Animation | Tailwind only — modal entrance animation |
| Custom CSS | Injected after framework styles, works with any framework |

**CDN notes.** Tailwind uses the Play CDN which runs JIT compilation in the browser — suitable for development and low-traffic sites. For production, compile only the required classes into a static CSS file and load it from your template. Bootstrap and UIkit load from jsDelivr.

### Modal Content

| Field | Placeholder | Description |
|-------|-------------|-------------|
| Title | — | Modal heading |
| Body Text | `{age}` | Main message |
| Confirm Button | `{age}` | Text for the "I'm old enough" button |
| Deny Button | `{age}` | Text for the "I'm underage" button |

### Date Picker

| Setting | Description |
|---------|-------------|
| Show Date Picker | Switch from Yes/No buttons to date input |
| Date Format | MDY / DMY / YMD |
| Picker Label | Text shown above the date fields |
| Invalid Date Message | Shown when the entered date is not valid |
| Underage Message | Shown when the user is below minimum age. Use `{age}` |

### Terms & Privacy Agreement

| Setting | Description |
|---------|-------------|
| Show Agreement | Display agreement text and links at the bottom of the modal |
| Agreement Text | Text above the links |
| Privacy Policy URL | Link to your privacy page |
| Terms of Use URL | Link to your terms page |

### Exclusions

- **Excluded Templates** — skip verification on all pages using selected templates
- **Excluded Pages** — skip verification on specific pages by ID

Admin pages are always excluded automatically.

## Customization

### Custom CSS

The Custom CSS field accepts any CSS. It is injected after the framework styles so all selectors override defaults. Use `#aw-overlay` and `#aw-box` as root selectors:

```css
#aw-overlay > div {
    max-width: 560px;
}

#aw-box {
    font-family: 'Your Custom Font', sans-serif;
}
```

### Placeholders

Use `{age}` in any text field — Title, Body Text, Confirm Button, Deny Button, Underage Message — to insert the configured minimum age at render time.

## Security Notes

### What this module protects against

- **Bots** — date picker mode requires manual input per field with auto-advance; resists automated form submission
- **CSRF** — the AJAX endpoint validates ProcessWire's session CSRF token on every POST
- **JSON corruption** — `ob_clean()` discards any buffered output before the JSON response is sent, preventing page HTML from leaking into the response body
- **XSS via Custom CSS** — `</style` sequences are escaped before injection
- **Redirect abuse** — only `http://`, `https://`, and `/`-relative URLs are accepted as redirect targets
- **Date string injection** — birth date is parsed with `DateTime::createFromFormat('Y-m-d', ...)` and a round-trip check; relative strings are rejected

### What this module does NOT protect against

The Yes/No button mode is a **UX-only barrier**. Any user can bypass it by setting the cookie manually in their browser. This is an inherent limitation of all client-side age gates. Legal responsibility for accurate self-reporting lies with the user.

For stronger enforcement, use date picker mode and consider pairing it with server-side session tracking or a third-party age verification service.

### Debug output

All JavaScript logging is gated behind a `_debug` flag set from ProcessWire's `$config->debug`. No console output in production.

## Troubleshooting

**Modal doesn't appear**
- Check that the module is enabled
- Verify the current page/template is not in the exclusion lists
- Clear browser cookies and reload
- Make sure your template outputs a closing `</body>` tag — the module injects its markup before it

**Cookie not persisting**
- Check cookie lifetime value
- Verify server clock is accurate
- Confirm HTTPS is correctly configured if the Secure flag is expected

**Styling issues**
- If using Tailwind, ensure **Load from CDN** is on (or that you've compiled Tailwind classes manually)
- Use the Custom CSS field to override any styles
- Clear browser cache after changes

**JSON parse error in the browser console**
- This means the server is returning non-JSON after the verification POST. Common cause: another module or a debug bar injecting output before or after the JSON. The module uses `ob_clean()` to discard buffered output, but content printed *after* `sendJson()` (e.g. from a shutdown hook) can still corrupt the response. Disable debug/profiler tools and retry.

## License

Provided "as is" without warranty of any kind. Use at your own risk.

## Author

Maxim Alex

## Contributing

Issues and pull requests are welcome.

## Support

1. Check this README
2. Review the module settings page in the admin
3. Check ProcessWire forums
4. Open an issue on GitHub

---

Made with ❤️ for the ProcessWire Community
