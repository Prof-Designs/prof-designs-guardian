<?php
    /**
     * Fatal error handler for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    /**
     * Class ErrorHandler
     *
     * Monitors and reports fatal PHP errors with intelligent alert throttling
     * to prevent notification flooding.
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */
    class ErrorHandler {
        /**
         * Register shutdown handler
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function init(): void {
            register_shutdown_function( [ self::class, 'handleFatal' ] );
        }

        /**
         * Handle fatal errors and send alerts
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function handleFatal(): void {
            $error = error_get_last();

            if ( ! $error ) {
                return;
            }

            $fatalTypes = [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
            ];

            if ( ! in_array( $error['type'], $fatalTypes, true ) ) {
                return;
            }

            $hash = md5( $error['message'] );

            if ( ! Helpers::shouldSendAlert( $hash, 3600 ) ) {
                return;
            }

            Mailer::send( 'Fatal PHP Error', [
                'message' => $error['message'],
                'file'    => $error['file'],
                'line'    => $error['line'],
                'site'    => home_url(),
            ] );
        }
    }