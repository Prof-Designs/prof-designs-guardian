<?php
    /**
     * Auto Updates Service
     *
     * @package ProfDesigns\Guardian\Services
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Services;

    use ProfDesigns\Guardian\Application;

    /**
     * Class AutoUpdateService
     *
     * Enables automatic updates for WordPress core, plugins, and themes.
     * Can be disabled via PROFDESIGNS_GUARDIAN_AUTO_UPDATES constant.
     *
     * @package ProfDesigns\Guardian\Services
     * @since   1.0.0
     */
    class AutoUpdateService {
        /**
         * The application instance
         *
         * @var Application
         */
        protected Application $app;

        /**
         * AutoUpdateService constructor
         *
         * @param Application $app Application instance
         */
        public function __construct( Application $app ) {
            $this->app = $app;
        }

        /**
         * Check if auto-updates are enabled
         *
         * @return bool
         */
        public function isEnabled(): bool {
            // Keep semantics consistent with other Guardian feature flags.
            return ! ( defined( 'PROFDESIGNS_GUARDIAN_AUTO_UPDATES' )
                       && constant( 'PROFDESIGNS_GUARDIAN_AUTO_UPDATES' ) === false );
        }

        /**
         * Enable automatic plugin updates
         *
         * @param mixed $update Whether to update (can be null from WP internals).
         * @param mixed $item   Plugin update data (optional).
         *
         * @return bool
         */
        public function enablePluginUpdates( $update, $item = null ): bool {
            return $update === null || (bool) $update;
        }

        /**
         * Enable automatic theme updates
         *
         * @param mixed $update Whether to update (can be null from WP internals).
         * @param mixed $item   Theme update data (optional).
         *
         * @return bool
         */
        public function enableThemeUpdates( $update, $item = null ): bool {
            return $update === null || (bool) $update;
        }

        /**
         * Enable automatic core updates
         *
         * @param mixed $update Whether to update (can be null from WP internals).
         * @param mixed $type   Update type (optional).
         *
         * @return bool
         */
        public function enableCoreUpdates( $update, $type = '' ): bool {
            return $update === null || (bool) $update;
        }

        /**
         * Filter core auto-update emails
         *
         * Preserve emails for failures and critical updates, suppress success emails
         *
         * @param bool   $send        Whether to send the email
         * @param string $type        The type of email being sent
         * @param object $core_update The update offer that was attempted
         * @param mixed  $result      The update result
         *
         * @return bool
         */
        public function filterCoreUpdateEmail( bool $send, string $type, $core_update, $result ): bool {
            // Always send failure and critical update emails
            if ( $type === 'fail' || $type === 'critical' ) {
                return true;
            }

            // Send if update resulted in error
            if ( is_wp_error( $result ) || $result === false ) {
                return true;
            }

            // Suppress success emails
            return false;
        }

        /**
         * Filter plugin/theme auto-update emails from the shared core filter.
         *
         * WordPress fires `auto_plugin_theme_update_email` with the email array as the
         * first argument (since WP 5.5). $type is 'success', 'fail', or 'mixed'.
         * Suppress success-only emails; preserve notifications when any update fails.
         *
         * Note: $email is untyped because an earlier filter callback may have returned
         * false to disable the email; passing that through a strict array hint would
         * throw a TypeError.
         *
         * @param mixed  $email              Email data passed to wp_mail() {to, subject, body, headers}, or false.
         * @param string $type               Update outcome: 'success', 'fail', or 'mixed'.
         * @param array  $successful_updates Successful update result items.
         * @param array  $failed_updates     Failed update result items.
         *
         * @return mixed
         */
        public function filterPluginThemeUpdateEmail( $email, string $type, array $successful_updates, array $failed_updates ) {
            // Only suppress when we have an actual email array and the run was success-only.
            // Uses pre_wp_mail to short-circuit wp_mail() before PHPMailer runs,
            // avoiding spurious wp_mail_failed triggers.
            if ( $type === 'success' && is_array( $email ) ) {
                add_filter( 'pre_wp_mail', [ $this, 'suppressNextMail' ], 1, 2 );
            }

            return $email;
        }

        /**
         * Short-circuit the next wp_mail() call and immediately self-remove.
         *
         * Registered at priority 1 by filterPluginThemeUpdateEmail() when a
         * success-only auto-update email should be suppressed. Returning true
         * signals "handled" to callers so wp_mail() does not appear to have failed.
         *
         * @param mixed $return Current pre-emption value (null = not yet intercepted).
         * @param array $atts   wp_mail() arguments.
         *
         * @return bool
         */
        public function suppressNextMail( $return, array $atts ): bool {
            remove_filter( 'pre_wp_mail', [ $this, 'suppressNextMail' ], 1 );

            return true;
        }
    }