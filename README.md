# AgeWire - Age Verification Module for ProcessWire

A modern, customizable age verification module for ProcessWire CMS with Tailwind CSS support and multiple themes.

## 📋 Features

- ✅ **Multiple Verification Methods**
  - Simple Yes/No buttons
  - Detailed date picker with separated MM/DD/YYYY inputs
  
- 🎨 **13 Beautiful Themes**
  - Modern, Dark, Classic, Minimal
  - Gradient, Neon, Elegant, Corporate
  - Vibrant, Nature, Sunset, Ocean, Purple

- 🌍 **International Date Formats**
  - MM/DD/YYYY (American)
  - DD/MM/YYYY (European)
  - YYYY/MM/DD (ISO)

- 🎭 **4 Animation Styles**
  - Fade In
  - Slide Up
  - Zoom In
  - Bounce In

- 🔒 **Security Features**
  - Cookie-based verification
  - Configurable cookie lifetime
  - Bot protection with date input fields
  - Secure cookie with HttpOnly and SameSite

- ⚙️ **Advanced Configuration**
  - Custom redirect URLs
  - Template and page exclusions
  - Customizable texts and placeholders
  - Terms & Privacy Policy links
  - Custom CSS support

- 📱 **Responsive Design**
  - Mobile-friendly interface
  - Tailwind CSS powered
  - Clean and modern UI

## 📦 Requirements

- ProcessWire 3.x
- PHP 7.4 or higher
- Modern web browser with JavaScript enabled

## 🚀 Installation

1. Download the module
2. Place the `AgeWire` folder in `/site/modules/`
3. Go to Modules in the ProcessWire admin
4. Click "Refresh" to detect the new module
5. Find "AgeWire" and click "Install"

## ⚙️ Configuration

Navigate to **Modules** → **Site** → **AgeWire** to configure the module.

### General Settings

| Setting | Default | Description |
|---------|---------|-------------|
| **Enabled** | ✓ | Enable/disable age verification |
| **Minimum Age** | 18 | Required age to access content |
| **Cookie Name** | age_verified | Name of verification cookie |
| **Cookie Lifetime** | 2592000 (30 days) | Duration in seconds |

**Cookie Lifetime Examples:**
- 1 day = 86400
- 7 days = 604800
- 14 days = 1209600
- 30 days = 2592000 ✓
- 90 days = 7776000
- 180 days = 15552000

### Content Settings

Customize all modal texts:
- Modal title
- Main message (use `{age}` placeholder)
- Confirm button text
- Deny button text
- Redirect URL for underage users

### Date Picker Settings

| Setting | Options | Description |
|---------|---------|-------------|
| **Show Date Picker** | On/Off | Enable detailed date input |
| **Date Format** | mdy, dmy, ymd | Choose regional format |
| **Date Picker Text** | Customizable | Label above date fields |
| **Invalid Date Text** | Customizable | Error message |
| **Underage Text** | Customizable | Message for underage users |

### Terms & Privacy Agreement

| Setting | Default | Description |
|---------|---------|-------------|
| **Show Agreement** | ✓ | Display terms at bottom |
| **Agreement Text** | Customizable | Text above links |
| **Privacy Policy URL** | /privacy-policy/ | Link to privacy page |
| **Terms of Use URL** | /terms-of-use/ | Link to terms page |

### Exclusion Settings

- **Excluded Templates**: Select templates to skip verification
- **Excluded Pages**: Select specific pages to skip verification

### Theme Settings

**Available Themes:**

| Theme | Description |
|-------|-------------|
| **Modern** | Clean blue design (default) |
| **Dark** | Pure black with zinc accents |
| **Classic** | Traditional blue style |
| **Minimal** | Simple monochrome |
| **Gradient** | Purple to pink gradient |
| **Neon** | Cyberpunk cyan glow |
| **Elegant** | Sophisticated slate tones |
| **Corporate** | Professional indigo |
| **Vibrant** | Orange and pink energy |
| **Nature** | Fresh green tones |
| **Sunset** | Warm orange to red |
| **Ocean** | Cool blue to cyan |
| **Purple** | Rich purple theme |

**Animation Styles:**
- Fade In (smooth appearance)
- Slide Up (from bottom)
- Zoom In (scale effect)
- Bounce In (playful bounce)

**Framework Settings:**
- Load External CSS (Tailwind from CDN) - recommended
- Custom CSS field for additional styling

## 🎯 Usage Examples

### Basic Setup (Yes/No Buttons)

1. Enable the module
2. Set minimum age (e.g., 18)
3. Choose a theme (e.g., Modern)
4. Save configuration

### Advanced Setup (Date Picker)

1. Enable "Show Date Picker"
2. Select date format for your region
3. Customize date picker text
4. Enable Terms & Privacy Agreement
5. Set your privacy policy and terms URLs
6. Choose theme and animation
7. Save configuration

### Exclude Specific Pages

1. Go to Exclusion Settings
2. Select templates or pages to exclude
3. Admin pages are automatically excluded

## 📸 Screenshots

*Add screenshots of different themes here*

## 🔧 Customization

### Custom CSS

Add your own CSS in the Custom CSS field:

```css
/* Example: Change modal width */
#age-verification-overlay > div {
    max-width: 600px;
}

/* Example: Custom font */
#age-verification-overlay {
    font-family: 'Your Custom Font', sans-serif;
}
```

### Using Placeholders

Use `{age}` placeholder in texts to dynamically insert the minimum age:

```
You must be {age} years or older to view this content.
```

Result: "You must be 18 years or older to view this content."

## 🌐 Internationalization

### Date Formats by Region

| Region | Format | Example |
|--------|--------|---------|
| USA, Canada | MM/DD/YYYY | 12/31/2000 |
| Europe, Latin America | DD/MM/YYYY | 31/12/2000 |
| Asia, ISO Standard | YYYY/MM/DD | 2000/12/31 |

### Customizing Texts

All texts are fully customizable through the module settings. Simply translate them to your language:

```
Modal Title: "Bitte bestätige dein Alter"
Modal Text: "Du musst mindestens {age} Jahre alt sein."
Confirm Button: "Ich bin {age} oder älter"
```

## 🔒 Security Features

### Cookie Security

The module uses secure cookies with:
- **HttpOnly**: Prevents JavaScript access
- **SameSite**: Protects against CSRF attacks
- **Secure flag**: Uses HTTPS when available
- **Custom lifetime**: Configure duration

### Bot Protection

Date picker mode provides enhanced bot protection:
- Separated input fields
- Auto-validation
- Manual date entry required
- No browser autofill

## 🐛 Troubleshooting

### Modal doesn't appear

1. Check if module is enabled
2. Verify page/template is not excluded
3. Clear browser cookies
4. Check browser console for JavaScript errors

### Cookie not persisting

1. Check cookie lifetime setting
2. Verify server time is correct
3. Ensure HTTPS is properly configured
4. Check browser privacy settings

### Styling issues

1. Enable "Load External CSS" option
2. Check for CSS conflicts
3. Use Custom CSS to override styles
4. Clear browser cache

## 📝 Changelog

### Version 1.0.9
- ✨ Added Terms & Privacy Agreement section
- 🔗 Configurable privacy policy and terms links
- 🎨 Agreement styling for all themes

### Version 1.0.8
- 🌍 Added international date format support
- 📅 Three date formats: MDY, DMY, YMD
- 🔧 Dynamic field reordering

### Version 1.0.7
- 📊 Split date input (MM/DD/YYYY)
- ⚡ Auto-focus navigation
- 🎯 Centered content layout
- 🍪 Cookie lifetime examples

### Version 1.0.6
- 🎨 Full Tailwind CSS color system
- 🖌️ 13 unique themes
- 🎭 Removed custom color settings

### Version 1.0.5
- 🚀 Initial public release

## 📄 License

This module is provided "as is" without warranty of any kind. Use at your own risk.

## 👨‍💻 Author

**Maxim Alex**

## 🤝 Contributing

Issues and pull requests are welcome!

## 💬 Support

For support, please:
1. Check this README
2. Review module settings
3. Check ProcessWire forums
4. Open an issue on GitHub

## ⭐ Show Your Support

If you find this module useful, please give it a star!

---

**Made with ❤️ for ProcessWire Community**