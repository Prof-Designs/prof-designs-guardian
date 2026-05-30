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
     * @since   0.10.0
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
            return ! defined( 'PROFDESIGNS_GUARDIAN_AUTO_UPDATES' )
                   || (bool) constant( 'PROFDESIGNS_GUARDIAN_AUTO_UPDATES' );
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
            return true;
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
            return true;
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
            return true;
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
         * Filter plugin auto-update emails
         *
         * WordPress passes: ($send, $type, $successful_updates, $failed_updates).
         * Preserve emails when any update fails, suppress success emails.
         *
         * @param bool   $send               Whether to send the email.
         * @param string $type               Update type provided by core ('plugin' or 'theme').
         * @param array  $successful_updates Successful update result items.
         * @param array  $failed_updates     Failed update result items.
         *
         * @return bool
         */
        public function filterPluginUpdateEmail( bool $send, string $type, array $successful_updates, array $failed_updates ): bool {
            if ( ! empty( $failed_updates ) ) {
                return true;
            }

            foreach ( $successful_updates as $update_result ) {
                if ( ! is_array( $update_result ) ) {
                    continue;
                }

                // Extra guard: send email if a malformed successful item contains an error result.
                if ( isset( $update_result['result'] )
                     && ( is_wp_error( $update_result['result'] ) || $update_result['result'] === false ) ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Filter theme auto-update emails.
         *
         * WordPress passes: ($send, $type, $successful_updates, $failed_updates).
         * Preserve emails when any update fails, suppress success emails.
         *
         * @param bool   $send               Whether to send the email.
         * @param string $type               Update type provided by core ('plugin' or 'theme').
         * @param array  $successful_updates Successful update result items.
         * @param array  $failed_updates     Failed update result items.
         *
         * @return bool
         */
        public function filterThemeUpdateEmail( bool $send, string $type, array $successful_updates, array $failed_updates ): bool {
            if ( ! empty( $failed_updates ) ) {
                return true;
            }

            foreach ( $successful_updates as $update_result ) {
                if ( ! is_array( $update_result ) ) {
                    continue;
                }

                // Extra guard: send email if a malformed successful item contains an error result.
                if ( isset( $update_result['result'] )
                     && ( is_wp_error( $update_result['result'] ) || $update_result['result'] === false ) ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Filter core update notification emails
         *
         * @param bool  $send   Whether to send the email
         * @param mixed $update Core update payload (type varies by WP internals)
         *
         * @return bool
         */
        public function filterCoreUpdateNotificationEmail( bool $send, $update = null ): bool {
            // Preserve WordPress default behavior unless explicitly overridden.
            return $send;
        }
    }
