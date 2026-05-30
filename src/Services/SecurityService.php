<?php
    /**
     * Security Service
     *
     * @package ProfDesigns\Guardian\Services
     */

    declare( strict_types=1 );

    namespace ProfDesigns\Guardian\Services;

    use ProfDesigns\Guardian\Application;

    /**
     * Class SecurityService
     *
     * Provides security hardening by disabling file editors while still allowing
     * automatic updates. This is a safer alternative to DISALLOW_FILE_MODS which
     * blocks both editing AND updates.
     *
     * @package ProfDesigns\Guardian\Services
     * @since   0.10.0
     */
    class SecurityService {
        /**
         * Capabilities denied while lock mode is active.
         */
        protected const LOCK_BLOCKED_CAPS = [
            // Intentionally keep activate_plugins allowed so admins can open Plugins list
            // and deactivate problematic plugins while install/update/delete remain locked.
            'install_plugins',
            'upload_plugins',
            'update_plugins',
            'delete_plugins',
            'install_themes',
            'upload_themes',
            'update_themes',
            'delete_themes',
            'update_core',
        ];

        /**
         * The application instance
         *
         * @var Application
         */
        protected Application $app;

        /**
         * SecurityService constructor
         *
         * @param Application $app Application instance
         */
        public function __construct( Application $app ) {
            $this->app = $app;
        }

        /**
         * Disable WordPress file editors
         *
         * @return void
         */
        public function disableFileEditors(): void {
            if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
                define( 'DISALLOW_FILE_EDIT', true );
            }
            prof_guardian_log( '[Guardian] File editors disabled' );
        }

        /**
         * Remove admin menu items for file editors
         *
         * @return void
         */
        public function removeEditorMenus(): void {
            remove_submenu_page( 'themes.php', 'theme-editor.php' );
            remove_submenu_page( 'plugins.php', 'plugin-editor.php' );

            if ( $this->isLockModsEnabled() ) {
                remove_submenu_page( 'index.php', 'update-core.php' );
                remove_submenu_page( 'plugins.php', 'plugin-install.php' );
                remove_submenu_page( 'themes.php', 'theme-install.php' );
            }
        }

        /**
         * Remove plugin installation capabilities from editor role
         *
         * @return void
         */
        public function removeEditorCapabilities(): void {
            $editor = get_role( 'editor' );
            if ( $editor ) {
                $editor->remove_cap( 'install_plugins' );
                $editor->remove_cap( 'activate_plugins' );
                $editor->remove_cap( 'update_plugins' );
                $editor->remove_cap( 'delete_plugins' );
                $editor->remove_cap( 'install_themes' );
                $editor->remove_cap( 'update_themes' );
                $editor->remove_cap( 'delete_themes' );
                prof_guardian_log( '[Guardian] Editor capabilities removed' );
            }
        }

        /**
         * Block access to update pages when modifications are locked
         *
         * @return void
         */
        public function blockUpdatePages(): void {
            if ( ! $this->isLockModsEnabled() ) {
                return;
            }

            global $pagenow;

            $blocked_pages = [
                'update-core.php',
                'plugin-install.php',
                'plugin-editor.php',
                'theme-install.php',
                'theme-editor.php',
                'update.php',
            ];

            if ( in_array( $pagenow, $blocked_pages, true ) ) {
                wp_die( esc_html__( 'Plugin and theme modifications are currently locked.', 'prof-designs-guardian' ), esc_html__( 'Access Denied', 'prof-designs-guardian' ), [ 'response' => 403 ] );
            }
        }

        /**
         * Hide locked UI elements when modifications are locked
         *
         * @return void
         */
        public function hideLockedUiElements(): void {
            if ( ! $this->isLockModsEnabled() ) {
                return;
            }

            echo '<style>
            .plugin-card-top .install-now,
            .theme-actions .button.activate,
            .plugin-action-buttons .button,
            .theme-browser .theme .theme-actions,
            #plugin_update_from_iframe,
            .update-link { display: none !important; }
        </style>';
        }

        /**
         * Remove plugin action links when modifications are locked
         *
         * @param array  $actions     Plugin action links
         * @param string $plugin_file Plugin file path
         *
         * @return array
         */
        public function removePluginActionLinks( array $actions, string $plugin_file ): array {
            if ( $this->isLockModsEnabled() ) {
                unset( $actions['delete'], $actions['edit'] );
            }

            return $actions;
        }

        /**
         * Remove theme action links when modifications are locked
         *
         * @param array $actions Theme action links
         * @param mixed $theme   Theme context passed by WordPress filter
         *
         * @return array
         */
        public function removeThemeActionLinks( array $actions, $theme = null ): array {
            if ( $this->isLockModsEnabled() ) {
                unset( $actions['delete'] );
            }

            return $actions;
        }

        /**
         * Remove updates menu from admin bar
         *
         * @param \WP_Admin_Bar $wp_admin_bar WordPress admin bar instance
         *
         * @return void
         */
        public function removeAdminBarUpdates( \WP_Admin_Bar $wp_admin_bar ): void {
            if ( $this->isLockModsEnabled() ) {
                $wp_admin_bar->remove_node( 'updates' );
            }
        }

        /**
         * Grant Site Health capabilities to administrators
         *
         * @param array    $allcaps All user capabilities
         * @param array    $caps    Required capabilities
         * @param array    $args    Capability check arguments
         * @param \WP_User $user    Current user
         *
         * @return array
         */
        public function grantSiteHealthCaps( array $allcaps, array $caps, array $args, \WP_User $user ): array {
            // Only grant capabilities to admins (capability-based check to support custom roles).
            if ( empty( $allcaps['manage_options'] ) ) {
                return $allcaps;
            }

            // Grant Site Health capabilities.
            $allcaps['view_site_health_checks'] = true;
            $allcaps['install_plugins']         = true;
            $allcaps['update_plugins']          = true;
            $allcaps['update_themes']           = true;
            $allcaps['update_core']             = true;

            return $allcaps;
        }

        /**
         * Enforce lock mode by denying modification capabilities.
         *
         * This blocks direct requests to update/install endpoints even when UI links
         * are hidden, by stripping capabilities at runtime.
         *
         * @param array    $allcaps All user capabilities
         * @param array    $caps    Required capabilities
         * @param array    $args    Capability check arguments
         * @param \WP_User $user    Current user
         *
         * @return array
         */
        public function enforceLockModCapabilities( array $allcaps, array $caps, array $args, \WP_User $user ): array {
            if ( ! $this->isLockModsEnabled() ) {
                return $allcaps;
            }

            $requested_caps = array_filter( $caps, 'is_string' );
            if ( empty( $requested_caps ) ) {
                return $allcaps;
            }

            $requires_lock_filter = false;
            foreach ( $requested_caps as $requested_cap ) {
                if ( in_array( $requested_cap, self::LOCK_BLOCKED_CAPS, true ) ) {
                    $requires_lock_filter = true;
                    break;
                }
            }

            if ( ! $requires_lock_filter ) {
                return $allcaps;
            }

            // Preserve Site Health compatibility when WordPress checks update caps.
            if ( $this->isSiteHealthContext() ) {
                return $allcaps;
            }

            foreach ( $requested_caps as $requested_cap ) {
                if ( in_array( $requested_cap, self::LOCK_BLOCKED_CAPS, true ) ) {
                    $allcaps[ $requested_cap ] = false;
                }
            }

            return $allcaps;
        }

        /**
         * Determine whether the current request is a Site Health context.
         *
         * @return bool
         */
        protected function isSiteHealthContext(): bool {
            global $pagenow;

            if ( $pagenow === 'site-health.php' ) {
                return true;
            }

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

                return in_array( $_REQUEST['action'], $health_actions, true );
            }

            return false;
        }

        /**
         * Block suspicious file uploads
         *
         * @param array $file Upload file data
         *
         * @return array
         */
        public function blockSuspiciousUploads( array $file ): array {
            $filename = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';

            if ( $filename === '' ) {
                return $file;
            }

            $normalized_name = strtolower( $filename );
            $tmp_name        = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';

            // Block .user.ini explicitly (multi-part extension is not caught by last-segment checks).
            if ( $normalized_name === '.user.ini' || $normalized_name === 'user.ini' ) {
                $file['error'] = sprintf( esc_html__( 'File upload blocked: "%s" is not allowed.', 'prof-designs-guardian' ), esc_html( $filename ) );
                prof_guardian_log( "[Guardian] Blocked suspicious upload (user.ini): {$filename}" );

                return $file;
            }

            // Deny dangerous executable/script extensions outright.
            $blocked_extensions = [
                'php',
                'php3',
                'php4',
                'php5',
                'php7',
                'php8',
                'phtml',
                'pht',
                'phar',
                'phps',
                'cgi',
                'pl',
                'py',
                'jsp',
                'asp',
                'aspx',
                'shtml',
                'htaccess',
                'user.ini',
                'suspected',
                'susp',
            ];

            $path_parts = explode( '.', $normalized_name );
            $last_ext   = count( $path_parts ) > 1 ? (string) end( $path_parts ) : '';

            if ( $last_ext !== '' && in_array( $last_ext, $blocked_extensions, true ) ) {
                $file['error'] = sprintf( esc_html__( 'File upload blocked: "%s" uses a disallowed extension.', 'prof-designs-guardian' ), esc_html( $filename ) );
                prof_guardian_log( "[Guardian] Blocked suspicious upload (extension): {$filename}" );

                return $file;
            }

            // Block dangerous double extensions like payload.php.jpg.
            if ( count( $path_parts ) > 2 ) {
                $middle_parts = array_slice( $path_parts, 0, - 1 );
                foreach ( $middle_parts as $part ) {
                    if ( in_array( (string) $part, $blocked_extensions, true ) ) {
                        $file['error'] = sprintf( esc_html__( 'File upload blocked: "%s" contains a suspicious double extension.', 'prof-designs-guardian' ), esc_html( $filename ) );
                        prof_guardian_log( "[Guardian] Blocked suspicious upload (double extension): {$filename}" );

                        return $file;
                    }
                }
            }

            // Block known web shell/malware naming patterns.
            $suspicious_patterns = [
                '/malware/i',
                '/c99shell/i',
                '/r57shell/i',
                '/shell[_-]?upload/i',
                '/webshell/i',
                '/cmd\./i',
                '/\.php\./i',
                '/\.phtml\./i',
                '/\.phar\./i',
            ];

            foreach ( $suspicious_patterns as $pattern ) {
                if ( preg_match( $pattern, $filename ) ) {
                    $file['error'] = sprintf( esc_html__( 'File upload blocked: "%s" matches a suspicious pattern.', 'prof-designs-guardian' ), esc_html( $filename ) );
                    prof_guardian_log( "[Guardian] Blocked suspicious upload (pattern): {$filename}" );

                    return $file;
                }
            }

            // Validate extension and MIME using WordPress helper when possible.
            if ( function_exists( 'wp_check_filetype_and_ext' ) && $tmp_name !== '' ) {
                $checked_type = wp_check_filetype_and_ext( $tmp_name, $filename );

                $checked_ext  = isset( $checked_type['ext'] )
                                && is_string( $checked_type['ext'] ) ? strtolower( $checked_type['ext'] ) : '';
                $checked_mime = isset( $checked_type['type'] )
                                && is_string( $checked_type['type'] ) ? strtolower( $checked_type['type'] ) : '';

                if ( $checked_ext !== '' && in_array( $checked_ext, $blocked_extensions, true ) ) {
                    $file['error'] = sprintf( esc_html__( 'File upload blocked: "%s" failed extension validation.', 'prof-designs-guardian' ), esc_html( $filename ) );
                    prof_guardian_log( "[Guardian] Blocked suspicious upload (wp_check ext): {$filename}" );

                    return $file;
                }

                $blocked_mimes = [
                    'application/x-php',
                    'application/php',
                    'text/x-php',
                    'text/php',
                    'application/x-httpd-php',
                    'application/x-phar',
                    'text/x-shellscript',
                ];

                if ( $checked_mime !== '' && in_array( $checked_mime, $blocked_mimes, true ) ) {
                    $file['error'] = sprintf( esc_html__( 'File upload blocked: "%s" failed MIME validation.', 'prof-designs-guardian' ), esc_html( $filename ) );
                    prof_guardian_log( "[Guardian] Blocked suspicious upload (wp_check mime): {$filename} ({$checked_mime})" );

                    return $file;
                }
            }

            return $file;
        }

        /**
         * Protect uploads directory with .htaccess
         *
         * @return void
         */
        public function protectUploadsDirectory(): void {
            $upload_dir = wp_upload_dir();
            $basedir    = $upload_dir['basedir'] ?? '';
            if ( ! $basedir || ! is_dir( $basedir ) ) {
                return;
            }
            $htaccess_file = trailingslashit( $basedir ) . '.htaccess';
            $index_file    = trailingslashit( $basedir ) . 'index.php';

            // Defense-in-depth: ensure index.php exists even if .htaccess is ignored (e.g. Nginx).
            if ( ! file_exists( $index_file ) ) {
                $index_result = @file_put_contents( $index_file, "<?php\n// Silence is golden.\n", LOCK_EX );
                if ( $index_result === false ) {
                    prof_guardian_log( '[Guardian] Failed to create index.php in uploads directory' );
                }
            }

            if ( file_exists( $htaccess_file ) ) {
                return;
            }

            $htaccess_content = <<<'HTACCESS'
 # Prevent PHP execution in uploads directory
 <FilesMatch "\.(?i:php\d*|phtml|pht|phar|suspected|susp)$">
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

            $result = @file_put_contents( $htaccess_file, $htaccess_content, LOCK_EX );

            if ( $result !== false ) {
                prof_guardian_log( '[Guardian] Created .htaccess in uploads directory' );
            } else {
                prof_guardian_log( '[Guardian] Failed to create .htaccess in uploads directory' );
            }
        }

        /**
         * Check if modifications lock is enabled
         *
         * @return bool
         */
        protected function isLockModsEnabled(): bool {
            return ! defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) || (bool) constant( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' );
        }
    }
