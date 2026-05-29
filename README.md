# Prof. Designs Guardian

Lightweight WordPress monitoring and maintenance system built with modern Laravel-inspired architecture.

## Features
- Automatic WordPress core updates
- Automatic plugin updates
- Automatic theme updates
- Fatal PHP error monitoring
- Website health checks
- Smart email notifications
- Anti-flood protection
- File editor protection (blocks theme/plugin editors)
- Plugin/theme installation lockdown (optional)
- Upload security hardening
- Malicious file upload prevention
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

**By default (no configuration needed):**
- Auto-updates: **ENABLED**
- Manual changes: **BLOCKED**
- Email alerts: sent to site admin email

**To customize, add these constants to wp-config.php:**

```php
// Custom email for priority support alerts (optional)
// Default: site admin email from Settings → General
define('PROFDESIGNS_GUARDIAN_EMAIL', 'alerts@example.com');

// To DISABLE plugin/theme modification lock (when you need to install/update manually):
// Default: true (locked for security)
define('PROFDESIGNS_GUARDIAN_LOCK_MODS', false);

// To DISABLE automatic updates (not recommended):
// Default: true (auto-updates enabled)
define('PROFDESIGNS_GUARDIAN_AUTO_UPDATES', false);
```

**Security Protection Levels:**
- **File editing** - Always blocked (theme/plugin editors disabled)
- **Plugin/Theme modifications** - Blocked by default via `PROFDESIGNS_GUARDIAN_LOCK_MODS`
- **Automatic updates** - Enabled by default via `PROFDESIGNS_GUARDIAN_AUTO_UPDATES`

**Configuration Scenarios:**

1. **Maximum Security (Default)**
   ```php
   // No constants needed - both default to true
   // - Auto-updates: ENABLED
   // - Manual changes: BLOCKED
   ```

2. **Disable Auto-Updates, Keep Security**
   ```php
   define('PROFDESIGNS_GUARDIAN_AUTO_UPDATES', false);
   // - Auto-updates: DISABLED
   // - Manual changes: BLOCKED (you'll need LOCK_MODS=false to update manually)
   ```

3. **Allow Manual Changes, Keep Auto-Updates**
   ```php
   define('PROFDESIGNS_GUARDIAN_LOCK_MODS', false);
   // - Auto-updates: ENABLED
   // - Manual changes: ALLOWED
   ```

4. **Disable Everything**
   ```php
   define('PROFDESIGNS_GUARDIAN_AUTO_UPDATES', false);
   define('PROFDESIGNS_GUARDIAN_LOCK_MODS', false);
   // - Auto-updates: DISABLED
   // - Manual changes: ALLOWED
   ```

When `PROFDESIGNS_GUARDIAN_LOCK_MODS` is `true` (default), no admin user can:
- Install plugins from repository or upload .zip files
- Install or upload themes
- Delete plugins or themes
- Manually update plugins or themes via admin dashboard

When locked, the UI elements for these actions are automatically hidden to avoid confusion.

This provides the same protection as `DISALLOW_FILE_MODS` but can be temporarily disabled for maintenance without deactivating the plugin.

### Temporarily Enable Manual Changes
When you need to manually install/update plugins or themes:

1. Add to `wp-config.php`:
   ```php
   define('PROFDESIGNS_GUARDIAN_LOCK_MODS', false);
   ```
2. Perform your maintenance work
3. Remove the line or set it back to `true`

The changes take effect automatically on the next admin page load. No need to deactivate the plugin.

## Installation as Must-Use Plugin

To ensure Guardian is always active and cannot be accidentally deactivated, install it as a Must-Use (MU) plugin.

### Steps:
1. Upload the `prof-designs-guardian` folder to `wp-content/mu-plugins/`
2. Upload the `prof-designs-guardian-loader.php` file to `wp-content/mu-plugins/`
3. (Optional) Run `composer install --no-dev` for optimized autoloading

**Note**: Composer is optional. The plugin includes a fallback autoloader that works without Composer.

The loader file is included in the plugin package for convenience.

## Architecture (v0.10.0+)

Guardian uses a modern Laravel/Sage-inspired architecture with:

- **Service Providers**: Modular bootstrapping of features
- **Dependency Injection**: Container-based service resolution (use closures for complex dependencies)
- **PSR-4 Autoloading**: Modern namespace-based class loading
- **Application Container**: Centralized service management
- **Single Responsibility**: Each service handles one specific concern

### For Developers

If you're extending Guardian or integrating it with other plugins:

```php
// Access the application container
$app = guardian();

// Resolve services
$security = $app->make(\ProfDesigns\Guardian\Services\SecurityService::class);
$mailer = $app->make(\ProfDesigns\Guardian\Services\MailerService::class);

// Register custom services
 $app->singleton(MyCustomService::class, function ($app) {
     return new MyCustomService($app->make(SomeDependency::class));
 });
```