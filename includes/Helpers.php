<?php
    /**
     * Helper utilities for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    /**
     * Class Helpers
     *
     * Provides utility methods for the Guardian plugin, including alert throttling
     * and cooldown management using WordPress transients.
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */
    class Helpers {
        /**
         * Determines whether an alert should be sent based on cooldown period.
         *
         * Uses WordPress transients to implement a cooldown mechanism that prevents
         * flooding administrators with duplicate alerts within a specified timeframe.
         *
         * @param string $key      Unique identifier for the alert type.
         * @param int    $cooldown Cooldown period in seconds. Default 3600 (1 hour).
         *
         * @return bool True if alert should be sent, false if within cooldown period.
         *
         * @since 1.0.0
         */
        public static function shouldSendAlert( string $key, int $cooldown = 3600 ): bool {
            $transientKey = 'guardian_' . md5( $key );

            if ( get_transient( $transientKey ) ) {
                return false;
            }

            set_transient( $transientKey, true, $cooldown );

            return true;
        }
    }