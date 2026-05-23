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
            $start_time = microtime( true );
            prof_guardian_log( '[Guardian] Health check starting...' );

            // Add timeout to prevent hanging
            $response = wp_remote_get( home_url(), [
                'timeout'   => 10,
                'sslverify' => false,
            ] );

            $elapsed = round( ( microtime( true ) - $start_time ) * 1000, 2 );

            if ( is_wp_error( $response ) ) {
                prof_guardian_log( sprintf( '[Guardian] Health check FAILED in %sms - Error: %s (%s)', $elapsed, $response->get_error_message(), $response->get_error_code() ) );

                $error_hash = md5( 'health_check_' . $response->get_error_code() );

                // Only send alert once per hour for same error
                if ( ! Helpers::shouldSendAlert( $error_hash, 3600 ) ) {
                    prof_guardian_log( '[Guardian] Alert throttled (already sent within 1 hour)' );

                    return;
                }

                prof_guardian_log( '[Guardian] Sending alert email...' );
                Mailer::send( 'Health Check Failed', [
                    'error'      => $response->get_error_message(),
                    'error_code' => $response->get_error_code(),
                    'site'       => home_url(),
                ] );

                return;
            }

            $code = wp_remote_retrieve_response_code( $response );
            prof_guardian_log( sprintf( '[Guardian] Health check completed in %sms - Status: %d', $elapsed, $code ) );

            if ( $code >= 500 ) {
                prof_guardian_log( sprintf( '[Guardian] Server error detected: HTTP %d', $code ) );

                $error_hash = md5( 'server_error_' . $code );

                // Only send alert once per hour for same status code
                if ( ! Helpers::shouldSendAlert( $error_hash, 3600 ) ) {
                    prof_guardian_log( '[Guardian] Alert throttled (already sent within 1 hour)' );

                    return;
                }

                prof_guardian_log( '[Guardian] Sending alert email...' );
                Mailer::send( 'Website returning server error', [
                    'status_code' => $code,
                    'site'        => home_url(),
                ] );
            } elseif ( $code >= 200 && $code < 300 ) {
                prof_guardian_log( '[Guardian] Health check PASSED - Site is healthy' );
            } else {
                prof_guardian_log( sprintf( '[Guardian] Health check completed with status %d (no alert needed)', $code ) );
            }
        }
    }