# Prof. Designs Guardian

Lightweight WordPress monitoring and maintenance system.

## Features
- Automatic WordPress core updates
- Automatic plugin updates
- Automatic theme updates
- Fatal PHP error monitoring
- Website health checks
- Smart email notifications
- Anti-flood protection
- MU plugin compatible
- Secure local-first architecture

## Philosophy
Guardian is designed around a simple principle:

- no remote control
- no external dependencies
- no unnecessary noise

If the website works correctly, Guardian stays silent.
If problems appear, Guardian notifies administrators intelligently.

## Optional wp-config.php Settings
```php
define('PROFDESIGNS_GUARDIAN_EMAIL', 'alerts@example.com');