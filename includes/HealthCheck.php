<?php
    /**
     * Website health monitoring for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    /**
     * Class HealthCheck
     *
     * Performs periodic health checks on the website
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */
    class HealthCheck {
        /**
         * Schedule hourly health checks
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function init(): void {
            add_action( 'guardian_health_check', [ self::class, 'run' ] );

            if ( ! wp_next_scheduled( 'guardian_health_check' ) ) {
                wp_schedule_event( time(), 'hourly', 'guardian_health_check' );
            }
        }

        /**
         * Run health check and alert on errors
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function run(): void {
            // Add timeout to prevent hanging
            $response = wp_remote_get( home_url(), [
                    'timeout'   => 10,
                    'sslverify' => false,
                ] );

            if ( is_wp_error( $response ) ) {
                $error_hash = md5( 'health_check_' . $response->get_error_code() );

                // Only send alert once per hour for same error
                if ( ! Helpers::shouldSendAlert( $error_hash, 3600 ) ) {
                    return;
                }

                Mailer::send( 'Health Check Failed', [
                    'error'      => $response->get_error_message(),
                    'error_code' => $response->get_error_code(),
                    'site'       => home_url(),
                ] );

                return;
            }

            $code = wp_remote_retrieve_response_code( $response );

            if ( $code >= 500 ) {
                $error_hash = md5( 'server_error_' . $code );

                // Only send alert once per hour for same status code
                if ( ! Helpers::shouldSendAlert( $error_hash, 3600 ) ) {
                    return;
                }

                Mailer::send( 'Website returning server error', [
                    'status_code' => $code,
                    'site'        => home_url(),
                ] );
            }
        }
    }