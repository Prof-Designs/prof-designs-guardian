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
     * @since   1.0.0
     */
    class HealthCheckServiceProvider extends ServiceProvider {
        /**
         * Health check cron hook name.
         */
        private const HEALTH_CHECK_CRON_HOOK = 'prof_guardian_run_health_check';

        /**
         * Health check Action Scheduler hook name.
         */
        private const HEALTH_CHECK_AS_HOOK = 'prof_guardian_run_health_check_action';

        /**
         * Option key for last scheduler reconciliation timestamp.
         */
        private const SCHEDULE_RECONCILED_AT_OPTION = 'prof_guardian_health_schedule_reconciled_at';

        /**
         * Minimum interval between scheduler reconciliation runs (seconds).
         */
        private const SCHEDULE_RECONCILE_INTERVAL = 900;

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

            // Register scheduled health check callback.
            add_action( self::HEALTH_CHECK_CRON_HOOK, [ $healthCheck, 'runScheduledHealthCheck' ] );
            add_action( self::HEALTH_CHECK_AS_HOOK, [ $healthCheck, 'runScheduledHealthCheck' ] );

            // Ensure a recurring hourly health check is scheduled.
            add_action( 'init', [ $this, 'ensureScheduledHealthCheck' ], 20 );

            prof_guardian_log( '[Guardian] Health check initialized' );
        }

        /**
         * Ensure the recurring health check event exists.
         *
         * @return void
         */
        public function ensureScheduledHealthCheck(): void {
            $last_reconciled_at = (int) get_option( self::SCHEDULE_RECONCILED_AT_OPTION, 0 );
            if ( $last_reconciled_at && ( time() - $last_reconciled_at ) < self::SCHEDULE_RECONCILE_INTERVAL ) {
                return;
            }

            // Prefer Action Scheduler if available so checks are visible in Scheduled Actions list.
            if ( function_exists( 'as_next_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
                // Keep only one backend: clear WP-Cron schedule when Action Scheduler is active.
                if ( wp_next_scheduled( self::HEALTH_CHECK_CRON_HOOK ) ) {
                    wp_clear_scheduled_hook( self::HEALTH_CHECK_CRON_HOOK );
                }

                if ( as_next_scheduled_action( self::HEALTH_CHECK_AS_HOOK, [], 'prof-designs-guardian' ) ) {
                    update_option( self::SCHEDULE_RECONCILED_AT_OPTION, time(), false );

                    return;
                }

                as_schedule_recurring_action( time()
                                              + MINUTE_IN_SECONDS, HOUR_IN_SECONDS, self::HEALTH_CHECK_AS_HOOK, [], 'prof-designs-guardian' );
                update_option( self::SCHEDULE_RECONCILED_AT_OPTION, time(), false );

                return;
            }

            // Keep only one backend: clear Action Scheduler schedule when falling back to WP-Cron.
            if ( function_exists( 'as_unschedule_all_actions' )
                 && function_exists( 'as_next_scheduled_action' )
                 && as_next_scheduled_action( self::HEALTH_CHECK_AS_HOOK, [], 'prof-designs-guardian' ) ) {
                as_unschedule_all_actions( self::HEALTH_CHECK_AS_HOOK, [], 'prof-designs-guardian' );
            }

            if ( wp_next_scheduled( self::HEALTH_CHECK_CRON_HOOK ) ) {
                update_option( self::SCHEDULE_RECONCILED_AT_OPTION, time(), false );

                return;
            }

            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HEALTH_CHECK_CRON_HOOK );
            update_option( self::SCHEDULE_RECONCILED_AT_OPTION, time(), false );
        }
    }
