<?php
    /**
     * Health Check Service Provider
     *
     * @package ProfDesigns\Guardian\Providers
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Providers;

    use ProfDesigns\Guardian\Services\HealthCheckService;
    use ProfDesigns\Guardian\Services\MailerService;

    /**
     * Class HealthCheckServiceProvider
     *
     * Registers and bootstraps health check features
     *
     * @package ProfDesigns\Guardian\Providers
     * @since   0.10.0
     */
    class HealthCheckServiceProvider extends ServiceProvider {
        /**
         * Register services in the container
         *
         * @return void
         */
        public function register(): void {
            $this->app->singleton( HealthCheckService::class, function ( $app ) {
                return new HealthCheckService( $app, $app->make( MailerService::class ) );
            } );
        }

        /**
         * Bootstrap services after registration
         *
         * @return void
         */
        public function boot(): void {
            /** @var HealthCheckService $healthCheck */
            $healthCheck = $this->app->make( HealthCheckService::class );

            // Register REST API endpoint
            add_action( 'rest_api_init', [ $healthCheck, 'registerEndpoint' ] );

            prof_guardian_log( '[Guardian] Health check initialized' );
        }
    }
