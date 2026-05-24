<?php
    /**
     * Email notification handler for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    /**
     * Class Mailer
     *
     * Handles email notifications for Guardian alerts and errors
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */
    class Mailer {
        /**
         * Send email notification
         *
         * @param string $subject Email subject line
         * @param array  $data    Key-value pairs for email body
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function send( string $subject, array $data = [] ): void {
            // Determine recipient with fallback for fatal error scenarios
            if ( defined( 'PROFDESIGNS_GUARDIAN_EMAIL' ) ) {
                $to = PROFDESIGNS_GUARDIAN_EMAIL;
            } elseif ( function_exists( 'get_option' ) ) {
                // WordPress is available, use admin email
                $to = get_option( 'admin_email' );
            } else {
                // Fatal error scenario: WordPress not fully loaded and no constant defined
                // Cannot reliably determine recipient email - abort
                error_log( '[Guardian] ERROR: Cannot send email - WordPress not loaded and PROFDESIGNS_GUARDIAN_EMAIL not defined' );

                return;
            }

            // Capture wp_mail errors temporarily for this send (only if WordPress is loaded)
            if ( function_exists( 'add_action' ) ) {
                add_action( 'wp_mail_failed', [ __CLASS__, 'log_mail_error' ], 10, 1 );
            }

            $siteUrl = home_url();

            prof_guardian_log( sprintf( '[Guardian] Sending email to %s - Subject: %s', $to, $subject ) );

            $message = '';
            foreach ( $data as $key => $value ) {
                $message .= strtoupper( $key ) . ': ' . $value . PHP_EOL;
            }

            $message .= PHP_EOL . 'TIME: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';

            $result = wp_mail( $to, '[' . parse_url( $siteUrl, PHP_URL_HOST ) . '] ' . $subject, $message );

            // Remove the error handler after sending (only if we added it)
            if ( function_exists( 'remove_action' ) ) {
                remove_action( 'wp_mail_failed', [ __CLASS__, 'log_mail_error' ], 10 );
            }

            if ( $result ) {
                prof_guardian_log( '[Guardian] Email sent successfully' );
            } else {
                // CRITICAL: Always log email failures (even in production) since alerts won't reach admin
                error_log( sprintf( '[Guardian] CRITICAL: Email sending FAILED - Subject: %s, Recipient: %s', $subject, $to ) );
            }
        }

        /**
         * Log wp_mail errors with detailed error message
         *
         * @param \WP_Error $error WP_Error object from wp_mail_failed action
         *
         * @return void
         *
         * @since 0.7.0
         */
        public static function log_mail_error( $error ): void {
            if ( is_wp_error( $error ) ) {
                error_log( sprintf( '[Guardian] SMTP Error: %s', $error->get_error_message() ) );
            }
        }

        /**
         * Send test email with Site Health summary
         *
         * Sends an email with critical and recommended Site Health checks
         * to verify email delivery is working
         *
         * @return void
         *
         * @since 1.0.1
         */
        public static function send_test_email(): void {
            // Get site info
            $site_url  = home_url();
            $site_name = get_bloginfo( 'name' );
            $timestamp = gmdate( 'Y-m-d H:i:s' ) . ' UTC';

            // Get Site Health data
            $health_data = self::get_site_health_summary();

            // Build email subject
            $subject = sprintf( '[%s] Guardian Test Email - %s', $site_name, $timestamp );

            // Build email body
            $message = "Guardian plugin has been activated and is now protecting your site.\n\n";
            $message .= "=== SITE HEALTH CHECK ===\n\n";

            if ( ! empty( $health_data['critical'] ) ) {
                $message .= "CRITICAL ISSUES (" . count( $health_data['critical'] ) . "):\n";
                foreach ( $health_data['critical'] as $issue ) {
                    $message .= "  • " . $issue . "\n";
                }
                $message .= "\n";
            }

            if ( ! empty( $health_data['recommended'] ) ) {
                $message .= "RECOMMENDED IMPROVEMENTS (" . count( $health_data['recommended'] ) . "):\n";
                foreach ( $health_data['recommended'] as $issue ) {
                    $message .= "  • " . $issue . "\n";
                }
                $message .= "\n";
            }

            if ( empty( $health_data['critical'] ) && empty( $health_data['recommended'] ) ) {
                $message .= "✓ All checks passed! No issues found.\n\n";
            }

            $message .= "=== GUARDIAN PROTECTION ===\n\n";
            $message .= "Enabled Features:\n";
            $message .= "  • Automatic updates (core, plugins, themes)\n";
            $message .= "  • Fatal error monitoring\n";
            $message .= "  • Hourly health checks\n";
            $message .= "  • File editor protection\n";
            $message .= "  • Upload security hardening\n";

            $lock_mods = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;
            $message   .= "  • Plugin/Theme modification lock: " . ( $lock_mods ? 'ENABLED' : 'DISABLED' ) . "\n\n";

            $message .= "---\n";
            $message .= "Site: " . $site_url . "\n";
            $message .= "Time: " . $timestamp . "\n";

            // Send email
            $to = defined( 'PROFDESIGNS_GUARDIAN_EMAIL' ) ? PROFDESIGNS_GUARDIAN_EMAIL : get_option( 'admin_email' );

            prof_guardian_log( '[Guardian] Sending test email to: ' . $to );

            // Capture wp_mail errors temporarily for this send
            if ( function_exists( 'add_action' ) ) {
                add_action( 'wp_mail_failed', [ __CLASS__, 'log_mail_error' ], 10, 1 );
            }

            $result = wp_mail( $to, $subject, $message );

            // Remove the error handler after sending
            if ( function_exists( 'remove_action' ) ) {
                remove_action( 'wp_mail_failed', [ __CLASS__, 'log_mail_error' ], 10 );
            }

            if ( $result ) {
                prof_guardian_log( '[Guardian] Test email sent successfully' );
            } else {
                // CRITICAL: Always log email failures in production
                error_log( sprintf( '[Guardian] CRITICAL: Test email sending FAILED - Recipient: %s', $to ) );
            }
        }

        /**
         * Get Site Health summary
         *
         * Fetches critical and recommended issues from WordPress Site Health
         *
         * @return array Array with 'critical' and 'recommended' keys
         *
         * @since 1.0.1
         */
        private static function get_site_health_summary(): array {
            $summary = [
                'critical'    => [],
                'recommended' => [],
            ];

            // Load Site Health class if not available
            if ( ! class_exists( 'WP_Site_Health' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
            }

            try {
                $site_health = new \WP_Site_Health();
                $tests       = $site_health->get_tests();

                // Run direct tests
                if ( isset( $tests['direct'] ) ) {
                    foreach ( $tests['direct'] as $test ) {
                        if ( ! isset( $test['test'] ) ) {
                            continue;
                        }

                        // Execute test
                        $result = call_user_func( $test['test'] );

                        if ( ! isset( $result['status'] ) ) {
                            continue;
                        }

                        // Collect issues
                        if ( $result['status'] === 'critical' && isset( $result['label'] ) ) {
                            $summary['critical'][] = $result['label'];
                        } elseif ( $result['status'] === 'recommended' && isset( $result['label'] ) ) {
                            $summary['recommended'][] = $result['label'];
                        }
                    }
                }
            } catch ( \Exception $e ) {
                prof_guardian_log( '[Guardian] Failed to get Site Health data: ' . $e->getMessage() );
            }

            return $summary;
        }
    }