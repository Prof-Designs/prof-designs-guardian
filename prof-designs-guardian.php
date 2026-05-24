<?php
    /**
     * Plugin Name: Prof Designs Guardian
     * Plugin URI: https://prof-designs.com/guardian
     * Description: A plugin that provides automatic updates, error handling, and health checks for your website.
     * Version: 0.8.2
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
     * @since   1.0.0
     */

    declare( strict_types=1 );

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Safe error logging that won't cause output
     *
     * @param string $message Log message
     *
     * @return void
     */
    function prof_guardian_log( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( $message );
        }
    }

    require_once __DIR__ . '/includes/Helpers.php';
    require_once __DIR__ . '/includes/Mailer.php';

    require_once __DIR__ . '/includes/Security.php';
    require_once __DIR__ . '/includes/AutoUpdates.php';
    require_once __DIR__ . '/includes/ErrorHandler.php';
    require_once __DIR__ . '/includes/HealthCheck.php';

    /**
     * One-time setup for MU plugin (runs on first load only)
     *
     * @since 1.0.0
     */
    function prof_designs_guardian_setup() {
        // Check if setup has already been done
        if ( get_option( 'prof_guardian_setup_done' ) ) {
            return;
        }

        prof_guardian_log( '[Guardian] === INITIAL SETUP STARTING ===' );

        // Run one-time security setup
        prof_guardian_log( '[Guardian] Setting up security capabilities...' );
        ProfDesigns\Guardian\Security::remove_editor_capabilities();

        prof_guardian_log( '[Guardian] Protecting uploads directory...' );
        ProfDesigns\Guardian\Security::protect_uploads_directory();
        update_option( 'prof_guardian_uploads_setup', time(), false );

        // Mark setup as complete
        update_option( 'prof_guardian_setup_done', true, false );

        // Schedule test email to run in 60 seconds (async to avoid blocking this request)
        wp_schedule_single_event( time() + 60, 'guardian_send_test_email' );
        prof_guardian_log( '[Guardian] Scheduled test email to send in 60 seconds' );

        prof_guardian_log( '[Guardian] === INITIAL SETUP COMPLETE ===' );
    }

    // Run setup on init to ensure it works even on sites without admin page loads
    add_action( 'init', 'prof_designs_guardian_setup', 5 );

    // Run capability restoration only on admin pages (admin-specific operation)
    if ( is_admin() ) {
        // Only register restoration if it hasn't been done yet (optimization)
        if ( ! get_option( 'prof_guardian_caps_restored_v2' ) ) {
            add_action( 'admin_init', 'prof_designs_guardian_restore_capabilities', 3 );
        }
    }

    /**
     * One-time capability restoration for Site Health fix
     *
     * Restores install_plugins to administrator role if it was previously removed
     * This fixes the "Sorry, you are not allowed" error on Site Health page
     *
     * @since 0.6.1
     */
    function prof_designs_guardian_restore_capabilities() {
        // Check if restoration has already been done
        if ( get_option( 'prof_guardian_caps_restored_v2' ) ) {
            return;
        }

        prof_guardian_log( '[Guardian] Restoring install_plugins capability for Site Health compatibility...' );

        $admin_role = get_role( 'administrator' );
        if ( $admin_role && ! $admin_role->has_cap( 'install_plugins' ) ) {
            $admin_role->add_cap( 'install_plugins' );
            prof_guardian_log( '[Guardian] Restored install_plugins to administrator role' );
        } else {
            prof_guardian_log( '[Guardian] Administrator already has install_plugins capability' );
        }

        // Mark restoration as complete
        update_option( 'prof_guardian_caps_restored_v2', true, false );

        // Force capability update
        ProfDesigns\Guardian\Security::remove_editor_capabilities();

        prof_guardian_log( '[Guardian] Capability restoration complete' );
    }

    /**
     * Send test email via cron (async)
     *
     * @return void
     *
     * @since 0.7.0
     */
    function prof_designs_guardian_send_test_email() {
        ProfDesigns\Guardian\Mailer::send_test_email();
    }

    // Register test email cron handler
    add_action( 'guardian_send_test_email', 'prof_designs_guardian_send_test_email' );

    // Initialize plugin components
    ProfDesigns\Guardian\Security::init();
    ProfDesigns\Guardian\AutoUpdates::init();
    ProfDesigns\Guardian\ErrorHandler::init();
    ProfDesigns\Guardian\HealthCheck::init();
