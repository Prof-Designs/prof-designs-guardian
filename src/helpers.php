<?php
    /**
     * Helper functions for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     */

    declare( strict_types=1 );

    if ( ! function_exists( 'prof_guardian_log' ) ) {
        /**
         * Safe error logging that won't cause output
         *
         * @param string $message Log message
         *
         * @return void
         */
        function prof_guardian_log( string $message ): void {
            if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
                return;
            }

            // Suppress routine per-request bootstrap noise by default.
            $suppressed_patterns = [
                'Bootstrapping Guardian v',
                'Registered: ProfDesigns\\Guardian\\Providers\\',
                'All service providers booted',
                'Bootstrap complete',
                'File editors disabled',
                'Auto-updates enabled',
                'Error handler initialized',
                'Health check initialized',
                '================================',
            ];

            foreach ( $suppressed_patterns as $pattern ) {
                if ( strpos( $message, $pattern ) !== false ) {
                    return;
                }
            }

            error_log( $message );
        }
    }

    if ( ! function_exists( 'guardian' ) ) {
        /**
         * Get the Guardian application instance
         *
         * @return \ProfDesigns\Guardian\Application
         */
        function guardian(): \ProfDesigns\Guardian\Application {
            return \ProfDesigns\Guardian\Application::getInstance();
        }
    }
