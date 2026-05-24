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
         * Schedule hourly health checks and register REST endpoint
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function init(): void {
            add_action( 'guardian_health_check', [ self::class, 'run' ] );
            add_action( 'rest_api_init', [ self::class, 'register_endpoint' ] );

            if ( ! wp_next_scheduled( 'guardian_health_check' ) ) {
                wp_schedule_event( time(), 'hourly', 'guardian_health_check' );
            }
        }

        /**
         * Register lightweight health check endpoint
         *
         * @return void
         *
         * @since 0.8.2
         */
        public static function register_endpoint(): void {
            register_rest_route( 'guardian/v1', '/health', [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'endpoint_response' ],
                'permission_callback' => '__return_true',
            ] );
        }

        /**
         * Health endpoint response
         *
         * @return array Health status data
         *
         * @since 0.8.2
         */
        public static function endpoint_response(): array {
            return [
                'status'    => 'ok',
                'timestamp' => time(),
            ];
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

            // Use lightweight REST endpoint for faster health checks
            // Falls back to homepage if endpoint doesn't exist
            $health_url = rest_url( 'guardian/v1/health' );

            // First attempt: standard check with SSL verification
            $response = wp_remote_get( $health_url, [
                'timeout' => 10,
            ] );

            // Fallback: retry with SSL verification disabled for self-signed certs
            // and increased timeout for slow shared hosting environments
            if ( is_wp_error( $response )
                 && in_array( $response->get_error_code(), [
                    'http_request_failed',
                    'ssl_verification_failed',
                ], true ) ) {
                prof_guardian_log( '[Guardian] Retrying health check with relaxed SSL verification...' );
                $response = wp_remote_get( $health_url, [
                    'timeout'   => 15,
                    'sslverify' => false,
                ] );
            }

            $elapsed = round( ( microtime( true ) - $start_time ) * 1000, 2 );

            if ( is_wp_error( $response ) ) {
                prof_guardian_log( sprintf( '[Guardian] Health check FAILED in %sms - Error: %s (%s)', $elapsed, $response->get_error_message(), $response->get_error_code() ) );

                // Track consecutive failures to avoid false alarms from loopback issues
                $failure_count = (int) get_option( 'prof_guardian_health_failures', 0 );
                $failure_count ++;
                update_option( 'prof_guardian_health_failures', $failure_count, false );

                prof_guardian_log( sprintf( '[Guardian] Consecutive failures: %d', $failure_count ) );

                // Only alert after 3 consecutive failures (3 hours of confirmed issues)
                // This prevents false alarms from transient loopback issues, split-horizon DNS,
                // reverse proxies, basic auth, or slow TLS handshakes
                if ( $failure_count < 3 ) {
                    prof_guardian_log( '[Guardian] Waiting for more failures before alerting (reduces false positives)' );

                    return;
                }

                $error_hash = md5( 'health_check_' . $response->get_error_code() );

                // Only send alert once per hour for same error
                if ( ! Helpers::shouldSendAlert( $error_hash, 3600 ) ) {
                    prof_guardian_log( '[Guardian] Alert throttled (already sent within 1 hour)' );

                    return;
                }

                prof_guardian_log( '[Guardian] Sending alert email...' );
                Mailer::send( 'Health Check Failed', [
                    'error'             => $response->get_error_message(),
                    'error_code'        => $response->get_error_code(),
                    'consecutive_fails' => $failure_count,
                    'site'              => home_url(),
                ] );

                return;
            }

            $code = wp_remote_retrieve_response_code( $response );
            prof_guardian_log( sprintf( '[Guardian] Health check completed in %sms - Status: %d', $elapsed, $code ) );

            if ( $code >= 500 ) {
                prof_guardian_log( sprintf( '[Guardian] Server error detected: HTTP %d', $code ) );

                // Track consecutive 5xx errors
                $failure_count = (int) get_option( 'prof_guardian_health_failures', 0 );
                $failure_count ++;
                update_option( 'prof_guardian_health_failures', $failure_count, false );

                prof_guardian_log( sprintf( '[Guardian] Consecutive 5xx errors: %d', $failure_count ) );

                // Only alert after 2 consecutive 5xx errors (2 hours)
                // 5xx errors are more serious than transport failures, so lower threshold
                if ( $failure_count < 2 ) {
                    prof_guardian_log( '[Guardian] Waiting for confirmation before alerting on 5xx error' );

                    return;
                }

                $error_hash = md5( 'server_error_' . $code );

                // Only send alert once per hour for same status code
                if ( ! Helpers::shouldSendAlert( $error_hash, 3600 ) ) {
                    prof_guardian_log( '[Guardian] Alert throttled (already sent within 1 hour)' );

                    return;
                }

                prof_guardian_log( '[Guardian] Sending alert email...' );
                Mailer::send( 'Website returning server error', [
                    'status_code'       => $code,
                    'consecutive_fails' => $failure_count,
                    'site'              => home_url(),
                ] );
            } elseif ( $code >= 200 && $code < 300 ) {
                // Reset failure counter on success
                $previous_failures = (int) get_option( 'prof_guardian_health_failures', 0 );
                if ( $previous_failures > 0 ) {
                    delete_option( 'prof_guardian_health_failures' );
                    prof_guardian_log( sprintf( '[Guardian] Site recovered after %d failure(s)', $previous_failures ) );
                }
                prof_guardian_log( '[Guardian] Health check PASSED - Site is healthy' );
            } else {
                prof_guardian_log( sprintf( '[Guardian] Health check completed with status %d (no alert needed)', $code ) );
            }
        }
    }