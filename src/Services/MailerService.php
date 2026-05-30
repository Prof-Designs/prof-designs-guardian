<?php
    /**
     * Mailer Service
     *
     * @package ProfDesigns\Guardian\Services
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Services;

    use ProfDesigns\Guardian\Application;

    /**
     * Class MailerService
     *
     * Handles email notifications with rate limiting
     *
     * @package ProfDesigns\Guardian\Services
     * @since   1.0.0
     */
    class MailerService {
        /**
         * The application instance
         *
         * @var Application
         */
        protected Application $app;

        /**
         * Throttle window in seconds (24 hours)
         */
        protected const THROTTLE_WINDOW = 86400;

        /**
         * Prefix for fatal-error throttle types.
         */
        protected const FATAL_ERROR_THROTTLE_PREFIX = 'fatal_error_';

        /**
         * Option key storing fatal-error throttle timestamps keyed by hash.
         */
        protected const FATAL_ERROR_THROTTLE_OPTION = 'prof_guardian_email_last_fatal_errors';

        /**
         * MailerService constructor
         *
         * @param Application $app Application instance
         */
        public function __construct( Application $app ) {
            $this->app = $app;
        }

        /**
         * Check if emails are enabled
         *
         * @return bool
         */
        public function isEnabled(): bool {
            // Allow disabling emails by setting PROFDESIGNS_GUARDIAN_EMAIL to false.
            return ! ( defined( 'PROFDESIGNS_GUARDIAN_EMAIL' ) && constant( 'PROFDESIGNS_GUARDIAN_EMAIL' ) === false );
        }

        /**
         * Resolve notification recipient email.
         *
         * - If PROFDESIGNS_GUARDIAN_EMAIL is a valid non-empty string, use it.
         * - Otherwise fall back to admin_email.
         *
         * @return string|null
         */
        public function getRecipientEmail(): ?string {
            $configured_email = defined( 'PROFDESIGNS_GUARDIAN_EMAIL' ) ? constant( 'PROFDESIGNS_GUARDIAN_EMAIL' ) : null;

            if ( is_string( $configured_email )
                 && $configured_email !== ''
                 && is_email( $configured_email ) ) {
                return $configured_email;
            }

            $admin_email = get_option( 'admin_email' );
            if ( is_string( $admin_email ) && $admin_email !== '' && is_email( $admin_email ) ) {
                return $admin_email;
            }

            return null;
        }

        /**
         * Send email with throttling
         *
         * @param string $to      Recipient email address
         * @param string $subject Email subject
         * @param string $message Email message
         * @param string $type    Email type for throttling
         *
         * @return bool
         */
        public function send( string $to, string $subject, string $message, string $type = 'general' ): bool {
            if ( ! $this->isEnabled() ) {
                prof_guardian_log( '[Guardian] Email notifications disabled' );

                return false;
            }

            // Check throttle
            if ( $this->isThrottled( $type ) ) {
                prof_guardian_log( "[Guardian] Email throttled: {$type}" );

                return false;
            }

            // Sanitize inputs (defense-in-depth against header injection / log injection).
            $safe_subject = str_replace( [ "\r", "\n" ], '', $subject );
            $safe_to      = str_replace( [ "\r", "\n" ], '', $to );

            // Send email
            $result = wp_mail( $safe_to, $safe_subject, $message );

            if ( $result ) {
                $this->updateThrottle( $type );
                prof_guardian_log( "[Guardian] Email sent: {$safe_subject} (to: {$safe_to})" );
            } else {
                // Always log mail failures (even when WP_DEBUG is off) since alerts won't reach admins.
                error_log( "[Guardian] CRITICAL: Failed to send email: {$safe_subject} (to: {$safe_to})" );

                prof_guardian_log( "[Guardian] Failed to send email: {$safe_subject} (to: {$safe_to})" );
            }

            return $result;
        }

        /**
         * Send test email
         *
         * @return bool
         */
        public function sendTestEmail(): bool {
            $to = $this->getRecipientEmail();

            if ( ! $to ) {
                prof_guardian_log( '[Guardian] No admin email configured' );

                return false;
            }

            $site_name = get_bloginfo( 'name' );
            $subject   = "[{$site_name}] Guardian Plugin Activated";
            $message   = "Prof Designs Guardian has been successfully activated on {$site_name}.\n\n";
            $message   .= "The following features are now active:\n";
            $message   .= "• Automatic updates for WordPress core, plugins, and themes\n";
            $message   .= "• Fatal error monitoring\n";
            $message   .= "• Health check monitoring\n";
            $message   .= "• Security hardening\n\n";
            $message   .= "You will receive email notifications for critical errors and failed updates.";

            return $this->send( $to, $subject, $message, 'activation' );
        }

        /**
         * Check if email type is throttled
         *
         * @param string $type Email type
         *
         * @return bool
         */
        protected function isThrottled( string $type ): bool {
            $type = sanitize_key( $type ) ?: 'general';

            if ( strpos( $type, self::FATAL_ERROR_THROTTLE_PREFIX ) === 0 ) {
                $error_hash = substr( $type, strlen( self::FATAL_ERROR_THROTTLE_PREFIX ) );
                if ( $error_hash === '' ) {
                    return false;
                }

                $throttle_map = $this->getPrunedFatalErrorThrottleMap();

                return isset( $throttle_map[ $error_hash ] );
            }

            $last_sent = (int) get_option( "prof_guardian_email_last_{$type}", 0 );

            return ( time() - $last_sent ) < self::THROTTLE_WINDOW;
        }

        /**
         * Update throttle timestamp
         *
         * @param string $type Email type
         *
         * @return void
         */
        protected function updateThrottle( string $type ): void {
            $type = sanitize_key( $type ) ?: 'general';

            if ( strpos( $type, self::FATAL_ERROR_THROTTLE_PREFIX ) === 0 ) {
                $error_hash = substr( $type, strlen( self::FATAL_ERROR_THROTTLE_PREFIX ) );
                if ( $error_hash === '' ) {
                    return;
                }

                $throttle_map                = $this->getPrunedFatalErrorThrottleMap( false );
                $throttle_map[ $error_hash ] = time();
                update_option( self::FATAL_ERROR_THROTTLE_OPTION, $throttle_map, false );

                return;
            }

            update_option( "prof_guardian_email_last_{$type}", time(), false );
        }

        /**
         * Load and prune fatal-error throttle timestamps.
         *
         * @param bool $persist Whether to persist pruning changes.
         *
         * @return array<string,int>
         */
        protected function getPrunedFatalErrorThrottleMap( bool $persist = true ): array {
            $raw_map = get_option( self::FATAL_ERROR_THROTTLE_OPTION, [] );
            $map     = is_array( $raw_map ) ? $raw_map : [];
            $now     = time();
            $pruned  = [];

            foreach ( $map as $hash => $timestamp ) {
                if ( ! is_string( $hash ) || $hash === '' ) {
                    continue;
                }

                $timestamp = (int) $timestamp;
                if ( $timestamp > 0 && ( $now - $timestamp ) < self::THROTTLE_WINDOW ) {
                    $pruned[ $hash ] = $timestamp;
                }
            }

            if ( $persist && $pruned !== $map ) {
                if ( empty( $pruned ) ) {
                    delete_option( self::FATAL_ERROR_THROTTLE_OPTION );
                } else {
                    update_option( self::FATAL_ERROR_THROTTLE_OPTION, $pruned, false );
                }
            }

            return $pruned;
        }
    }
