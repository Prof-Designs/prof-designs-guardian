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

## 0.9.0 - 24.05.2026 (Production Testing)
- [x] `Added` Lightweight REST API health check endpoint (`/wp-json/guardian/v1/health`)
- [x] `Changed` Health check now uses REST endpoint instead of full homepage load
- [x] `Changed` Health check timeout reduced from 10 seconds to 5 seconds
- [x] `Changed` One-time setup now only runs in admin or CLI context (prevents frontend role mutations)
- [x] `Fixed` Health check performance issue (reduced from ~2000ms to ~50-200ms)
- [x] `Fixed` Mailer defensive fallback - guard `add_action()`/`remove_action()` calls for fatal error scenarios
- [x] `Fixed` Added `wp_roles()` availability check before capability mutations
- [x] `Security` Fixed privilege escalation vulnerability in Site Health capability grants (now requires admin role)
- [x] `Improved` HealthCheck class structure - all health functionality self-contained
- [x] `Improved` Removed unnecessary instrumentation from capability filter (performance optimization)

## 1.0.0 - TBA
- [x] `Added` Automatic WordPress core, plugin, and theme updates
- [x] `Added` Fatal PHP error monitoring with email notifications
- [x] `Added` Hourly website health checks with timeout protection
- [x] `Added` File editor protection (disables theme/plugin editors)
- [x] `Added` Plugin and theme installation lockdown (optional via wp-config.php)
- [x] `Added` Upload directory security hardening (.htaccess protection)
- [x] `Added` Malicious file upload prevention
- [x] `Added` Smart email alert throttling (prevents notification floods)
- [x] `Added` PROFDESIGNS_GUARDIAN_AUTO_UPDATES constant to control auto-updates
- [x] `Added` PROFDESIGNS_GUARDIAN_LOCK_MODS constant to control manual modifications
- [x] `Added` PROFDESIGNS_GUARDIAN_EMAIL constant for custom alert email
- [x] `Added` Logging for blocked file uploads (security monitoring)
- [x] `Added` Logging for upload directory protection file creation
- [x] `Added` Warnings when .htaccess or index.php creation fails
