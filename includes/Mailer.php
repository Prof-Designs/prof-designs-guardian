<?php
    /**
     * Email notification handler for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    /**
     * Class Mailer
     *
     * Handles email notifications for Guardian alerts and errors.
     * Supports configurable recipient addresses via WordPress constants.
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */
    class Mailer {
        /**
         * Fallback email address when no custom address is configured.
         *
         * @var string
         *
         * @since 1.0.0
         */
        private static $fallbackEmail = 'dev@prof-designs.lv';

        /**
         * Sends an email notification with structured data.
         *
         * Priority order for recipient address:
         * 1. PROFDESIGNS_GUARDIAN_EMAIL constant (if defined in wp-config.php)
         * 2. Fallback email address
         *
         * @param string $subject Email subject line.
         * @param array  $data    Associative array of data to include in email body.
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function send( string $subject, array $data = [] ): void {
            $siteUrl = home_url();

            // Priority:
            // 1. WP config constant
            // 2. Hardcoded fallback
            $to = defined( 'PROFDESIGNS_GUARDIAN_EMAIL' ) ? PROFDESIGNS_GUARDIAN_EMAIL : self::$fallbackEmail;

            $message = '';

            foreach ( $data as $key => $value ) {
                $message .= strtoupper( $key ) . ': ' . $value . PHP_EOL;
            }

            wp_mail( $to, '[' . parse_url( $siteUrl, PHP_URL_HOST ) . '] ' . $subject, $message );
        }
    }