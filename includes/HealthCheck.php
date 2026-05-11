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
     * Performs periodic health checks on the website by making HTTP requests
     * and monitoring response codes. Alerts administrators if the site is down
     * or returning server errors.
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
            $response = wp_remote_get( home_url() );
            $code     = wp_remote_retrieve_response_code( $response );

            if ( is_wp_error( $response ) ) {
                Mailer::send( 'Health Check Failed', [
                    'error' => $response->get_error_message(),
                    'site'  => home_url(),
                ] );

                return;
            }

            if ( $code >= 500 ) {
                Mailer::send( 'Website returning server error', [
                    'status_code' => $code,
                    'site'        => home_url(),
                ] );
            }
        }
    }