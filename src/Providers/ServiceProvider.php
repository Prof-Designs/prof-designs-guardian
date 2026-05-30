<?php
    /**
     * Abstract Service Provider
     *
     * @package ProfDesigns\Guardian\Providers
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Providers;

    use ProfDesigns\Guardian\Application;
    use ProfDesigns\Guardian\Contracts\ServiceProvider as ServiceProviderContract;

    /**
     * Class ServiceProvider
     *
     * Base service provider implementation
     *
     * @package ProfDesigns\Guardian\Providers
     * @since   1.0.0
     */
    abstract class ServiceProvider implements ServiceProviderContract {
        /**
         * The application instance
         *
         * @var Application
         */
        protected Application $app;

        /**
         * ServiceProvider constructor
         *
         * @param Application $app Application instance
         */
        public function __construct( Application $app ) {
            $this->app = $app;
        }

        /**
         * Register services in the container
         *
         * @return void
         */
        abstract public function register(): void;

        /**
         * Bootstrap services after registration
         *
         * @return void
         */
        public function boot(): void {
            // Default empty implementation
        }
    }
