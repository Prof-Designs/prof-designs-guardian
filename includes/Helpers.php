<?php
    /**
     * Helper utilities for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    /**
     * Class Helpers
     *
     * Provides utility methods for the Guardian plugin
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */
    class Helpers {
        /**
         * Determines whether an alert should be sent based on cooldown period
         *
         * @param string $key      Unique identifier for the alert type
         * @param int    $cooldown Cooldown period in seconds. Default 3600 (1 hour)
         *
         * @return bool True if alert should be sent, false if within cooldown period
         *
         * @since 1.0.0
         */
        public static function shouldSendAlert( string $key, int $cooldown = 3600 ): bool {
            $option_key = 'guardian_alert_' . md5( $key );
            $last_sent  = get_option( $option_key );

            // Check if we're still in cooldown period
            if ( $last_sent && ( time() - $last_sent ) < $cooldown ) {
                $minutes_ago      = round( ( time() - $last_sent ) / 60 );
                $cooldown_minutes = round( $cooldown / 60 );
                prof_guardian_log( sprintf( '[Guardian] Alert throttled: Last sent %d min ago (cooldown: %d min)', $minutes_ago, $cooldown_minutes ) );

                return false;
            }

            // Update last sent time
            update_option( $option_key, time(), false );
            prof_guardian_log( '[Guardian] Alert allowed: Cooldown expired or first alert' );

            return true;
        }
    }