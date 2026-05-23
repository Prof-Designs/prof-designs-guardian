<?php
    /**
     * Plugin Name: Prof Designs Guardian
     * Plugin URI: https://prof-designs.com/guardian
     * Description: A  plugin that provides automatic updates, error handling, and health checks for your website.
     * Version: 0.6.2
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

        error_log( '[Guardian] === INITIAL SETUP STARTING ===' );

        // Run one-time security setup
        error_log( '[Guardian] Setting up security capabilities...' );
        ProfDesigns\Guardian\Security::remove_editor_capabilities();

        error_log( '[Guardian] Protecting uploads directory...' );
        ProfDesigns\Guardian\Security::protect_uploads_directory();
        update_option( 'prof_guardian_uploads_setup', time(), false );

        // Schedule health checks
        if ( ! wp_next_scheduled( 'guardian_health_check' ) ) {
            error_log( '[Guardian] Scheduling hourly health checks...' );
            wp_schedule_event( time(), 'hourly', 'guardian_health_check' );
        }

        // Mark setup as complete
        update_option( 'prof_guardian_setup_done', true, false );

        // Send test email with Site Health summary
        ProfDesigns\Guardian\Mailer::send_test_email();

        error_log( '[Guardian] === INITIAL SETUP COMPLETE ===' );
    }

    // Run setup only on admin pages to avoid frontend overhead
    if ( is_admin() ) {
        add_action( 'admin_init', 'prof_designs_guardian_setup', 5 );
        add_action( 'admin_init', 'prof_designs_guardian_restore_capabilities', 3 );
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

        error_log( '[Guardian] Restoring install_plugins capability for Site Health compatibility...' );

        $admin_role = get_role( 'administrator' );
        if ( $admin_role && ! $admin_role->has_cap( 'install_plugins' ) ) {
            $admin_role->add_cap( 'install_plugins' );
            error_log( '[Guardian] Restored install_plugins to administrator role' );
        } else {
            error_log( '[Guardian] Administrator already has install_plugins capability' );
        }

        // Mark restoration as complete
        update_option( 'prof_guardian_caps_restored_v2', true, false );
        
        // Force capability update
        ProfDesigns\Guardian\Security::remove_editor_capabilities();
        
        error_log( '[Guardian] Capability restoration complete' );
    }

    // Initialize plugin components
    ProfDesigns\Guardian\Security::init();
    ProfDesigns\Guardian\AutoUpdates::init();
    ProfDesigns\Guardian\ErrorHandler::init();
    ProfDesigns\Guardian\HealthCheck::init();
