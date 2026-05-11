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
     * This ensures the site stays current with security patches and bug fixes.
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
            add_filter( 'auto_update_plugin', '__return_true' );
            add_filter( 'auto_update_theme', '__return_true' );
            add_filter( 'auto_update_core', '__return_true' );
        }
    }