<?php
    /**
     * Fatal error handler for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
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
         * Initializes the error handler.
         *
         * Registers a shutdown function to catch fatal PHP errors that would
         * otherwise go unreported.
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function init(): void {
            register_shutdown_function( [ self::class, 'handleFatal' ] );
        }

        /**
         * Handles fatal PHP errors during shutdown.
         *
         * Captures the last error and sends an email notification if it's a fatal error.
         * Uses alert throttling to prevent duplicate notifications for the same error.
         *
         * Fatal error types monitored:
         * - E_ERROR: Fatal run-time errors
         * - E_PARSE: Compile-time parse errors
         * - E_CORE_ERROR: Fatal errors during PHP startup
         * - E_COMPILE_ERROR: Fatal compile-time errors
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

            // TODO: replace with default WordPress mailer
            Mailer::send( 'Fatal PHP Error', [
                'message' => $error['message'],
                'file'    => $error['file'],
                'line'    => $error['line'],
                'site'    => home_url(),
            ] );
        }
    }