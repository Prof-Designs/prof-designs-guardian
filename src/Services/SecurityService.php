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
         *
         * @return array
         */
        public function removeThemeActionLinks( array $actions ): array {
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
            if ( ! in_array( 'administrator', $user->roles, true ) ) {
                return $allcaps;
            }

            // Grant Site Health capabilities
            $allcaps['view_site_health_checks'] = true;
            $allcaps['install_plugins']         = true;

            return $allcaps;
        }

        /**
         * Block suspicious file uploads
         *
         * @param array $file Upload file data
         *
         * @return array
         */
        public function blockSuspiciousUploads( array $file ): array {
            $filename            = $file['name'] ?? '';
            $suspicious_patterns = [
                '/\.php\d*$/i',           // .php, .php5, .php7, etc.
                '/\.phtml$/i',            // .phtml
                '/\.suspected$/i',        // .suspected
                '/\.susp$/i',             // .susp
                '/malware/i',             // malware keyword
                '/c99shell/i',            // c99shell keyword
                '/r57shell/i',            // r57shell keyword
            ];

            foreach ( $suspicious_patterns as $pattern ) {
                if ( preg_match( $pattern, $filename ) ) {
                    $file['error'] = sprintf( __( 'File upload blocked: "%s" matches suspicious pattern.', 'prof-designs-guardian' ), esc_html( $filename ) );
                    prof_guardian_log( "[Guardian] Blocked suspicious upload: {$filename}" );
                    break;
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
            $upload_dir    = wp_upload_dir();
            $htaccess_file = trailingslashit( $upload_dir['basedir'] ) . '.htaccess';

            if ( file_exists( $htaccess_file ) ) {
                return;
            }

            $htaccess_content = '# Protect uploads directory
<Files *.php>
    deny from all
</Files>
<Files *.phtml>
    deny from all
</Files>
<Files *.suspected>
    deny from all
</Files>';

            $result = file_put_contents( $htaccess_file, $htaccess_content );

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
            return defined( 'PROFDESIGNS_GUARDIAN_LOCK_MODS' ) && PROFDESIGNS_GUARDIAN_LOCK_MODS;
        }
    }
