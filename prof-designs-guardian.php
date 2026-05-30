<?php
    /**
     * Plugin Name: Prof Designs Guardian
     * Plugin URI: https://prof-designs.com/guardian
     * Description: A plugin that provides automatic updates, error handling, and health checks for your website.
    * Version: 1.0.0
     *
     * Author: Prof Designs
     * Author URI: https://profdesigns.com
     *
     * License: GPL3
     * License URI: https://www.gnu.org/licenses/gpl-3.0.html
     *
     * Requires PHP: 7.4
     *
     * @package ProfDesigns\Guardian
     * @since   0.10.0
     */

    declare( strict_types=1 );

    use ProfDesigns\Guardian\Application;
    use ProfDesigns\Guardian\Providers\SetupServiceProvider;
    use ProfDesigns\Guardian\Providers\SecurityServiceProvider;
    use ProfDesigns\Guardian\Providers\AutoUpdateServiceProvider;
    use ProfDesigns\Guardian\Providers\ErrorHandlerServiceProvider;
    use ProfDesigns\Guardian\Providers\HealthCheckServiceProvider;
    use ProfDesigns\Guardian\Providers\MailerServiceProvider;

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    // Define plugin constants
    define( 'PROF_GUARDIAN_VERSION', '1.0.0' );
    define( 'PROF_GUARDIAN_PLUGIN_FILE', __FILE__ );
    define( 'PROF_GUARDIAN_PLUGIN_DIR', __DIR__ );

    // Load Composer autoloader
    if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
        require_once __DIR__ . '/vendor/autoload.php';
    } else {
        // Fallback: Load classes manually if Composer hasn't been run
        spl_autoload_register( function ( $class ) {
            $prefix   = 'ProfDesigns\\Guardian\\';
            $base_dir = __DIR__ . '/src/';

            $len = strlen( $prefix );
            if ( strncmp( $prefix, $class, $len ) !== 0 ) {
                return;
            }

            $relative_class = substr( $class, $len );
            $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

            if ( file_exists( $file ) ) {
                require $file;
            }
        } );

        // Manually load helper functions
        require_once __DIR__ . '/src/helpers.php';
    }

    /**
     * Bootstrap the Guardian application
     *
     * @return Application
     */
    function prof_guardian_bootstrap(): Application {
        // Create application instance
        $app = Application::getInstance( __DIR__ );

        prof_guardian_log( '[Guardian] ============================================' );
        prof_guardian_log( '[Guardian] Bootstrapping Guardian v' . PROF_GUARDIAN_VERSION );
        prof_guardian_log( '[Guardian] ============================================' );

        // Register service providers
        $providers = [
            MailerServiceProvider::class,        // Mailer (no dependencies)
            SecurityServiceProvider::class,      // Security (no dependencies)
            AutoUpdateServiceProvider::class,    // Auto-updates (no dependencies)
            ErrorHandlerServiceProvider::class,  // Error handler (depends on Mailer)
            HealthCheckServiceProvider::class,   // Health check (depends on Mailer)
            SetupServiceProvider::class,         // Setup (depends on Security, Mailer)
        ];

        foreach ( $providers as $provider ) {
            $app->register( $provider );
            prof_guardian_log( '[Guardian] Registered: ' . $provider );
        }

        // Boot all service providers
        $app->boot();
        prof_guardian_log( '[Guardian] All service providers booted' );

        prof_guardian_log( '[Guardian] Bootstrap complete' );
        prof_guardian_log( '[Guardian] ============================================' );

        return $app;
    }

    // Bootstrap the plugin
    prof_guardian_bootstrap();
