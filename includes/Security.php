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
            if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['action'] ) ) {
                $health_actions = [ 'health-check', 'health-check-loopback', 'health-check-background-updates', 'health-check-files-integrity' ];
                foreach ( $health_actions as $action ) {
                    if ( strpos( $_REQUEST['action'], $action ) !== false ) {
                        add_filter( 'user_has_cap', [ __CLASS__, 'grant_site_health_caps' ], 0, 4 );
                        return;
                    }
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
            $timer_start = microtime( true );

            // Cache lock state for 1 hour to avoid repeated constant checks
            $lock_modifications = get_transient( 'prof_guardian_lock_state_cache' );
            if ( false === $lock_modifications ) {
                $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;
                set_transient( 'prof_guardian_lock_state_cache', $lock_modifications, HOUR_IN_SECONDS );
            }

            if ( ! $lock_modifications ) {
                $exec_time = ( microtime( true ) - $timer_start ) * 1000;
                error_log( sprintf( '[Guardian] block_update_pages: %.2fms (lock disabled)', $exec_time ) );

                return;
            }

            global $pagenow;

            // Block direct access to update-core.php
            if ( $pagenow === 'update-core.php' ) {
                $exec_time = ( microtime( true ) - $timer_start ) * 1000;
                error_log( sprintf( '[Guardian] block_update_pages: %.2fms (blocked access)', $exec_time ) );
                wp_die( __( 'Manual updates are currently disabled. Automatic updates are still active.', 'prof-designs-guardian' ), __( 'Updates Locked', 'prof-designs-guardian' ), [ 'response' => 403 ] );
            }

            $exec_time = ( microtime( true ) - $timer_start ) * 1000;
            if ( $exec_time > 5 ) {
                error_log( sprintf( '[Guardian] block_update_pages: %.2fms', $exec_time ) );
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
            static $call_count = 0;
            static $slow_calls = 0;
            $call_count++;
            $timer_start = microtime( true );

            // Cache lock state for 1 hour to avoid repeated constant checks
            $lock_modifications = get_transient( 'prof_guardian_lock_state_cache' );
            if ( false === $lock_modifications ) {
                $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;
                set_transient( 'prof_guardian_lock_state_cache', $lock_modifications, HOUR_IN_SECONDS );
            }

            if ( ! $lock_modifications ) {
                return $allcaps;
            }

            // ONLY block when we're certain it's a manual update action
            // Be very specific to avoid interfering with other operations
            if ( ! isset( $_REQUEST['action'] ) ) {
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

            $exec_time = ( microtime( true ) - $timer_start ) * 1000;
            prof_guardian_log( sprintf( '[Guardian] Blocked manual update action: %s (%.2fms)', $_REQUEST['action'], $exec_time ) );

            // Track slow calls
            if ( $exec_time > 10 ) {
                $slow_calls++;
                prof_guardian_log( sprintf( '[Guardian] filter_manual_update_caps: SLOW call #%d (%.2fms)', $slow_calls, $exec_time ) );
            }

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
            if ( false === $stored_lock_state ) {
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
                
                $roles_processed++;
            }

            // Store the lock state as integer (1 or 0)
            $new_state = $lock_modifications ? 1 : 0;
            $updated = update_option( 'prof_guardian_lock_state', $new_state, false );

            if ( ! $updated ) {
                prof_guardian_log( '[Guardian] WARNING: Failed to update prof_guardian_lock_state option!' );
            }

            // Clear the cached lock state so next check picks up the change
            delete_transient( 'prof_guardian_lock_state_cache' );

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

            // Cache lock state for 1 hour to avoid repeated constant checks
            $lock_modifications = get_transient( 'prof_guardian_lock_state_cache' );
            if ( false === $lock_modifications ) {
                $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;
                set_transient( 'prof_guardian_lock_state_cache', $lock_modifications, HOUR_IN_SECONDS );
            }

            if ( $lock_modifications ) {
                remove_submenu_page( 'index.php', 'update-core.php' );
            }

            $exec_time = ( microtime( true ) - $timer_start ) * 1000;
            if ( $exec_time > 5 ) {
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
            $last_check = get_option( 'prof_guardian_uploads_setup' );

            // Skip if checked within the last 24 hours
            if ( $last_check && ( time() - $last_check ) < DAY_IN_SECONDS ) {
                return;
            }

            $hours_since = $last_check ? round( ( time() - $last_check ) / 3600, 1 ) : 'never';
            prof_guardian_log( sprintf( '[Guardian] Upload protection check starting (last checked: %s)', $last_check ? $hours_since . ' hours ago' : 'never' ) );

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

                @file_put_contents( $htaccess_file, $htaccess_content );
            }

            // Create index.php to prevent directory listing as fallback
            $index_file = $basedir . '/index.php';

            if ( ! file_exists( $index_file ) ) {
                @file_put_contents( $index_file, '<?php // Silence is golden' );
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
                'phtml',
                'phar',
                'exe',
                'com',
                'bat',
                'cmd',
                'sh',
                'bash',
                'js',
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

                return $file;
            }

            // Check for double extensions (e.g., file.php.jpg)
            $all_extensions = explode( '.', $filename );
            if ( count( $all_extensions ) > 2 ) {
                array_shift( $all_extensions ); // Remove filename part

                foreach ( $all_extensions as $ext ) {
                    if ( in_array( strtolower( $ext ), $dangerous_extensions, true ) ) {
                        $file['error'] = 'Security: Upload blocked. Multiple file extensions detected.';

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

                return $file;
            }

            return $file;
        }
    }
