## Changelog

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

**Types of changes**
- `Added` for new features.
- `Changed` for changes in existing functionality.
- `Fixed` for any bug fixes.
- `Security` in case of vulnerabilities.
- `Deprecated` for soon-to-be removed features.
- `Removed` for now removed features.

## TODO

## 0.6.2 - 23.05.2026 (Production Testing)
- [ ] `Added` Logging for blocked file uploads (security monitoring)
- [ ] `Added` Logging for upload directory protection file creation
- [ ] `Added` Warnings when .htaccess or index.php creation fails
- [ ] `Improved` Capability restoration only runs when needed (performance optimization)

## 1.0.0 - 01.07.2026
- [x] `Added` Automatic WordPress core, plugin, and theme updates
- [x] `Added` Fatal PHP error monitoring with email notifications
- [x] `Added` Hourly website health checks with timeout protection
- [x] `Added` File editor protection (disables theme/plugin editors)
- [x] `Added` Plugin and theme installation lockdown (optional via wp-config.php)
- [x] `Added` Upload directory security hardening (.htaccess protection)
- [x] `Added` Malicious file upload prevention
- [x] `Added` Smart email alert throttling (prevents notification floods)
- [x] `Added` PROFDESIGNS_GUARDIAN_UPDATES constant to control auto-updates
- [x] `Added` PROFDESIGNS_GUARDIAN_LOCK_MODS constant to control manual modifications
- [x] `Added` PROFDESIGNS_GUARDIAN_EMAIL constant for custom alert email