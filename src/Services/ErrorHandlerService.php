<?php
    /**
     * Error Handler Service
     *
     * @package ProfDesigns\Guardian\Services
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Services;

    use ProfDesigns\Guardian\Application;

    /**
     * Class ErrorHandlerService
     *
     * Monitors and handles PHP fatal errors with email notifications
     *
     * @package ProfDesigns\Guardian\Services
     * @since   1.0.0
     */
    class ErrorHandlerService {
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
         * ErrorHandlerService constructor
         *
         * @param Application   $app    Application instance
         * @param MailerService $mailer Mailer service instance
         */
        public function __construct( Application $app, MailerService $mailer ) {
            $this->app    = $app;
            $this->mailer = $mailer;
        }

        /**
         * Handle fatal errors
         *
         * @return void
         */
        public function handleFatalError(): void {
            $error = error_get_last();

            if ( $error === null ) {
                return;
            }

            $fatal_errors = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ];

            if ( ! in_array( $error['type'], $fatal_errors, true ) ) {
                return;
            }

            // Skip recovery mode errors (already handled by WordPress)
            if ( isset( $error['message'] ) && strpos( $error['message'], 'recovery mode' ) !== false ) {
                return;
            }

            prof_guardian_log( '[Guardian] Fatal error detected: ' . $error['message'] );

            // Send email notification
            $this->sendErrorNotification( $error );
        }

        /**
         * Send error notification email
         *
         * @param array $error Error information
         *
         * @return void
         */
        protected function sendErrorNotification( array $error ): void {
            $recipient_email = $this->mailer->getRecipientEmail();
            if ( ! $recipient_email ) {
                return;
            }

            $site_name = get_bloginfo( 'name' );
            $subject   = "[{$site_name}] Fatal Error Detected";

            $message = "A fatal error was detected on {$site_name}:\n\n";
            $message .= "Error Type: " . $this->getErrorType( $error['type'] ) . "\n";
            $message .= "Message: {$error['message']}\n";
            $message .= "File: {$error['file']}\n";
            $message .= "Line: {$error['line']}\n";
            $message .= "\nTime: " . current_time( 'mysql' ) . "\n";
            $message .= "URL: "
                        . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'N/A' );

            $error_key = md5( (string) ( $error['message'] ?? '' )
                              . (string) ( $error['file'] ?? '' )
                              . (string) ( $error['line'] ?? '' ) );

            $this->mailer->send( $recipient_email, $subject, $message, 'fatal_error_' . $error_key );
        }

        /**
         * Get human-readable error type name
         *
         * @param int $type Error type constant
         *
         * @return string
         */
        protected function getErrorType( int $type ): string {
            $error_types = [
                E_ERROR             => 'Fatal Error',
                E_WARNING           => 'Warning',
                E_PARSE             => 'Parse Error',
                E_NOTICE            => 'Notice',
                E_CORE_ERROR        => 'Core Error',
                E_CORE_WARNING      => 'Core Warning',
                E_COMPILE_ERROR     => 'Compile Error',
                E_COMPILE_WARNING   => 'Compile Warning',
                E_USER_ERROR        => 'User Error',
                E_USER_WARNING      => 'User Warning',
                E_USER_NOTICE       => 'User Notice',
                E_STRICT            => 'Strict Notice',
                E_RECOVERABLE_ERROR => 'Recoverable Error',
                E_DEPRECATED        => 'Deprecated',
                E_USER_DEPRECATED   => 'User Deprecated',
            ];

            return $error_types[ $type ] ?? 'Unknown Error';
        }
    }
