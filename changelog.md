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

## 1.1.0 - 27.06.2026
- [x] `Added` vendor directory to repository for Composer dependencies
- [x] `Changed` `LOCK_BLOCKED_CAPS` visibility from `protected` to `public`
- [x] `Changed` Capability migration key bumped to `prof_guardian_caps_restored_v3`
- [x] `Fixed` Log timezone mismatch on early-bootstrap `error_log` calls
- [x] `Fixed` Fatal `TypeError` in `filterPluginThemeUpdateEmail`
- [x] `Fixed` Success-only auto-update email suppression via `pre_wp_mail`
- [x] `Fixed` Administrator role missing theme and plugin modification caps after plugin removal
- [x] `Removed` Per-request auto-update log noise
- [x] `Removed` Recoverable error handler (`set_error_handler`)
- [x] `Removed` Dead `filterPluginUpdateEmail` and `filterThemeUpdateEmail` methods
- [x] `Removed` Unused `PROF_GUARDIAN_PLUGIN_FILE` constant
- [x] `Removed` `PROFDESIGNS_GUARDIAN_CAPTURE_DEPRECATED` and `PROFDESIGNS_GUARDIAN_LOG_THIRD_PARTY_WARNINGS` constants

## 1.0.0 - 01.06.2026
- `Added` Laravel/Sage-inspired architecture with service providers and dependency injection
- `Added` PSR-4 autoloading and modern namespaced plugin structure
- `Added` Automatic WordPress core, plugin, and theme updates
- `Added` Fatal PHP error monitoring with email notifications
- `Added` REST API health check endpoint with consecutive failure tracking
- `Added` HTTP 503 health-check response for unhealthy status
- `Added` File editor protection (disables theme/plugin editors)
- `Added` Plugin and theme installation lockdown with capability-level enforcement
- `Added` Upload directory security hardening and malicious file prevention
- `Added` Smart email alert throttling and auto-update filtering
- `Added` Performance optimizations (health checks, capability filters)
- `Added` Configuration constants and security logging notifications
