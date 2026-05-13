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

            // Only check capabilities when settings might have changed
            add_action( 'admin_init', [ __CLASS__, 'maybe_update_capabilities' ], 5 );

            // Remove editor menu items
            add_action( 'admin_menu', [ __CLASS__, 'remove_editor_menus' ], 999 );

            // Only check uploads protection once per day instead of every page load
            add_action( 'init', [ __CLASS__, 'maybe_protect_uploads_directory' ], 5 );

            // Filter suspicious file uploads
            add_filter( 'wp_handle_upload_prefilter', [ __CLASS__, 'block_suspicious_uploads' ] );
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
            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            $stored_lock_state = get_option( 'prof_guardian_lock_state' );

            // Skip if nothing changed
            if ( $stored_lock_state !== false && $stored_lock_state === (int) $lock_modifications ) {
                return;
            }

            // Update capabilities
            self::remove_editor_capabilities();
        }

        /**
         * Remove file editing capabilities from all roles
         *
         * @return void
         *
         * @since 1.0.0
         */
        public static function remove_editor_capabilities(): void {
            $lock_modifications = defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) ? PROFDESIGNS_GUARDIAN_LOCK_MODS : true;

            $wp_roles = wp_roles();

            if ( ! $wp_roles ) {
                return;
            }

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
                $mod_caps = [
                    'install_plugins',
                    'upload_plugins',
                    'delete_plugins',
                    'update_plugins',
                    'install_themes',
                    'upload_themes',
                    'delete_themes',
                    'update_themes',
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
            }

            // Store the lock state
            update_option( 'prof_guardian_lock_state', (int) $lock_modifications, false );
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
            remove_submenu_page( 'themes.php', 'theme-editor.php' );
            remove_submenu_page( 'plugins.php', 'plugin-editor.php' );
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
            $last_check  = get_option( 'prof_guardian_uploads_setup' );
            $hours_since = $last_check ? round( ( time() - $last_check ) / 3600, 1 ) : 'never';

            // Skip if checked within the last 24 hours
            if ( $last_check && ( time() - $last_check ) < DAY_IN_SECONDS ) {
                error_log( sprintf( '[Guardian] Upload protection: Skipping check (last checked %s hours ago)', $hours_since ) );

                return;
            }

            error_log( sprintf( '[Guardian] Upload protection: Running check (last checked: %s)', $last_check ? $hours_since
                                                                                                                . ' hours ago' : 'never' ) );

            // Check and protect uploads directory
            self::protect_uploads_directory();

            // Update last check time
            update_option( 'prof_guardian_uploads_setup', time(), false );
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
