# حلل المعرفة — Design System

نظام تصميم مستخرج من `public_html` جاهز للاستخدام في أي مشروع.

## الهيكل

```
halal-design-system/
├── assets/logos/       # الشعارات (PNG + SVG)
├── css/
│   ├── halal-ds.css    # ← ملف الاستيراد الرئيسي
│   ├── fonts.css
│   ├── tokens-light.css
│   ├── tokens-dark.css
│   ├── base.css
│   ├── layout.css
│   └── components.css
├── js/
│   └── survey-theme.json   # ثيم SurveyJS الداكن
└── examples/
    └── showcase.html       # معاينة المكونات
```

## الاستخدام السريع

```html
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <link rel="stylesheet" href="path/to/css/halal-ds.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <nav class="ds-navbar">...</nav>
  <button class="ds-btn ds-btn-primary">حفظ</button>
</body>
</html>
```

## الثيمات

| الثيم | التفعيل | الاستخدام |
|-------|---------|-----------|
| فاتح (افتراضي) | بدون attribute | منصة، فواتير، QR |
| داكن | `data-theme="dark"` على `<html>` | نماذج، SurveyJS |

```html
<html lang="ar" dir="rtl" data-theme="dark">
```

## الشعارات

| الملف | الاستخدام |
|-------|-----------|
| `logo.png` | خلفيات فاتحة |
| `logo-white.png` | خلفيات داكنة / تدرجات |
| `logo-white-alt.png` | بديل أبيض |
| `logo.svg` | متجه قابل للتكبير |

```html
<img src="assets/logos/logo.png" class="ds-logo-img" alt="حلل المعرفة">
```

## المكونات الرئيسية

### Layout
- `ds-navbar`, `ds-sidebar`, `ds-content`, `ds-topbar`
- `ds-main-layout`, `ds-app`, `ds-login-page`

### Buttons
- `ds-btn ds-btn-primary`
- `ds-btn ds-btn-outline`
- `ds-btn ds-btn-teal`
- `ds-btn ds-btn-danger`
- `ds-btn ds-btn-sm`

### Cards
- `ds-card`, `ds-stat-card`, `ds-app-card`
- `ds-cards-grid`, `ds-stats-grid`

### Forms
- `ds-form-group`, `ds-input`, `ds-search-box`
- `ds-login-card`, `ds-login-button`

### Feedback
- `ds-alert ds-alert-success|error|warning`
- `ds-badge ds-badge-paid|pending|code`

## SurveyJS

```javascript
import theme from './js/survey-theme.json';
survey.applyTheme(theme);
```

## CSS Variables

```css
var(--ds-primary)      /* #005c7b */
var(--ds-secondary)    /* #40baac */
var(--ds-text)         /* #2a3f5f */
var(--ds-radius)       /* 14px */
var(--ds-gradient-brand)
```

## المعاينة

افتح `examples/showcase.html` في المتصفح.

## النسخ لمشروع آخر

```bash
cp -r halal-design-system/css ./your-project/public/css/
cp -r halal-design-system/assets ./your-project/public/
```

أو انسخ المجلد بالكامل كـ submodule.
