<?php
    /**
     * Security Service Provider
     *
     * @package ProfDesigns\Guardian\Providers
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Providers;

    use ProfDesigns\Guardian\Services\SecurityService;

    /**
     * Class SecurityServiceProvider
     *
     * Registers and bootstraps security features
     *
     * @package ProfDesigns\Guardian\Providers
     * @since   0.10.0
     */
    class SecurityServiceProvider extends ServiceProvider {
        /**
         * Register services in the container
         *
         * @return void
         */
        public function register(): void {
            $this->app->singleton( SecurityService::class );
        }

        /**
         * Bootstrap services after registration
         *
         * @return void
         */
        public function boot(): void {
            /** @var SecurityService $security */
            $security = $this->app->make( SecurityService::class );

            // Disable file editors
            $security->disableFileEditors();

            // Only run admin-specific hooks in admin context
            if ( is_admin() ) {
                // Enforce lock mode at capability level to block direct action endpoints.
                add_filter( 'user_has_cap', [ $security, 'enforceLockModCapabilities' ], 1, 4 );

                // Setup hooks on admin_init
                add_action( 'admin_init', function () use ( $security ) {
                    $security->blockUpdatePages();
                    $this->setupCapabilityFilters( $security );
                }, 1 );

                // Remove editor menus
                add_action( 'admin_menu', [ $security, 'removeEditorMenus' ], 999 );

                // Hide locked UI elements
                add_action( 'admin_head', [ $security, 'hideLockedUiElements' ] );
                add_filter( 'plugin_action_links', [ $security, 'removePluginActionLinks' ], 10, 2 );
                add_filter( 'theme_action_links', [ $security, 'removeThemeActionLinks' ], 10, 2 );
                add_action( 'admin_bar_menu', [ $security, 'removeAdminBarUpdates' ], 999 );
            }

            // File upload filtering applies everywhere
            add_filter( 'wp_handle_upload_prefilter', [ $security, 'blockSuspiciousUploads' ] );
        }

        /**
         * Setup capability filters based on current page
         *
         * @param SecurityService $security Security service instance
         *
         * @return void
         */
        protected function setupCapabilityFilters( SecurityService $security ): void {
            global $pagenow;

            // Grant Site Health capabilities for Site Health pages
            if ( $pagenow === 'site-health.php' ) {
                add_filter( 'user_has_cap', [ $security, 'grantSiteHealthCaps' ], 0, 4 );

                return;
            }

            // Grant for Site Health AJAX requests
            if ( defined( 'DOING_AJAX' )
                 && DOING_AJAX
                 && isset( $_REQUEST['action'] )
                 && is_string( $_REQUEST['action'] ) ) {
                $health_actions = [
                    'health-check',
                    'health-check-loopback',
                    'health-check-background-updates',
                    'health-check-files-integrity',
                ];
                if ( in_array( $_REQUEST['action'], $health_actions, true ) ) {
                    add_filter( 'user_has_cap', [ $security, 'grantSiteHealthCaps' ], 0, 4 );
                }
            }
        }
    }
