<?php
    /**
     * Service Provider Contract
     *
     * @package ProfDesigns\Guardian\Contracts
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Contracts;

    /**
     * Interface ServiceProvider
     *
     * @package ProfDesigns\Guardian\Contracts
     * @since   1.0.0
     */
    interface ServiceProvider {
        /**
         * Register services in the container
         *
         * @return void
         */
        public function register(): void;

        /**
         * Bootstrap services after registration
         *
         * @return void
         */
        public function boot(): void;
    }
