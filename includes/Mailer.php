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
         * Fallback email address when no custom address is configured
         *
         * @var string
         *
         * @since 1.0.0
         */
        private static $fallbackEmail = 'dev@prof-designs.lv';

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
            $siteUrl = home_url();

            $to = defined( 'PROFDESIGNS_GUARDIAN_EMAIL' ) ? PROFDESIGNS_GUARDIAN_EMAIL : self::$fallbackEmail;

            error_log( sprintf( '[Guardian] Sending email to %s - Subject: %s', $to, $subject ) );

            $message = '';
            foreach ( $data as $key => $value ) {
                $message .= strtoupper( $key ) . ': ' . $value . PHP_EOL;
            }

            $message .= PHP_EOL . 'TIME: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';

            $result = wp_mail( $to, '[' . parse_url( $siteUrl, PHP_URL_HOST ) . '] ' . $subject, $message );

            if ( $result ) {
                error_log( '[Guardian] Email sent successfully' );
            } else {
                error_log( '[Guardian] Email sending FAILED' );
            }
        }
    }