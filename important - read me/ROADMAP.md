# 🛣️ SimRacing League Manager – Legacy Roadmap

## ✅ Legacy 1.0 – Initial Release
> Core self-hosted simracing management app

- [x] Driver & team management
- [x] Race calendar & result tracking
- [x] Points system & live standings
- [x] Penalty system
- [x] Multi-league support
- [x] Admin dashboard

---

## 🔧 Legacy 1.1 – League Customization & Branding

### 🔹 1.1.0 – Brand & Identity
- [x] Custom league name/logo/color
- [x] Editable league homepage text
- [x] League welcome screen / summary

### 🔹 1.1.0a – Bugfixes ✅
- [x] The logo's `href` is no longer relative – links from `/admin/` pages no longer break the site

### 🔹 1.1.0b – Bugfixes 🚧
- [x] Logo uploads (and general file uploads) work  
- [x] Editing entries without changing uploads no longer shows an error in the upload field

### 🔹 1.1.1 – Flexible Points
- [x] Point system presets (F1, MotoGP, etc.)
- [x] Per-session point control (Quali/Sprint/Race)
- [x] Bonus points config (Fastest Lap, Pole, etc.)

### 🔹 1.1.2 – UI & Layout
- [x] Improved race calendar layout
- [x] Driver/team list filtering & search
- [x] Sidebar/menu restructuring

---

## 🔄 Legacy 1.2 – Automation & Tools

### 🔹 1.2.0 – Integrations
- [x] Discord webhook support
- [x] Google Calendar sync
- [x] Optional Discord bot for stat posting

### 🔹 1.2.0a – Security & Robustness Bugfixes ✅
- [x] Enhanced CSRF protection for admin forms
- [x] Improved input validation and sanitization across all endpoints
- [x] Discord integration error handling and robustness improvements
- [x] API security enhancements with rate limiting and proper error responses
- [x] Secure file upload validation and directory protection
- [x] Discord bot security improvements with rate limiting and input validation
- [x] Comprehensive error logging without exposing sensitive data
- [x] Database query security with proper PDO parameter binding
- [x] Enhanced configuration validation and secure defaults

### 🔹 1.2.1 – Exporting & Reporting ✅
- [x] CSV/PDF export for results, standings, penalties
- [x] Export version tracking
- [x] Comprehensive export management system with rate limiting
- [x] Secure file download with access control
- [x] API endpoints for programmatic data export
- [x] Export history and statistics tracking
- [x] Automatic cleanup of expired exports
- [x] Admin interface for export management

### 🔹 1.2.2 – Steward Logs & Race Management
- [x] Comprehensive steward note system for races
- [x] Detailed penalty logs with incident descriptions and justifications
- [x] Incident reporting with lap times and driver positions
- [x] Role-based permissions (Steward, Race Director, Administrator)
- [x] Steward decision history and appeal tracking
- [x] Race incident timeline with timestamps
- [x] Downloadable steward reports (PDF/CSV)
- [x] Integration with Discord for steward notifications
- [x] Photo/video evidence upload for incidents
- [x] Driver protest and appeal system
- [x] Steward committee voting on complex incidents
- [x] Race control dashboard for live race management

---

## 🛠️ Legacy 1.3 – Settings & Migration

### 🔹 1.3.0 – Global Config & Setup
- [x] Setup wizard
- [x] Modular feature toggles (qualifying, fantasy mode)
- [x] League-wide defaults

### 🔹 1.3.1 – Appearance & Themes
- [x] Theme switcher (Dark/Light/Custom CSS)
- [x] Translation-ready (i18n)
- [x] Customizable announcement bar

### 🔹 1.3.2 – Migration System
- [x] Export full league as `.zip` or `.json`
- [x] License & version metadata
- [x] Import preview for hosted system
- [x] Secure export tokens

---

## ✅ Legacy 1.4 – Final Polish & LTS

### 🔹 1.4.0 – Plugin Support
- [x] Basic plugin loader
- [x] Plugin events/hooks
- [x] "Lite Mode" option

### 🔹 1.4.1 – Admin Tools
- [x] Audit log (admin actions)
- [x] Database cleanup / season reset
- [x] Debug panel

### 🔹 1.4.2 – Final Migration Prep
- [x] Finalized export format & docs
- [x] Migration sandbox test
- [x] LTS patch release support

---

## 🌐 v2.0 – Hosted Platform (Q2 2026)

- [ ] Central hosted league portal
- [ ] Driver & league discovery system
- [ ] Hosted import from Legacy exports
- [ ] OAuth login (Discord, Google)
- [ ] Public profiles & applications
- [ ] League management UI
- [ ] League management UI
