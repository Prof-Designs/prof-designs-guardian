<?php
    /**
     * Automatic updates manager for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    /**
     * Class AutoUpdates
     *
     * Enables automatic updates for WordPress core, plugins, and themes.
     * Can be disabled via PROFDESIGNS_GUARDIAN_AUTO_UPDATES constant.
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */
    class AutoUpdates {
        /**
         * Enable automatic updates
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function init(): void {
            // Check if auto-updates are enabled (defaults to true)
            $updates_enabled = defined( 'PROFDESIGNS_GUARDIAN_AUTO_UPDATES' ) ? PROFDESIGNS_GUARDIAN_AUTO_UPDATES : true;

            if ( ! $updates_enabled ) {
                prof_guardian_log( '[Guardian] Auto-updates disabled via PROFDESIGNS_GUARDIAN_AUTO_UPDATES constant' );

                return;
            }

            // Enable automatic updates
            add_filter( 'auto_update_plugin', '__return_true' );
            add_filter( 'auto_update_theme', '__return_true' );
            add_filter( 'auto_update_core', '__return_true' );

            // Suppress routine success emails, but preserve failure/security-related notifications
            // until Guardian implements equivalent update notification delivery.
            add_filter( 'auto_core_update_send_email', [ self::class, 'filter_core_update_email' ], 10, 4 );
            add_filter( 'auto_plugin_update_send_email', [ self::class, 'filter_plugin_update_email' ], 10, 2 );
            add_filter( 'auto_theme_update_send_email', [ self::class, 'filter_theme_update_email' ], 10, 2 );
            add_filter( 'send_core_update_notification_email', [ self::class, 'filter_core_update_notification_email' ], 10, 2 );
        }

        /**
         * Preserve core auto-update emails for failures and critical update cases.
         *
         * @param bool       $send        Whether to send the email.
         * @param string     $type        The type of email being sent.
         * @param object     $core_update The update offer that was attempted.
         * @param bool|mixed $result      The update result.
         *
         * @return bool
         *
         * @since 1.0.0
         */
        public static function filter_core_update_email( bool $send, string $type, $core_update, $result ): bool {
            // Always send failure emails
            if ( 'fail' === $type ) {
                return true;
            }
            // Send if update resulted in error
            if ( is_wp_error( $result ) || false === $result ) {
                return true;
            }
            // Suppress success emails
            return false;
        }

        /**
         * Preserve plugin auto-update emails when any update fails.
         *
         * @param bool  $send           Whether to send the email.
         * @param array $update_results Plugin auto-update results.
         *
         * @return bool
         *
         * @since 1.0.0
         */
        public static function filter_plugin_update_email( bool $send, array $update_results ): bool {
            foreach ( $update_results as $update_result ) {
                if ( ! is_array( $update_result ) ) {
                    continue;
                }
                // Check for failed updates
                if (
                    ( isset( $update_result['result'] ) && ( is_wp_error( $update_result['result'] ) || false === $update_result['result'] ) ) ||
                    ( isset( $update_result['successful'] ) && false === $update_result['successful'] )
                ) {
                    return true;
                }
            }
            // No failures detected, suppress email
            return false;
        }

        /**
         * Preserve theme auto-update emails when any update fails.
         *
         * @param bool  $send           Whether to send the email.
         * @param array $update_results Theme auto-update results.
         *
         * @return bool
         *
         * @since 1.0.0
         */
        public static function filter_theme_update_email( bool $send, array $update_results ): bool {
            foreach ( $update_results as $update_result ) {
                if ( ! is_array( $update_result ) ) {
                    continue;
                }
                // Check for failed updates
                if (
                    ( isset( $update_result['result'] ) && ( is_wp_error( $update_result['result'] ) || false === $update_result['result'] ) ) ||
                    ( isset( $update_result['successful'] ) && false === $update_result['successful'] )
                ) {
                    return true;
                }
            }
            // No failures detected, suppress email
            return false;
        }

        /**
         * Do not suppress WordPress core update notification emails.
         *
         * @param bool  $send          Whether to send the email.
         * @param mixed $update_result The core update result.
         *
         * @return bool
         *
         * @since 1.0.0
         */
        public static function filter_core_update_notification_email( bool $send, $update_result ): bool {
            // Allow WordPress to send core update notifications (security updates, etc.)
            return true;
        }
    }