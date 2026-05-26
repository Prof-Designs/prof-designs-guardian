<?php
    /**
     * Mailer Service Provider
     *
     * @package ProfDesigns\Guardian\Providers
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Providers;

    use ProfDesigns\Guardian\Services\MailerService;

    /**
     * Class MailerServiceProvider
     *
     * Registers and bootstraps mailer features
     *
     * @package ProfDesigns\Guardian\Providers
     * @since   0.10.0
     */
    class MailerServiceProvider extends ServiceProvider {
        /**
         * Register services in the container
         *
         * @return void
         */
        public function register(): void {
            $this->app->singleton( MailerService::class );
        }

        /**
         * Bootstrap services after registration
         *
         * @return void
         */
        public function boot(): void {
            // Register test email cron handler
            add_action( 'guardian_send_test_email', function () {
                /** @var MailerService $mailer */
                $mailer = $this->app->make( MailerService::class );
                $mailer->sendTestEmail();
            } );
        }
    }
