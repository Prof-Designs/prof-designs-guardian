<?php
    /**
     * Security manager for Prof Designs Guardian
     *
     * @package ProfDesigns\Guardian
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian;

    /**
     * Class Security
     *
     * Provides security hardening by disabling file editors while still allowing
     * automatic updates. This is a safer alternative to DISALLOW_FILE_MODS which
     * blocks both editing AND updates.
     *
     * @package ProfDesigns\Guardian
     * @since   1.0.0
     */
    class Security {
        /**
         * Initialize security features
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function init(): void {
            // Disable file editors (theme and plugin editors in admin)
            if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
                define( 'DISALLOW_FILE_EDIT', true );
            }

            // Only run admin-specific hooks in admin context
            if ( is_admin() ) {
                // Only check capabilities when settings might have changed
                add_action( 'admin_init', [ __CLASS__, 'maybe_update_capabilities' ], 5 );

                // Block access to update pages when locked
                add_action( 'admin_init', [ __CLASS__, 'block_update_pages' ], 1 );

                // Remove editor menu items
                add_action( 'admin_menu', [ __CLASS__, 'remove_editor_menus' ], 999 );

                // Only check uploads protection once per day
                add_action( 'admin_init', [ __CLASS__, 'maybe_protect_uploads_directory' ], 5 );

                // Add capability filter on admin_init when we can properly detect the page
                add_action( 'admin_init', [ __CLASS__, 'maybe_add_capability_filter' ], 0 );

                // Hide locked UI elements when modifications are locked
                add_action( 'admin_head', [ __CLASS__, 'hide_locked_ui_elements' ] );
                add_filter( 'plugin_action_links', [ __CLASS__, 'remove_plugin_action_links' ], 10, 2 );
                add_filter( 'theme_action_links', [ __CLASS__, 'remove_theme_action_links' ], 10, 2 );
                add_action( 'admin_bar_menu', [ __CLASS__, 'remove_admin_bar_updates' ], 999 );
            }

            // File upload filtering applies everywhere
            add_filter( 'wp_handle_upload_prefilter', [ __CLASS__, 'block_suspicious_uploads' ] );
        }


        /**
         * Conditionally add capability filter based on current page
         *
         * Only adds the filter if we're NOT on Site Health pages
         *
         * @return void
         *
         * @since 1.0.1
         */
        public static function maybe_add_capability_filter(): void {
            global $pagenow;

            // CRITICAL: Grant Site Health capability at priority 0, BEFORE WordPress checks at priority 1
            // WordPress's wp_maybe_grant_site_health_caps runs at priority 1 and checks for install_plugins
            // We must grant view_site_health_checks directly at priority 0 to bypass this issue
            if ( $pagenow === 'site-health.php' ) {
                add_filter( 'user_has_cap', [ __CLASS__, 'grant_site_health_caps' ], 0, 4 );

                return;
            }

            // For Site Health AJAX, grant capabilities at priority 0
            if ( defined( 'DOING_AJAX' )
                 && DOING_AJAX
                 && isset( $_REQUEST['action'] )
                 && is_string( $_REQUEST['action'] ) ) {
                $health_actions = [
                    'health-check',
                    'health-check-loopback',
                    'health-check-background-updates',
                    'health-check-files-integrity',
                ];
                if ( in_array( $_REQUEST['action'], $health_actions, true ) ) {
                    add_filter( 'user_has_cap', [ __CLASS__, 'grant_site_health_caps' ], 0, 4 );

                    return;
                }
            }

            // Safe to add the blocking filter now
            add_filter( 'user_has_cap', [ __CLASS__, 'filter_manual_update_caps' ], 10, 4 );
        }

        /**
         * Grant capabilities needed for Site Health checks
         *
         * Site Health needs these capabilities to run tests, even when
         * modifications are locked.
         *
         * CRITICAL: This must run at priority 0, BEFORE WordPress's
         * wp_maybe_grant_site_health_caps filter at priority 1
         *
         * SECURITY: Only grants capabilities to administrators to prevent
         * privilege escalation if non-admin users access Site Health pages
         *
         * @param array    $allcaps All capabilities
         * @param array    $caps    Required capabilities
         * @param array    $args    Additional arguments
         * @param \WP_User $user    User object
         *
         * @return array Modified capabilities
         *
         * @since 1.0.1
         */
        public static function grant_site_health_caps( array $allcaps, array $caps, array $args, $user ): array {
            // SECURITY: Only grant capabilities to administrators
            // Without this check, any user reaching Site Health pages would get admin capabilities
            if ( ! isset( $allcaps['manage_options'] ) || ! $allcaps['manage_options'] ) {
                return $allcaps;
            }

            // Directly grant the Site Health capability that WordPress checks for
            $allcaps['view_site_health_checks'] = true;

            // Also grant the underlying capabilities so Site Health tests can run properly
            $allcaps['install_plugins'] = true;
            $allcaps['update_plugins']  = true;
            $allcaps['update_themes']   = true;
            $allcaps['update_core']     = true;

            // Note: Logging removed to prevent log pollution (this is called on every capability check)

            return $allcaps;
        }

        /**
         * Block direct access to update pages when modifications are locked
         *
         * @return void
         *
         * @since 1.0.1
         */
        public static function block_update_pages(): void {
            // Check lock state directly from constant (defined() is essentially free)
            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            if ( ! $lock_modifications ) {
                return;
            }

            global $pagenow;

            // Block direct access to update and installation pages
            $blocked_pages = [
                'update-core.php',
                'plugin-install.php',
                'theme-install.php',
            ];

            if ( in_array( $pagenow, $blocked_pages, true ) ) {
                $support_email = Helpers::get_support_email();
                $message       = __( 'Manual modifications are currently disabled for security. Automatic updates are still active.', 'prof-designs-guardian' )
                                 . '<br><br>'
                                 . sprintf(
                                     /* translators: %1$s and %3$s are HTML tags for emphasis, %2$s is the support email address */
                                     __( 'If you need to make changes, please contact support: %1$s%2$s%3$s', 'prof-designs-guardian' ),
                                     '<strong>',
                                     esc_html( $support_email ),
                                     '</strong>'
                                 );

                wp_die( $message, __( 'Modifications Locked', 'prof-designs-guardian' ), [ 'response' => 403 ] );
            }
        }

        /**
         * Filter manual update capabilities in admin context
         *
         * Allows viewing update pages but blocks actual update actions
         * when LOCK_MODS is enabled.
         *
         * Note: This filter is not added on Site Health pages to avoid interference.
         *
         * @param array    $allcaps All capabilities
         * @param array    $caps    Required capabilities
         * @param array    $args    Additional arguments
         * @param \WP_User $user    User object
         *
         * @return array Modified capabilities
         *
         * @since 1.0.1
         */
        public static function filter_manual_update_caps( array $allcaps, array $caps, array $args, $user ): array {
            // Check lock state directly from constant (defined() is essentially free)
            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            if ( ! $lock_modifications ) {
                return $allcaps;
            }

            // ONLY block when we're certain it's a manual update action
            // Be very specific to avoid interfering with other operations
            if ( ! isset( $_REQUEST['action'] ) || ! is_string( $_REQUEST['action'] ) ) {
                return $allcaps;
            }

            $blocked_actions = [
                'install-plugin',        // Block manual plugin installation from repository
                'upload-plugin',         // Block plugin upload
                'update-plugin',
                'update-selected',
                'delete-selected',
                'update-theme',
                'do-plugin-upgrade',
                'do-theme-upgrade',
            ];

            if ( ! in_array( $_REQUEST['action'], $blocked_actions, true ) ) {
                return $allcaps;
            }

            // Only now do we modify capabilities
            $allcaps['install_plugins'] = false;  // Block actual installation attempts
            $allcaps['update_plugins']  = false;
            $allcaps['update_themes']   = false;
            $allcaps['delete_plugins']  = false;
            $allcaps['delete_themes']   = false;

            // Sanitize action for logging to prevent log injection attacks
            $safe_action = sanitize_key( $_REQUEST['action'] );
            prof_guardian_log( sprintf( '[Guardian] Blocked manual update action: %s', $safe_action ) );

            return $allcaps;
        }

        /**
         * Check if capabilities need updating
         *
         * Only runs when lock state changes or first time
         *
         * @return void
         *
         * @since 1.0.1
         */
        public static function maybe_update_capabilities(): void {
            $timer_start = microtime( true );

            // Prevent rapid repeated runs (race condition protection)
            if ( get_transient( 'prof_guardian_caps_updating' ) ) {
                return;
            }

            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            $stored_lock_state = get_option( 'prof_guardian_lock_state' );

            // Convert stored value to boolean for comparison
            $stored_bool = ( $stored_lock_state === '1' || $stored_lock_state === 1 || $stored_lock_state === true );

            // First run: option doesn't exist yet
            if ( $stored_lock_state === false ) {
                // Set a temporary lock to prevent concurrent runs
                set_transient( 'prof_guardian_caps_updating', 1, 10 );

                prof_guardian_log( sprintf( '[Guardian] Initial capability setup (lock=%s)', $lock_modifications ? 'true' : 'false' ) );
                self::remove_editor_capabilities();

                delete_transient( 'prof_guardian_caps_updating' );

                $exec_time = ( microtime( true ) - $timer_start ) * 1000;
                prof_guardian_log( sprintf( '[Guardian] Initial setup complete: %.2fms', $exec_time ) );

                return;
            }

            // Skip if nothing changed
            if ( $stored_bool === $lock_modifications ) {
                return;
            }

            // Set a temporary lock to prevent concurrent runs
            set_transient( 'prof_guardian_caps_updating', 1, 10 );

            prof_guardian_log( sprintf( '[Guardian] Lock state changed: %s -> %s', $stored_bool ? 'true' : 'false', $lock_modifications ? 'true' : 'false' ) );

            // Update capabilities
            self::remove_editor_capabilities();

            delete_transient( 'prof_guardian_caps_updating' );

            $exec_time = ( microtime( true ) - $timer_start ) * 1000;
            prof_guardian_log( sprintf( '[Guardian] Capabilities updated: %.2fms', $exec_time ) );
        }

        /**
         * Remove file editing capabilities from all roles
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function remove_editor_capabilities(): void {
            $timer_start = microtime( true );

            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            $wp_roles = wp_roles();

            if ( ! $wp_roles ) {
                return;
            }

            $roles_processed = 0;
            foreach ( $wp_roles->roles as $role_name => $role_info ) {
                $role = get_role( $role_name );

                if ( ! $role ) {
                    continue;
                }

                // Always remove file editing capabilities
                $role->remove_cap( 'edit_themes' );
                $role->remove_cap( 'edit_plugins' );
                $role->remove_cap( 'edit_files' );

                // Handle installation/modification capabilities
                // IMPORTANT: We keep install_plugins to allow Site Health access
                // Site Health requires this capability to grant view_site_health_checks
                // Actual plugin installations are blocked via runtime filter
                $mod_caps = [
                    'upload_plugins',
                    'delete_plugins',
                    'install_themes',
                    'upload_themes',
                    'delete_themes',
                ];

                foreach ( $mod_caps as $cap ) {
                    if ( $lock_modifications ) {
                        $role->remove_cap( $cap );
                    } else {
                        if ( isset( $role_info['capabilities'][ $cap ] ) ) {
                            $role->add_cap( $cap );
                        }
                    }
                }

                $roles_processed ++;
            }

            // Store the lock state as integer (1 or 0)
            $new_state = $lock_modifications ? 1 : 0;
            $updated   = update_option( 'prof_guardian_lock_state', $new_state, false );

            // Note: update_option() returns false if the value hasn't changed (WordPress optimization)
            // This is not an error - verify the actual stored value instead
            if ( ! $updated ) {
                $stored_value = get_option( 'prof_guardian_lock_state' );
                if ( $stored_value != $new_state ) {
                    prof_guardian_log( sprintf( '[Guardian] WARNING: Failed to update prof_guardian_lock_state! Expected: %d, Got: %s', $new_state, $stored_value ) );
                }
                // If values match, WordPress just skipped the update (no change needed)
            }

            $exec_time = ( microtime( true ) - $timer_start ) * 1000;
            prof_guardian_log( sprintf( '[Guardian] Capabilities updated for %d roles: %.2fms (lock state: %d)', $roles_processed, $exec_time, $new_state ) );
        }

        /**
         * Remove editor menu pages
         *
         * Removes theme and plugin editor pages from the admin menu
         * as a final failsafe.
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function remove_editor_menus(): void {
            $timer_start = microtime( true );

            // Always remove file editors
            remove_submenu_page( 'themes.php', 'theme-editor.php' );
            remove_submenu_page( 'plugins.php', 'plugin-editor.php' );

            // Check lock state directly from constant (defined() is essentially free)
            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            if ( $lock_modifications ) {
                remove_submenu_page( 'index.php', 'update-core.php' );
                remove_submenu_page( 'plugins.php', 'plugin-install.php' );  // Remove "Add New" under Plugins
                remove_submenu_page( 'themes.php', 'theme-install.php' );    // Remove "Add New" under Appearance
            }

            $exec_time = ( microtime( true ) - $timer_start ) * 1000;
            // Only log if execution time is unusually slow (> 20ms)
            if ( $exec_time > 20 ) {
                prof_guardian_log( sprintf( '[Guardian] remove_editor_menus: %.2fms (slow)', $exec_time ) );
            }
        }

        /**
         * Check if uploads directory protection is needed
         *
         * Only runs once per day to avoid checking filesystem on every page load
         *
         * @return void
         *
         * @since 1.0.1
         */
        public static function maybe_protect_uploads_directory(): void {
            $timer_start = microtime( true );
            $last_check  = get_option( 'prof_guardian_uploads_setup' );

            // Skip if checked within the last 24 hours
            if ( $last_check && ( time() - $last_check ) < DAY_IN_SECONDS ) {
                return;
            }

            $hours_since = $last_check ? round( ( time() - $last_check ) / 3600, 1 ) : 'never';
            prof_guardian_log( sprintf( '[Guardian] Upload protection check starting (last checked: %s)', $last_check ? $hours_since
                                                                                                                        . ' hours ago' : 'never' ) );

            // Check and protect uploads directory
            self::protect_uploads_directory();

            // Update last check time
            update_option( 'prof_guardian_uploads_setup', time(), false );

            $exec_time = ( microtime( true ) - $timer_start ) * 1000;
            prof_guardian_log( sprintf( '[Guardian] Upload protection check complete: %.2fms (next check in 24 hours)', $exec_time ) );
        }

        /**
         * Protect uploads directory from PHP execution
         *
         * Creates .htaccess and index.php files in the uploads directory
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function protect_uploads_directory(): void {
            $upload_dir = wp_upload_dir();
            $basedir    = $upload_dir['basedir'];

            if ( ! $basedir || ! is_dir( $basedir ) ) {
                return;
            }

            // Create .htaccess to block PHP execution
            $htaccess_file = $basedir . '/.htaccess';

            if ( ! file_exists( $htaccess_file ) ) {
                $htaccess_content = <<<'HTACCESS'
# Prevent PHP execution in uploads directory
<FilesMatch "\.(?i:php)$">
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
</FilesMatch>

# Disable directory listing
Options -Indexes
HTACCESS;

                $result = @file_put_contents( $htaccess_file, $htaccess_content );
                if ( $result === false ) {
                    prof_guardian_log( '[Guardian] WARNING: Failed to create .htaccess in uploads directory' );
                } else {
                    prof_guardian_log( '[Guardian] Created .htaccess in uploads directory' );
                }
            }

            // Create index.php to prevent directory listing as fallback
            $index_file = $basedir . '/index.php';

            if ( ! file_exists( $index_file ) ) {
                $result = @file_put_contents( $index_file, '<?php // Silence is golden' );
                if ( $result === false ) {
                    prof_guardian_log( '[Guardian] WARNING: Failed to create index.php in uploads directory' );
                } else {
                    prof_guardian_log( '[Guardian] Created index.php in uploads directory' );
                }
            }
        }

        /**
         * Block suspicious file uploads
         *
         * Prevents uploading of potentially dangerous file types including
         * PHP files, executables, and files with suspicious extensions.
         *
         * @param array $file Upload file data
         *
         * @return array Modified file data or error
         *
         * @since 1.0.0
         */
        public static function block_suspicious_uploads( array $file ): array {
            $dangerous_extensions = [
                'php',
                'php3',
                'php4',
                'php5',
                'php7',
                'php8',
                'pht',
                'phtml',
                'phar',
                'exe',
                'com',
                'bat',
                'cmd',
                'sh',
                'bash',
                'jar',
                'vbs',
                'pl',
                'cgi',
                'asp',
                'aspx',
                'shtml',
                'shtm',
                'fcgi',
                'fpl',
                'dll',
                'so',
            ];

            $filename  = $file['name'] ?? '';
            $file_ext  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
            $file_type = $file['type'] ?? '';

            // Check file extension
            if ( in_array( $file_ext, $dangerous_extensions, true ) ) {
                $file['error'] = sprintf( 'Security: Upload blocked. "%s" files are not allowed for security reasons.', $file_ext );
                prof_guardian_log( sprintf( '[Guardian] BLOCKED file upload: %s (extension: %s)', $filename, $file_ext ) );

                return $file;
            }

            // Check for double extensions (e.g., file.php.jpg)
            $all_extensions = explode( '.', $filename );
            if ( count( $all_extensions ) > 2 ) {
                array_shift( $all_extensions ); // Remove filename part

                foreach ( $all_extensions as $ext ) {
                    if ( in_array( strtolower( $ext ), $dangerous_extensions, true ) ) {
                        $file['error'] = 'Security: Upload blocked. Multiple file extensions detected.';
                        prof_guardian_log( sprintf( '[Guardian] BLOCKED file upload: %s (double extension detected)', $filename ) );

                        return $file;
                    }
                }
            }

            // Check MIME type for common executable types
            $dangerous_mimes = [
                'application/x-php',
                'application/x-httpd-php',
                'application/x-httpd-php-source',
                'application/x-sh',
                'application/x-executable',
                'application/x-msdownload',
            ];

            if ( in_array( $file_type, $dangerous_mimes, true ) ) {
                $file['error'] = 'Security: Upload blocked. File type not allowed.';
                prof_guardian_log( sprintf( '[Guardian] BLOCKED file upload: %s (MIME type: %s)', $filename, $file_type ) );

                return $file;
            }

            return $file;
        }

        /**
         * Hide UI elements for locked modifications
         *
         * Hides "Add New" buttons, upload sections, and update action links
         * when PROFDESIGNS_GUARDIAN_LOCK_MODS is enabled.
         *
         * @return void
         *
         * @since 0.8.0
         */
        public static function hide_locked_ui_elements(): void {
            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            if ( ! $lock_modifications ) {
                return;
            }

            // Hide "Add New" buttons, upload sections, and update links via CSS
            echo '<style>
                /* Hide Add New buttons for plugins and themes */
                .page-title-action[href*="plugin-install.php"],
                .page-title-action[href*="theme-install.php"],
                a.upload-view-toggle,
                .upload-plugin-wrap,
                .upload-theme,
                
                /* Hide update action buttons/links (preserve informational notices) */
                .update-message a.update-link,
                .update-message button,
                .plugin-update-tr a.update-link,
                .theme-update-tr a.update-link,
                a.update-link,
                
                /* Hide "Update Now" buttons in plugin/theme cards */
                .plugin-card .update-now,
                .theme-card .update-now,
                
                /* Hide update count bubbles in menu */
                #menu-plugins .update-plugins,
                #menu-appearance .update-plugins,
                
                /* Hide bulk selection controls (checkboxes) */
                .plugins .check-column input[type="checkbox"],
                .themes .check-column input[type="checkbox"],
                #cb-select-all-1,
                #cb-select-all-2,
                
                /* Hide bulk update actions from dropdown */
                .plugins .tablenav .bulkactions select option[value="update-selected"],
                .themes .tablenav .bulkactions select option[value="update-selected"],
                
                /* Hide the entire upload plugin section */
                .wrap .upload-plugin-wrap {
                    display: none !important;
                }
            </style>';
        }

        /**
         * Remove plugin action links when modifications are locked
         *
         * Removes "Update now", "Delete", and other modification links
         * from the plugin list table.
         *
         * @param array  $actions     Plugin action links
         * @param string $plugin_file Plugin file path
         *
         * @return array Filtered action links
         *
         * @since 0.8.0
         */
        public static function remove_plugin_action_links( array $actions, string $plugin_file ): array {
            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            if ( ! $lock_modifications ) {
                return $actions;
            }

            // Remove modification-related action links
            unset( $actions['delete'] );

            // Note: "update" links are handled by the CSS hiding above
            // We don't need to unset them here as they won't be visible

            return $actions;
        }

        /**
         * Remove theme action links when modifications are locked
         *
         * Removes "Delete" and other modification links from theme rows.
         *
         * @param array  $actions Theme action links
         * @param object $theme   Theme object
         *
         * @return array Filtered action links
         *
         * @since 0.8.0
         */
        public static function remove_theme_action_links( array $actions, $theme ): array {
            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            if ( ! $lock_modifications ) {
                return $actions;
            }

            // Remove modification-related action links
            unset( $actions['delete'] );

            return $actions;
        }

        /**
         * Remove update count from admin bar when modifications are locked
         *
         * Removes the "X Updates" item from the admin bar to avoid confusion
         * when users can't manually update.
         *
         * @param \WP_Admin_Bar $wp_admin_bar WordPress admin bar object
         *
         * @return void
         *
         * @since 0.8.0
         */
        public static function remove_admin_bar_updates( $wp_admin_bar ): void {
            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            if ( ! $lock_modifications ) {
                return;
            }

            // Remove the updates menu item from admin bar
            $wp_admin_bar->remove_node( 'updates' );
        }
    }
