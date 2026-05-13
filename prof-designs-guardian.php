<?php
    /**
     * Plugin Name: Prof Designs Guardian
     * Plugin URI: https://prof-designs.com/guardian
     * Description: A  plugin that provides automatic updates, error handling, and health checks for your website.
     * Version: 0.3.0
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

    require_once __DIR__ . '/includes/Helpers.php';
    require_once __DIR__ . '/includes/Mailer.php';
    require_once __DIR__ . '/includes/Security.php';
    require_once __DIR__ . '/includes/AutoUpdates.php';
    require_once __DIR__ . '/includes/ErrorHandler.php';
    require_once __DIR__ . '/includes/HealthCheck.php';

    /**
     * One-time setup for MU plugin (runs on first load)
     *
     * @since 1.0.0
     */
    function prof_designs_guardian_setup() {
        // Check if setup has already been done
        if ( get_option( 'prof_guardian_setup_done' ) ) {
            return;
        }

        // Run one-time setup
        ProfDesigns\Guardian\Security::remove_editor_capabilities();
        ProfDesigns\Guardian\Security::protect_uploads_directory();
        update_option( 'prof_guardian_uploads_setup', time(), false );

        // Schedule health checks
        if ( ! wp_next_scheduled( 'guardian_health_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'guardian_health_check' );
        }

        // Mark setup as complete
        update_option( 'prof_guardian_setup_done', true, false );
    }

    add_action( 'init', 'prof_designs_guardian_setup' );

    // Initialize plugin components
    ProfDesigns\Guardian\Security::init();
    ProfDesigns\Guardian\AutoUpdates::init();
    ProfDesigns\Guardian\ErrorHandler::init();
    ProfDesigns\Guardian\HealthCheck::init();