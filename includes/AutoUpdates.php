<?php
    /**
     * Automatic updates manager for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    /**
     * Class AutoUpdates
     *
     * Enables automatic updates for WordPress core, plugins, and themes.
     * Can be disabled via PROFDESIGNS_GUARDIAN_AUTO_UPDATES constant.
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */
    class AutoUpdates {
        /**
         * Enable automatic updates
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function init(): void {
            // Check if auto-updates are enabled (defaults to true)
            $updates_enabled = defined( 'PROFDESIGNS_GUARDIAN_AUTO_UPDATES' ) ? PROFDESIGNS_GUARDIAN_AUTO_UPDATES : true;

            if ( ! $updates_enabled ) {
                prof_guardian_log( '[Guardian] Auto-updates disabled via PROFDESIGNS_GUARDIAN_AUTO_UPDATES constant' );

                return;
            }

            // Enable automatic updates
            add_filter( 'auto_update_plugin', '__return_true' );
            add_filter( 'auto_update_theme', '__return_true' );
            add_filter( 'auto_update_core', '__return_true' );

            // Disable WordPress default update notification emails
            // Guardian handles all update monitoring and notifications
            add_filter( 'auto_core_update_send_email', '__return_false' );
            add_filter( 'auto_plugin_update_send_email', '__return_false' );
            add_filter( 'auto_theme_update_send_email', '__return_false' );
            add_filter( 'send_core_update_notification_email', '__return_false' );
        }
    }