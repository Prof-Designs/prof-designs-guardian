<?php
    /**
     * Setup Service Provider
     *
     * @package ProfDesigns\Guardian\Providers
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Providers;

    use ProfDesigns\Guardian\Services\SecurityService;

    /**
     * Class SetupServiceProvider
     *
     * Handles one-time setup tasks and initialization
     *
     * @package ProfDesigns\Guardian\Providers
     * @since   1.0.0
     */
    class SetupServiceProvider extends ServiceProvider {
        /**
         * Register services in the container
         *
         * @return void
         */
        public function register(): void {
            // No services to register
        }

        /**
         * Bootstrap services after registration
         *
         * @return void
         */
        public function boot(): void {
            // Run setup on init
            add_action( 'init', [ $this, 'runSetup' ], 5 );

            // Run uploads protection on init
            add_action( 'init', [ $this, 'protectUploads' ], 5 );

            // Restore capabilities on admin_init if needed
            if ( is_admin() && ! get_option( 'prof_guardian_caps_restored_v2' ) ) {
                add_action( 'admin_init', [ $this, 'restoreCapabilities' ], 3 );
            }
        }

        /**
         * Run one-time setup
         *
         * @return void
         */
        public function runSetup(): void {
            // Only run in admin or CLI context
            if ( ! is_admin() && ! ( defined( 'WP_CLI' ) && (bool) constant( 'WP_CLI' ) ) ) {
                return;
            }

            // Check if setup has already been done
            if ( get_option( 'prof_guardian_setup_done' ) ) {
                return;
            }

            // Ensure wp_roles() is available
            if ( ! function_exists( 'wp_roles' ) ) {
                prof_guardian_log( '[Guardian] WARNING: wp_roles() not available yet, deferring setup' );

                return;
            }

            prof_guardian_log( '[Guardian] === INITIAL SETUP STARTING ===' );

            /** @var SecurityService $security */
            $security = $this->app->make( SecurityService::class );
            $security->removeEditorCapabilities();

            // Mark setup as complete
            update_option( 'prof_guardian_setup_done', true, false );

            // Schedule test email
            wp_schedule_single_event( time() + 60, 'guardian_send_test_email' );
            prof_guardian_log( '[Guardian] Scheduled test email to send in 60 seconds' );

            prof_guardian_log( '[Guardian] === INITIAL SETUP COMPLETE ===' );
        }

        /**
         * Protect uploads directory
         *
         * @return void
         */
        public function protectUploads(): void {
            $last_setup = (int) get_option( 'prof_guardian_uploads_setup', 0 );
            // Re-check daily in case files are removed/overwritten during restores or migrations.
            if ( $last_setup && ( time() - $last_setup ) < DAY_IN_SECONDS ) {
                return;
            }

            prof_guardian_log( '[Guardian] Protecting uploads directory...' );

            /** @var SecurityService $security */
            $security = $this->app->make( SecurityService::class );
            $security->protectUploadsDirectory();

            update_option( 'prof_guardian_uploads_setup', time(), false );
            prof_guardian_log( '[Guardian] Uploads directory protection complete' );
        }

        /**
         * Restore capabilities for Site Health compatibility
         *
         * @return void
         */
        public function restoreCapabilities(): void {
            // Check if restoration has already been done
            if ( get_option( 'prof_guardian_caps_restored_v2' ) ) {
                return;
            }

            prof_guardian_log( '[Guardian] Restoring install_plugins capability for Site Health compatibility...' );

            $admin_role = get_role( 'administrator' );
            if ( $admin_role && ! $admin_role->has_cap( 'install_plugins' ) ) {
                $admin_role->add_cap( 'install_plugins' );
                prof_guardian_log( '[Guardian] Restored install_plugins to administrator role' );
            } else {
                prof_guardian_log( '[Guardian] Administrator already has install_plugins capability' );
            }

            // Mark restoration as complete
            update_option( 'prof_guardian_caps_restored_v2', true, false );

            prof_guardian_log( '[Guardian] Capability restoration complete' );
        }
    }
