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

## 0.8.0 - 24.05.2026 (UI/UX Improvements)
- [x] `Changed` Renamed `PROFDESIGNS_GUARDIAN_UPDATES` to `PROFDESIGNS_GUARDIAN_AUTO_UPDATES` for clarity
- [x] `Added` UI hiding when `PROFDESIGNS_GUARDIAN_LOCK_MODS` is enabled (hides "Add New" buttons, upload sections, and update links)
- [x] `Added` Menu item removal for "Add New" under Plugins and Themes when modifications are locked
- [x] `Added` Direct page access blocking for plugin-install.php and theme-install.php pages
- [x] `Added` Support email display in "Modifications Locked" error messages for easier contact
- [x] `Added` `Helpers::get_support_email()` method to retrieve configured support email
- [x] `Added` Admin bar update count removal when modifications are locked (prevents confusion)
- [x] `Improved` Admin interface now clearly reflects locked state - elements you can't use are hidden
- [x] `Security` Removed delete action links from plugins and themes when modifications are locked

**Breaking Change:** If you're using `PROFDESIGNS_GUARDIAN_UPDATES` constant in your `wp-config.php`, rename it to `PROFDESIGNS_GUARDIAN_AUTO_UPDATES`. The old constant name will no longer work.

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
- [x] `Added` Logging for blocked file uploads (security monitoring)
- [x] `Added` Logging for upload directory protection file creation
- [x] `Added` Warnings when .htaccess or index.php creation fails