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
         * Identifies the pending auto-update suppression email.
         * Set by filterPluginThemeUpdateEmail(), consumed and cleared by suppressNextMail().
         *
         * @var array{to: string|array, subject: string}|null
         */
        private ?array $pendingSuppress = null;

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
         * Enable automatic plugin updates.
         *
         * Runs at priority 999 to ensure it fires after any plugin that opts out via
         * its own `auto_update_plugin` filter (e.g. Wordfence at priority 10 or 99).
         * Always returns true — Guardian's purpose is to keep everything updated.
         *
         * @param mixed $update Whether to update (null = no decision yet, false = opted out).
         * @param mixed $item   Plugin update data object from the updates transient.
         *
         * @return bool
         */
        public function enablePluginUpdates( $update, $item = null ): bool {
            return true;
        }

        /**
         * Enable automatic theme updates.
         *
         * Runs at priority 999 to override any theme opt-outs.
         * Always returns true.
         *
         * @param mixed $update Whether to update (null = no decision yet, false = opted out).
         * @param mixed $item   Theme update data object from the updates transient.
         *
         * @return bool
         */
        public function enableThemeUpdates( $update, $item = null ): bool {
            return true;
        }

        /**
         * Enable automatic core updates.
         *
         * Always returns true. WordPress respects AUTOMATIC_UPDATER_DISABLED and
         * WP_AUTO_UPDATE_CORE = false before the filter is even called.
         *
         * @param mixed $update Whether to update (null = no decision yet, false = opted out).
         * @param mixed $item   Core update offer object from the updates transient.
         *
         * @return bool
         */
        public function enableCoreUpdates( $update, $item = null ): bool {
            return true;
        }

        /**
         * Log failed items from an automatic update run.
         *
         * Hooked to `automatic_updates_complete`. Silent on a clean run;
         * writes one `[Guardian][AutoUpdate] FAILED` line per failed item.
         * Each result object contains:
         *   $result->name   — human-readable package name
         *   $result->item   — update data (new_version, slug, …)
         *   $result->result — true on success, WP_Error or false on failure
         *
         * @param array $results Keyed by type ('plugin','theme','core','translation').
         *
         * @return void
         */
        public function logAutoUpdateResults( array $results ): void {
            $labels = [ 'plugin' => 'Plugin', 'theme' => 'Theme', 'core' => 'Core', 'translation' => 'Translation' ];
            $failed = [];

            foreach ( $labels as $type => $label ) {
                if ( empty( $results[ $type ] ) ) {
                    continue;
                }
                foreach ( $results[ $type ] as $result ) {
                    if ( is_wp_error( $result->result ) || $result->result === false ) {
                        $version  = isset( $result->item->new_version ) ? ' v' . $result->item->new_version : '';
                        $failed[] = sprintf( '%s: %s%s', $label, $result->name, $version );
                    }
                }
            }

            foreach ( $failed as $entry ) {
                prof_guardian_log( '[Guardian][AutoUpdate] FAILED ' . $entry );
            }
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
            if ( $type === 'success' && is_array( $email ) && isset( $email['to'], $email['subject'] ) ) {
                $this->pendingSuppress = [
                    'to'      => $email['to'],
                    'subject' => $email['subject'],
                ];
                add_filter( 'pre_wp_mail', [ $this, 'suppressNextMail' ], 1, 2 );
            }

            return $email;
        }

        /**
         * Short-circuit the next wp_mail() call and immediately self-remove.
         *
         * Registered at priority 1 by filterPluginThemeUpdateEmail() when a
         * success-only auto-update email should be suppressed. The call is matched
         * against the to/subject captured when the filter was registered; if the
         * email does not match, the filter stays registered and passes $return
         * through unchanged so the next wp_mail() call is checked.
         *
         * Returns $return ?? true on a match: preserves any existing non-null
         * $return set by a prior pre_wp_mail callback rather than overriding it.
         *
         * @param mixed $return Current pre-emption value (null = not yet intercepted).
         * @param array $atts   wp_mail() arguments {to, subject, message, headers, attachments}.
         *
         * @return mixed True when we suppress; original $return otherwise.
         */
        public function suppressNextMail( $return, array $atts ) {
            if ( $this->pendingSuppress === null ) {
                remove_filter( 'pre_wp_mail', [ $this, 'suppressNextMail' ], 1 );

                return $return;
            }

            // Only suppress if this is the exact email we targeted.
            // Normalize 'to' via wp_parse_list() on both sides: wp_mail() accepts a
            // string or array and may reformat the value between filter and send.
            $pending_to = array_map( 'trim', wp_parse_list( $this->pendingSuppress['to'] ) );
            $atts_to    = array_map( 'trim', wp_parse_list( $atts['to'] ?? '' ) );

            if ( $atts_to !== $pending_to
                 || (string) ( $atts['subject'] ?? '' ) !== (string) $this->pendingSuppress['subject'] ) {
                // Not our email — leave filter registered so the next wp_mail() is checked.
                return $return;
            }

            // Matched — suppress and clean up.
            remove_filter( 'pre_wp_mail', [ $this, 'suppressNextMail' ], 1 );
            $this->pendingSuppress = null;

            return $return ?? true;
        }
    }