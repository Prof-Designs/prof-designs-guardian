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
     * @since   0.10.0
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
            return ! defined( 'PROFDESIGNS_GUARDIAN_EMAIL' ) || PROFDESIGNS_GUARDIAN_EMAIL;
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

            // Send email
            $result = wp_mail( $to, $subject, $message );

            if ( $result ) {
                $this->updateThrottle( $type );
                prof_guardian_log( "[Guardian] Email sent: {$subject}" );
            } else {
                prof_guardian_log( "[Guardian] Failed to send email: {$subject}" );
            }

            return $result;
        }

        /**
         * Send test email
         *
         * @return bool
         */
        public function sendTestEmail(): bool {
            $to = get_option( 'admin_email' );
            if ( defined( 'PROFDESIGNS_GUARDIAN_EMAIL' )
                 && is_string( PROFDESIGNS_GUARDIAN_EMAIL )
                 && PROFDESIGNS_GUARDIAN_EMAIL !== ''
                 && is_email( PROFDESIGNS_GUARDIAN_EMAIL ) ) {
                $to = PROFDESIGNS_GUARDIAN_EMAIL;
            }

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

        /**
         * Check if email type is throttled
         *
         * @param string $type Email type
         *
         * @return bool
         */
        protected function isThrottled( string $type ): bool {
            $last_sent = get_option( "prof_guardian_email_last_{$type}", 0 );

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
            update_option( "prof_guardian_email_last_{$type}", time(), false );
        }
    }
