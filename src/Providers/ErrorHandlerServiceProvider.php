<?php
    /**
     * Error Handler Service Provider
     *
     * @package ProfDesigns\Guardian\Providers
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Providers;

    use ProfDesigns\Guardian\Services\ErrorHandlerService;
    use ProfDesigns\Guardian\Services\MailerService;

    /**
     * Class ErrorHandlerServiceProvider
     *
     * Registers and bootstraps error handling features
     *
     * @package ProfDesigns\Guardian\Providers
     * @since   1.0.0
     */
    class ErrorHandlerServiceProvider extends ServiceProvider {
        /**
         * Register services in the container
         *
         * @return void
         */
        public function register(): void {
            $this->app->singleton( ErrorHandlerService::class, function ( $app ) {
                return new ErrorHandlerService( $app, $app->make( MailerService::class ) );
            } );
        }

        /**
         * Bootstrap services after registration
         *
         * @return void
         */
        public function boot(): void {
            /** @var ErrorHandlerService $errorHandler */
            $errorHandler = $this->app->make( ErrorHandlerService::class );

            // Register shutdown handler for fatal errors only.
            register_shutdown_function( [ $errorHandler, 'handleFatalError' ] );

            prof_guardian_log( '[Guardian] Error handler initialized' );
        }
    }
