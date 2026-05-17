<?php
    /**
     * Plugin Name: Prof Designs Guardian Loader
     */

    if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
        return;
    }

    $plugin_file = 'prof-designs-guardian/prof-designs-guardian.php';

    require_once WPMU_PLUGIN_DIR . '/' . $plugin_file;

    add_action( 'pre_current_active_plugins', function () use ( $plugin_file ) {
        global $plugins, $wp_list_table;

        $plugin_data = get_plugin_data( WPMU_PLUGIN_DIR . '/' . $plugin_file, false, false );

        if ( empty( $plugin_data['Name'] ) ) {
            $plugin_data['Name'] = $plugin_file;
        }

        $plugins['mustuse'][ $plugin_file ] = $plugin_data;
        $GLOBALS['totals']['mustuse']       = count( $plugins['mustuse'] );

        if ( $GLOBALS['status'] === 'mustuse' ) {
            $wp_list_table->items = $plugins['mustuse'];
            foreach ( $wp_list_table->items as $file => $data ) {
                $wp_list_table->items[ $file ] = _get_plugin_data_markup_translate( $file, $data, false );
            }
        }
    } );
