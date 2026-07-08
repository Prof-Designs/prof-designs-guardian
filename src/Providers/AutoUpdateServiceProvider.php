<?php
    /**
     * Auto Updates Service Provider
     *
     * @package ProfDesigns\Guardian\Providers
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Providers;

    use ProfDesigns\Guardian\Services\AutoUpdateService;

    /**
     * Class AutoUpdateServiceProvider
     *
     * Registers and bootstraps automatic update features
     *
     * @package ProfDesigns\Guardian\Providers
     * @since   1.0.0
     */
    class AutoUpdateServiceProvider extends ServiceProvider {
        /**
         * Register services in the container
         *
         * @return void
         */
        public function register(): void {
            $this->app->singleton( AutoUpdateService::class );
        }

        /**
         * Bootstrap services after registration
         *
         * @return void
         */
        public function boot(): void {
            /** @var AutoUpdateService $autoUpdate */
            $autoUpdate = $this->app->make( AutoUpdateService::class );

            if ( ! $autoUpdate->isEnabled() ) {
                return;
            }

            // Enable automatic updates at priority 999 so Guardian runs after any
            // plugin/theme that opts out via its own auto_update_* filter.
            // Pass 2 args so $item (the update data object) is received.
            add_filter( 'auto_update_plugin', [ $autoUpdate, 'enablePluginUpdates' ], 999, 2 );
            add_filter( 'auto_update_theme', [ $autoUpdate, 'enableThemeUpdates' ], 999, 2 );
            add_filter( 'auto_update_core', [ $autoUpdate, 'enableCoreUpdates' ], 999, 2 );

            // Log outcomes after the full auto-update run completes.
            add_action( 'automatic_updates_complete', [ $autoUpdate, 'logAutoUpdateResults' ] );

            // Filter update notification emails
            add_filter( 'auto_core_update_send_email', [ $autoUpdate, 'filterCoreUpdateEmail' ], 10, 4 );
            add_filter( 'auto_plugin_theme_update_email', [ $autoUpdate, 'filterPluginThemeUpdateEmail' ], 10, 4 );
        }
    }
