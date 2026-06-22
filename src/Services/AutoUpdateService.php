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
         * @param array  $email              Email data passed to wp_mail() {to, subject, body, headers}.
         * @param string $type               Update outcome: 'success', 'fail', or 'mixed'.
         * @param array  $successful_updates Successful update result items.
         * @param array  $failed_updates     Failed update result items.
         *
         * @return array
         */
        public function filterPluginThemeUpdateEmail( array $email, string $type, array $successful_updates, array $failed_updates ): array {
            // Suppress success-only emails; keep notifications when any update failed.
            if ( $type === 'success' ) {
                $email['to'] = '';
            }

            return $email;
        }

    }
