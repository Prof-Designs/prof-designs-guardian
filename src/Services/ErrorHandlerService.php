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
     * @since   0.10.0
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
         * Handle recoverable errors
         *
         * @param int    $severity Error severity
         * @param string $message  Error message
         * @param string $file     File where error occurred
         * @param int    $line     Line number where error occurred
         * @param array  $context  Error context (PHP 7.4 compatibility)
         *
         * @return bool
         */
        public function handleRecoverableError( int $severity, string $message, string $file, int $line, array $context = [] ): bool {
            // Log critical errors
            $critical_errors = [ E_WARNING, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED ];

            if ( in_array( $severity, $critical_errors, true ) && $this->shouldLogRecoverableError( $severity, $message, $file ) ) {
                prof_guardian_log( "[Guardian] {$this->getErrorType($severity)}: {$message} in {$file}:{$line}" );
            }

            // Don't interfere with WordPress error handling
            return false;
        }

        /**
         * Get the recoverable error mask for set_error_handler.
         *
         * @return int
         */
        public function getRecoverableErrorMask(): int {
            $mask = E_WARNING | E_USER_WARNING;

            // Deprecated notices are noisy on many production sites; keep opt-in.
            if ( defined( 'PROFDESIGNS_GUARDIAN_CAPTURE_DEPRECATED' )
                 && (bool) constant( 'PROFDESIGNS_GUARDIAN_CAPTURE_DEPRECATED' ) ) {
                $mask |= E_DEPRECATED | E_USER_DEPRECATED;
            }

            return $mask;
        }

        /**
         * Decide whether a recoverable error should be logged by Guardian.
         *
         * @param int    $severity Error severity
         * @param string $message  Error message
         * @param string $file     File where error occurred
         *
         * @return bool
         */
        protected function shouldLogRecoverableError( int $severity, string $message, string $file ): bool {
            // Allow explicit override to keep previous broad logging behavior.
            if ( defined( 'PROFDESIGNS_GUARDIAN_LOG_THIRD_PARTY_WARNINGS' )
                 && (bool) constant( 'PROFDESIGNS_GUARDIAN_LOG_THIRD_PARTY_WARNINGS' ) ) {
                return true;
            }

            $normalized_file = str_replace( '\\', '/', strtolower( $file ) );
            $site_root       = str_replace( '\\', '/', strtolower( ABSPATH ) );

            // Always keep Guardian-origin warnings.
            if ( strpos( $normalized_file, '/wp-content/mu-plugins/prof-designs-guardian/' ) !== false ) {
                return true;
            }

            // Keep WordPress core warnings (wp-admin/wp-includes) as actionable platform issues.
            if ( strpos( $normalized_file, '/wp-admin/' ) !== false || strpos( $normalized_file, '/wp-includes/' ) !== false ) {
                return true;
            }

            // Any warning clearly outside site root can still be relevant.
            if ( $site_root !== '' && strpos( $normalized_file, $site_root ) === false ) {
                return true;
            }

            // By default suppress third-party plugin/theme warning storms.
            return false;
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

            $error_key = md5( (string) ( $error['message'] ?? '' ) . (string) ( $error['file'] ?? '' ) . (string) ( $error['line'] ?? '' ) );

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
