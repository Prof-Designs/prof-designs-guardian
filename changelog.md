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

## 1.0.0 - 30.05.2026 (Production Testing)
- [x] `Added` Laravel/Sage-inspired architecture with service providers and dependency injection
- [x] `Added` PSR-4 autoloading and modern namespaced plugin structure
- [x] `Added` Automatic WordPress core, plugin, and theme updates
- [x] `Added` Fatal PHP error monitoring with email notifications
- [x] `Added` REST API health check endpoint with consecutive failure tracking
- [x] `Added` HTTP 503 health-check response for unhealthy status
- [x] `Added` File editor protection (disables theme/plugin editors)
- [x] `Added` Plugin and theme installation lockdown with capability-level enforcement
- [x] `Added` Upload directory security hardening and malicious file prevention
- [x] `Added` Smart email alert throttling and auto-update filtering
- [x] `Added` Performance optimizations (health checks, capability filters)
- [x] `Added` Configuration constants and security logging notifications
