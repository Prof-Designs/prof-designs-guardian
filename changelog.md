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

## 1.1.0 - 25.06.2026
- [x] `Fixed` Log timezone mismatch caused by early-bootstrap `error_log` call firing before plugins set `date.timezone`
- [x] `Fixed` Fatal `TypeError` in `filterPluginThemeUpdateEmail`
- [x] `Removed` Per-request auto-update log noise (`Auto-updates disabled/enabled` messages fired on every page load)
- [x] `Removed` Dead `filterPluginUpdateEmail` and `filterThemeUpdateEmail` methods 
- [x] `Removed` Unused `PROF_GUARDIAN_PLUGIN_FILE` constant

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
