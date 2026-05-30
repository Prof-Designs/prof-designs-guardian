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
     * @since   0.10.0
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
                prof_guardian_log( '[Guardian] Auto-updates disabled via PROFDESIGNS_GUARDIAN_AUTO_UPDATES constant' );

                return;
            }

            // Enable automatic updates
            add_filter( 'auto_update_plugin', [ $autoUpdate, 'enablePluginUpdates' ] );
            add_filter( 'auto_update_theme', [ $autoUpdate, 'enableThemeUpdates' ] );
            add_filter( 'auto_update_core', [ $autoUpdate, 'enableCoreUpdates' ] );

            // Filter update notification emails
            add_filter( 'auto_core_update_send_email', [ $autoUpdate, 'filterCoreUpdateEmail' ], 10, 4 );
            add_filter( 'auto_plugin_update_send_email', [ $autoUpdate, 'filterPluginUpdateEmail' ], 10, 4 );
            add_filter( 'auto_theme_update_send_email', [ $autoUpdate, 'filterThemeUpdateEmail' ], 10, 4 );
            add_filter( 'send_core_update_notification_email', [
                $autoUpdate,
                'filterCoreUpdateNotificationEmail',
            ], 10, 2 );

            prof_guardian_log( '[Guardian] Auto-updates enabled' );
        }
    }
