<?php
    /**
     * Plugin Name: Prof Designs Guardian
     * Plugin URI: https://prof-designs.com/guardian
     * Description: A  plugin that provides automatic updates, error handling, and health checks for your website.
     * Version: 0.1.1
     *
     * Author: Prof Designs
     * Author URI: https://profdesigns.com
     *
     * License: GPL3
     * License URI: https://www.gnu.org/licenses/gpl-3.0.html
     *
     * Requires PHP: 7.4
     * TODO: Update URI: https://prof-designs.com/guardian/update
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */

    declare( strict_types=1 );

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    require_once __DIR__ . '/includes/Helpers.php';
    require_once __DIR__ . '/includes/Mailer.php';
    require_once __DIR__ . '/includes/AutoUpdates.php';
    require_once __DIR__ . '/includes/ErrorHandler.php';
    require_once __DIR__ . '/includes/HealthCheck.php';

    ProfDesigns\Guardian\AutoUpdates::init();
    ProfDesigns\Guardian\ErrorHandler::init();
    ProfDesigns\Guardian\HealthCheck::init();