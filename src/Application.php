<?php
    /**
     * Application Container for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    use ProfDesigns\Guardian\Contracts\ServiceProvider;

    /**
     * Class Application
     *
     * Simplified Laravel-style application container with service provider support
     *
     * @package ProfDesigns\Guardian
     * @since   0.10.0
     */
    class Application {
        /**
         * The application instance
         *
         * @var Application|null
         */
        protected static ?Application $instance = null;

        /**
         * Container bindings
         *
         * @var array<string, mixed>
         */
        protected array $bindings = [];

        /**
         * Singleton instances
         *
         * @var array<string, object>
         */
        protected array $instances = [];

        /**
         * Registered service providers
         *
         * @var array<ServiceProvider>
         */
        protected array $serviceProviders = [];

        /**
         * Booted service providers
         *
         * @var array<string, bool>
         */
        protected array $bootedProviders = [];

        /**
         * Plugin base path
         *
         * @var string
         */
        protected string $basePath;

        /**
         * Application constructor
         *
         * @param string $basePath Plugin base path
         */
        protected function __construct( string $basePath ) {
            $this->basePath   = rtrim( $basePath, '/\\' );
            static::$instance = $this;

            // Register the container as a singleton
            $this->instance( self::class, $this );
            $this->instance( Application::class, $this );
        }

        /**
         * Get the application instance
         *
         * @param string|null $basePath Plugin base path (required for first instantiation)
         *
         * @return Application
         */
        public static function getInstance( ?string $basePath = null ): Application {
            if ( static::$instance === null ) {
                if ( $basePath === null ) {
                    throw new \RuntimeException( 'Application base path required for first instantiation' );
                }
                static::$instance = new static( $basePath );
            }

            return static::$instance;
        }

        /**
         * Get the base path of the plugin
         *
         * @param string $path Optional path to append
         *
         * @return string
         */
        public function basePath( string $path = '' ): string {
            return $this->basePath . ( $path ? DIRECTORY_SEPARATOR . ltrim( $path, '/\\' ) : '' );
        }

        /**
         * Register a binding with the container
         *
         * @param string               $abstract Abstract type
         * @param callable|string|null $concrete Concrete implementation
         * @param bool                 $shared   Whether to share the instance
         *
         * @return void
         */
        public function bind( string $abstract, $concrete = null, bool $shared = false ): void {
            $concrete = $concrete ?? $abstract;

            $this->bindings[ $abstract ] = [
                'concrete' => $concrete,
                'shared'   => $shared,
            ];
        }

        /**
         * Register a shared binding (singleton) with the container
         *
         * @param string               $abstract Abstract type
         * @param callable|string|null $concrete Concrete implementation
         *
         * @return void
         */
        public function singleton( string $abstract, $concrete = null ): void {
            $this->bind( $abstract, $concrete, true );
        }

        /**
         * Register an existing instance as shared in the container
         *
         * @param string $abstract Abstract type
         * @param mixed  $instance Instance to register
         *
         * @return void
         */
        public function instance( string $abstract, $instance ): void {
            $this->instances[ $abstract ] = $instance;
        }

        /**
         * Resolve a type from the container
         *
         * @param string $abstract Abstract type to resolve
         *
         * @return mixed
         */
        public function make( string $abstract ) {
            // Return existing instance if available
            if ( isset( $this->instances[ $abstract ] ) ) {
                return $this->instances[ $abstract ];
            }

            // Get concrete implementation
            $concrete = $this->bindings[ $abstract ]['concrete'] ?? $abstract;
            $shared   = $this->bindings[ $abstract ]['shared'] ?? false;

            // Build the instance
            $instance = $this->build( $concrete );

            // Store if shared
            if ( $shared ) {
                $this->instances[ $abstract ] = $instance;
            }

            return $instance;
        }

        /**
         * Build a concrete instance
         *
         * @param callable|string $concrete Concrete implementation
         *
         * @return mixed
         */
        protected function build( $concrete ) {
            // If it's a closure, execute it
            if ( $concrete instanceof \Closure ) {
                return $concrete( $this );
            }

            // If it's a string, instantiate the class
            if ( is_string( $concrete ) ) {
                if ( ! class_exists( $concrete ) ) {
                    throw new \RuntimeException( "Class {$concrete} does not exist" );
                }

                return new $concrete( $this );
            }

            return $concrete;
        }

        /**
         * Register a service provider
         *
         * @param ServiceProvider|string $provider Service provider instance or class name
         *
         * @return void
         */
        public function register( $provider ): void {
            // If it's a string, instantiate it
            if ( is_string( $provider ) ) {
                $provider = new $provider( $this );
            }

            // Call register method
            $provider->register();

            // Store provider
            $this->serviceProviders[] = $provider;
        }

        /**
         * Boot all registered service providers
         *
         * @return void
         */
        public function boot(): void {
            foreach ( $this->serviceProviders as $provider ) {
                $this->bootProvider( $provider );
            }
        }

        /**
         * Boot a service provider
         *
         * @param ServiceProvider $provider Service provider to boot
         *
         * @return void
         */
        protected function bootProvider( ServiceProvider $provider ): void {
            $providerClass = get_class( $provider );

            if ( isset( $this->bootedProviders[ $providerClass ] ) ) {
                return;
            }

            $provider->boot();
            $this->bootedProviders[ $providerClass ] = true;
        }
    }
