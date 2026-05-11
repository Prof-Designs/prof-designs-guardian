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
         * Send email notification
         *
         * @param string $subject
         * @param array  $data Key-value pairs for email body
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