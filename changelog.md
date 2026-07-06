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

## 1.1.1 - 06.07.2026
- `Fixed` `auto_update_*` hooks registered at priority 999 to override plugin/theme opt-out filters
- `Fixed` `$item` never received by update callbacks; `add_filter` arg count corrected to 2
- `Fixed` `enablePluginUpdates`, `enableThemeUpdates`, `enableCoreUpdates` unconditionally return `true`
- `Added` `logAutoUpdateResults` on `automatic_updates_complete` — logs failed items only; silent on a clean run

## 1.1.0 - 27.06.2026
- `Added` vendor directory to repository for Composer dependencies
- `Changed` `LOCK_BLOCKED_CAPS` visibility from `protected` to `public`
- `Changed` Capability migration key bumped to `prof_guardian_caps_restored_v3`
- `Fixed` Log timezone mismatch on early-bootstrap `error_log` calls
- `Fixed` Fatal `TypeError` in `filterPluginThemeUpdateEmail`
- `Fixed` Success-only auto-update email suppression via `pre_wp_mail`
- `Fixed` Administrator role missing theme and plugin modification caps after plugin removal
- `Removed` Per-request auto-update log noise
- `Removed` Recoverable error handler (`set_error_handler`)
- `Removed` Dead `filterPluginUpdateEmail` and `filterThemeUpdateEmail` methods
- `Removed` Unused `PROF_GUARDIAN_PLUGIN_FILE` constant
- `Removed` `PROFDESIGNS_GUARDIAN_CAPTURE_DEPRECATED` and `PROFDESIGNS_GUARDIAN_LOG_THIRD_PARTY_WARNINGS` constants

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
