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
- [ ] 'update now' hide on plugins list

## 0.10.0 - 26.05.2026 (Laravel Architecture Refactor)
- [x] `Changed` Complete architectural refactoring with Laravel/Sage-inspired patterns
- [x] `Added` PSR-4 autoloading with Composer support
- [x] `Added` Application container with dependency injection
- [x] `Added` Service provider pattern for modular bootstrapping
- [x] `Added` Dedicated service classes with single responsibility
- [x] `Changed` Refactored all features into injectable services
- [x] `Changed` Modern namespaced architecture (ProfDesigns\Guardian)
- [x] `Added` Comprehensive architecture documentation
- [x] `Changed` Improved testability with dependency injection
- [x] `Changed` Better code organization and separation of concerns
- [x] `Security` Maintained all security features from 1.0.0
- [x] `Changed` PHP 7.4+ compatibility maintained throughout

### Service Providers Added
- SecurityServiceProvider - Security hardening features
- AutoUpdateServiceProvider - Automatic update management
- ErrorHandlerServiceProvider - Error monitoring and notifications
- HealthCheckServiceProvider - REST API health monitoring
- MailerServiceProvider - Email notification handling
- SetupServiceProvider - One-time initialization tasks

### Services Architecture
- SecurityService - Centralized security operations
- AutoUpdateService - Update management with email filtering
- ErrorHandlerService - Fatal and recoverable error handling
- HealthCheckService - Comprehensive health monitoring
- MailerService - Email delivery with throttling

## 1.0.0 - 24.05.2026 (Production Testing)
- [x] `Added` Automatic WordPress core, plugin, and theme updates
- [x] `Added` Fatal PHP error monitoring with email notifications
- [x] `Added` REST API health check endpoint with consecutive failure tracking
- [x] `Added` SSL verification fallback and timeout protection
- [x] `Added` File editor protection (disables theme/plugin editors)
- [x] `Added` Plugin and theme installation lockdown
- [x] `Added` Upload directory security hardening and malicious file prevention
- [x] `Added` Smart email alert throttling and auto-update filtering
- [x] `Added` Security fixes (privilege escalation, log injection, information disclosure)
- [x] `Added` Performance optimizations (health checks, capability filters)
- [x] `Added` Configuration constants (AUTO_UPDATES, LOCK_MODS, EMAIL)
- [x] `Added` Security logging and warning notifications
