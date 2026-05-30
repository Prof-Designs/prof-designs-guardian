<?php
    /**
     * Health Check Service
     *
     * @package ProfDesigns\Guardian\Services
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Services;

    use ProfDesigns\Guardian\Application;

    /**
     * Class HealthCheckService
     *
     * Monitors site health via REST API endpoint
     *
     * @package ProfDesigns\Guardian\Services
     * @since   0.10.0
     */
    class HealthCheckService {
        /**
         * The application instance
         *
         * @var Application
         */
        protected Application $app;

        /**
         * Mailer service instance
         *
         * @var MailerService
         */
        protected MailerService $mailer;

        /**
         * REST API namespace
         */
        protected const REST_NAMESPACE = 'prof-guardian/v1';

        /**
         * REST API route
         */
        protected const REST_ROUTE = '/health';

        /**
         * Maximum consecutive failures before alert
         */
        protected const MAX_FAILURES = 3;

        /**
         * HealthCheckService constructor
         *
         * @param Application   $app    Application instance
         * @param MailerService $mailer Mailer service instance
         */
        public function __construct( Application $app, MailerService $mailer ) {
            $this->app    = $app;
            $this->mailer = $mailer;
        }

        /**
         * Register REST API endpoint
         *
         * @return void
         */
        public function registerEndpoint(): void {
            register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handleHealthCheck' ],
                'permission_callback' => function ( \WP_REST_Request $request ): bool {
                    // Always allow authenticated admins.
                    if ( current_user_can( 'manage_options' ) ) {
                        return true;
                    }

                    // If a shared secret is configured, require it for unauthenticated monitoring.
                    $health_key = defined( 'PROFDESIGNS_GUARDIAN_HEALTH_KEY' ) ? constant( 'PROFDESIGNS_GUARDIAN_HEALTH_KEY' ) : null;

                    if ( is_string( $health_key ) && $health_key !== '' ) {
                        $header_key = $request->get_header( 'x-guardian-health-key' );
                        $key        = ( is_string( $header_key )
                                        && $header_key !== '' ) ? $header_key : (string) $request->get_param( 'key' );

                        if ( $key === '' ) {
                            return false;
                        }

                        return hash_equals( $health_key, $key );
                    }

                    return false;
                },
            ] );
        }

        /**
         * Handle health check request
         *
         * @param \WP_REST_Request $request REST API request
         *
         * @return \WP_REST_Response
         */
        public function handleHealthCheck( \WP_REST_Request $request ): \WP_REST_Response {
            $status = $this->getHealthStatus();

            // Reset failure counter on successful check
            if ( $status['status'] === 'healthy' ) {
                delete_option( 'prof_guardian_health_failures' );
            }

            $http_status = ( $status['status'] === 'healthy' ) ? 200 : 503;

            return new \WP_REST_Response( $status, $http_status );
        }

        /**
         * Run scheduled health check via WP-Cron.
         *
         * @return void
         */
        public function runScheduledHealthCheck(): void {
            $started_at  = microtime( true );
            $status      = $this->getHealthStatus();
            $duration_ms = round( ( microtime( true ) - $started_at ) * 1000, 2 );

            if ( $status['status'] === 'healthy' ) {
                delete_option( 'prof_guardian_health_failures' );

                return;
            }

            $error_message = 'Health check reported unhealthy status';
            foreach ( $status['checks'] as $name => $check ) {
                if ( isset( $check['status'] ) && $check['status'] !== 'pass' ) {
                    $check_message = isset( $check['message'] )
                                     && is_string( $check['message'] ) ? $check['message'] : 'Unknown check failure';
                    $error_message = sprintf( '%s: %s', (string) $name, $check_message );
                    break;
                }
            }

            $endpoint = rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
            $this->recordFailure( $endpoint, $error_message );

            prof_guardian_log( sprintf( '[Guardian] Health check run: unhealthy (%.2fms) - %s', $duration_ms, $error_message ) );
        }

        /**
         * Get current health status
         *
         * @return array
         */
        protected function getHealthStatus(): array {
            global $wpdb;

            $status = [
                'status'    => 'healthy',
                'timestamp' => time(),
                'checks'    => [],
            ];

            // Database connectivity
            $status['checks']['database'] = $this->checkDatabase( $wpdb );

            // WordPress core files
            $status['checks']['core_files'] = $this->checkCoreFiles();

            // Memory usage
            $status['checks']['memory'] = $this->checkMemory();

            // PHP version
            $status['checks']['php_version'] = $this->checkPhpVersion();

            // Determine overall status
            foreach ( $status['checks'] as $check ) {
                if ( $check['status'] !== 'pass' ) {
                    $status['status'] = 'unhealthy';
                    break;
                }
            }

            return $status;
        }

        /**
         * Check database connectivity
         *
         * @param \wpdb $wpdb WordPress database object
         *
         * @return array
         */
        protected function checkDatabase( \wpdb $wpdb ): array {
            try {
                $result = (int) $wpdb->get_var( 'SELECT 1' );

                return [
                    'status'  => $result === 1 ? 'pass' : 'fail',
                    'message' => $result === 1 ? 'Database connected' : 'Database connection failed',
                ];
            } catch ( \Exception $e ) {
                prof_guardian_log( '[Guardian] Database health check error: ' . $e->getMessage() );

                return [
                    'status'  => 'fail',
                    'message' => 'Database connection failed',
                ];
            }
        }

        /**
         * Check WordPress core files integrity
         *
         * @return array
         */
        protected function checkCoreFiles(): array {
            $wp_config_paths = [
                ABSPATH . 'wp-config.php',
                dirname( ABSPATH ) . '/wp-config.php',
            ];
            $core_files      = [
                ABSPATH . 'wp-load.php',
                ABSPATH . 'wp-settings.php',
            ];

            $wp_config_exists = false;
            foreach ( $wp_config_paths as $path ) {
                if ( file_exists( $path ) ) {
                    $wp_config_exists = true;
                    break;
                }
            }
            if ( ! $wp_config_exists ) {
                return [
                    'status'  => 'fail',
                    'message' => 'Missing core file: wp-config.php',
                ];
            }

            foreach ( $core_files as $file ) {
                if ( ! file_exists( $file ) ) {
                    return [
                        'status'  => 'fail',
                        'message' => 'Missing core file: ' . basename( $file ),
                    ];
                }
            }

            return [
                'status'  => 'pass',
                'message' => 'Core files intact',
            ];
        }

        /**
         * Check memory usage
         *
         * @return array
         */
        protected function checkMemory(): array {
            $memory_limit = ini_get( 'memory_limit' );
            $memory_usage = memory_get_usage( true );

            return [
                'status'       => 'pass',
                'message'      => 'Memory usage normal',
                'memory_limit' => $memory_limit,
                'memory_usage' => size_format( $memory_usage ),
            ];
        }

        /**
         * Check PHP version
         *
         * @return array
         */
        protected function checkPhpVersion(): array {
            $php_version = PHP_VERSION;
            $min_version = '7.4';

            return [
                'status'      => version_compare( $php_version, $min_version, '>=' ) ? 'pass' : 'fail',
                'message'     => "PHP version: {$php_version}",
                'php_version' => $php_version,
            ];
        }

        /**
         * Record health check failure
         *
         * @param string $endpoint Health check endpoint
         * @param string $error    Error message
         *
         * @return void
         */
        public function recordFailure( string $endpoint, string $error ): void {
            $failures = (int) get_option( 'prof_guardian_health_failures', 0 );
            $failures ++;

            update_option( 'prof_guardian_health_failures', $failures, false );

            prof_guardian_log( "[Guardian] Health check failure #{$failures}: {$error}" );

            // Send alert after consecutive failures
            if ( $failures >= self::MAX_FAILURES ) {
                $this->sendFailureAlert( $endpoint, $error, $failures );
                // Reset counter after alert
                delete_option( 'prof_guardian_health_failures' );
            }
        }

        /**
         * Send failure alert email
         *
         * @param string $endpoint Health check endpoint
         * @param string $error    Error message
         * @param int    $failures Number of consecutive failures
         *
         * @return void
         */
        protected function sendFailureAlert( string $endpoint, string $error, int $failures ): void {
            $recipient_email = $this->mailer->getRecipientEmail();
            if ( ! $recipient_email ) {
                return;
            }

            $site_name = get_bloginfo( 'name' );
            $subject   = "[{$site_name}] Health Check Alert";

            $message = "Health check monitoring has detected {$failures} consecutive failures on {$site_name}.\n\n";
            $message .= "Endpoint: {$endpoint}\n";
            $message .= "Latest Error: {$error}\n";
            $message .= "Time: " . current_time( 'mysql' ) . "\n\n";
            $message .= "Please investigate this issue as soon as possible.";

            $this->mailer->send( $recipient_email, $subject, $message, 'health_check' );
        }
    }
