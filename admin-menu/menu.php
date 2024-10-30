<?php
/**
 * Handles everything in menu 
 * 
 * @since 1.0.5
 */

namespace Blaze_Admin_Menu;

if( ! class_exists( 'Admin_Menu' ) ) :
    class Admin_Menu {
        /**
         * Holds current theme text domain
         * 
         * @since 1.0.5
         */
        public $theme_textdomain;
        
        /**
         * Is premium theme or not
         * 
         * @since 1.0.5
         */
        public $is_premium;

        /**
         * Holds parent menu slug
         * 
         * @since 1.0.5
         */
        public $parent_menu_slug;

        /**
         * Function that gets called when class is instantiated
         * 
         * @since 1.0.5
         */
        public function __construct() {
            $this->theme_textdomain = wp_get_theme()->get( 'TextDomain' );
            $this->is_premium = preg_match( '/-pro/', $this->theme_textdomain );
            if( $this->is_premium ) :
                $this->parent_menu_slug = str_replace( '-pro', '-info', $this->theme_textdomain );
            else:
                $this->parent_menu_slug = $this->theme_textdomain . '-info';
            endif;
            add_action( 'admin_menu', [ $this, 'register_menus' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts' ] );
        }

        /**
         * Register styles and scripts
         * 
         * @since 1.0.5
         */
        public function register_scripts( $hook ) {
            if( $hook == 'blogistic-info_page_blaze-system-info' ) :
                wp_enqueue_style( 'blaze-admin-menu', BLAZE_DEMO_IMPORTER_URL . 'admin-menu/menu.css', [], BLAZE_DEMO_IMPORTER_VERSION );
                wp_enqueue_script( 'blaze-admin-menu', BLAZE_DEMO_IMPORTER_URL . 'admin-menu/menu.js', [ 'jquery' ], BLAZE_DEMO_IMPORTER_VERSION, true );
                wp_localize_script( 'blaze-admin-menu', 'BlazeMenuObject', [
                    'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                    '_wpnonce'  => wp_create_nonce( 'blogistic-security-nonce' ),
                    'systemInfo' =>  json_encode( $this->get_system_info_data() )
                ]);
            endif;
        }

        /**
         * Register menus and sub menus
         * 
         * @since 1.0.5
         */
        public function register_menus() {
            add_submenu_page(
                $this->parent_menu_slug,  // parent slug
                esc_html__( 'System Info', 'blaze-demo-importer' ), // page title
                esc_html__( 'System Info', 'blaze-demo-importer' ), // menu title
                'manage_capabilities',
                'blaze-system-info',
                [ $this, 'system_info_callback' ]
            );
        }

        /**
         * Callback function for system info sub menu page
         * 
         * @since 1.0.5
         */
        public function system_info_callback() {
            ?>
                <div class="blaze-system-info">
                    <h2 class="page-title"><?php echo esc_html__( "System Status", "blaze-demo-importer" ); ?></h2>
                    <div class="page-content">
                        <button class="button-action copy-all button button-secondary"><?php echo esc_html__( 'Copy All', 'blaze-demo-importer' ); ?></button>
                        <a href="#" class="button-action download button button-primary"><?php echo esc_html__( 'Download', 'blaze-demo-importer' ); ?></a>
                        <div class="item-container-wrap">
                            <?php $this->system_info_html_part(); ?>
                        </div>
                    </div>
                </div>
            <?php
        }

        /**
         * Get system information html
         * 
         * @since 1.0.5
         */
        public function system_info_html_part() {
            $system_data = $this->get_system_info_data();
            if( ! empty( $system_data ) && is_array( $system_data ) ) :
                foreach( $system_data as $data ) :
                    ?>
                        <div class="item-container">
                            <div class="item-head"><?php echo esc_html( $data['label'] ); ?></div>
                            <div class="item-body">
                                <?php
                                    if( ! empty( $data['readings'] ) && is_array( $data['readings'] ) ) :
                                        foreach( $data['readings'] as $reading ) :
                                            ?>
                                                <div class="item-row">
                                                    <div class="item-column row-heading"><?php echo esc_html( $reading['label'] ); ?></div>
                                                    <div class="item-column row-value"><?php echo esc_html( $reading['value'] ); ?></div>
                                                    <?php
                                                        if( array_key_exists( 'remark', $reading ) ) echo '<div class="item-column row-remarks">'. esc_html__( 'Required: ', 'blaze-demo-importer' )  . esc_html( $reading['remark'] ) .'</div>';
                                                    ?>
                                                </div>
                                            <?php
                                        endforeach;
                                    endif;
                                ?>
                            </div>
                        </div>
                    <?php
                endforeach;
            endif;
        }

        /**
         * get system information data
         * 
         * @since 1.0.5
         */
        public function get_system_info_data() {
            global $wpdb, $wp_rewrite;
            $curl_data = curl_version();
            $gd_data = gd_info();
            $active_plugins = $this->get_plugins_list( 'active-plugins' );
            $inactive_plugins = $this->get_plugins_list( 'inactive-plugins' );
            $system_info = [
                'system-info'   =>  [
                    'label' =>  esc_html__( 'System Info', 'blaze-demo-importer' ),
                    'readings'  =>  [
                        'operating-system'  => $this->set_reading_value( esc_html__( 'Operating System', 'blaze-demo-importer' ), PHP_OS ),
                        'server'  =>  $this->set_reading_value( esc_html__( 'Server', 'blaze-demo-importer' ), wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ),
                        'mysql-server'  =>  $this->set_reading_value( esc_html__( 'MySQL Version', 'blaze-demo-importer' ), $wpdb->get_var( 'SELECT VERSION()' ) ),
                        'php-version'  =>  $this->set_reading_value( esc_html__( 'PHP Version', 'blaze-demo-importer' ), PHP_VERSION, wp_get_theme()->get( 'RequiresPHP' ) ),
                        'php-max-execution-time'  =>  $this->set_reading_value( esc_html__( 'PHP Max Execution Time', 'blaze-demo-importer' ), ini_get( 'max_execution_time' ), esc_html__( '> 30', 'blaze-demo-importer' ) ),
                        'php-max-upload-size'  =>  $this->set_reading_value( esc_html__( 'PHP Max Upload Size', 'blaze-demo-importer' ), ini_get( 'upload_max_filesize' ), esc_html__( 'at least 1M', 'blaze-demo-importer' ) ),
                        'php-post-max-size'  =>  $this->set_reading_value( esc_html__( 'PHP Post Max Size', 'blaze-demo-importer' ), ini_get( 'post_max_size' ) ),
                        'php-max-input-vars'  =>  $this->set_reading_value( esc_html__( 'PHP Max Input Vars', 'blaze-demo-importer' ), ini_get( 'max_input_vars' ) ),
                        'php-memory-limit'  =>  $this->set_reading_value( esc_html__( 'PHP Memory Limit', 'blaze-demo-importer' ), ini_get( 'memory_limit' ) ),
                        'curl-installed'  =>  $this->set_reading_value( esc_html__( 'cURL Installed', 'blaze-demo-importer' ), $this->get_yes_no_value( extension_loaded( 'curl' ) ) ),
                        'curl-version'  =>  $this->set_reading_value( esc_html__( 'cURL Version', 'blaze-demo-importer' ), $curl_data['version'] ),
                        'gd-installed'  =>  $this->set_reading_value( esc_html__( 'GD Installed', 'blaze-demo-importer' ), $this->get_yes_no_value( extension_loaded( 'gd' ) ) ),
                        'gd-version'  =>  $this->set_reading_value( esc_html__( 'GD Version', 'blaze-demo-importer' ), $gd_data['GD Version'] ),
                        'write-permission'  =>  $this->set_reading_value( esc_html__( 'Write Permission', 'blaze-demo-importer' ), wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ), // yet
                        'demo-pack-server-connection'  =>  $this->set_reading_value( esc_html__( 'Demo Pack Server Connection', 'blaze-demo-importer' ), wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ),   //yet
                    ]
                ],
                'wordpress-info'   =>  [
                    'label' =>  esc_html__( 'Wordpress Info', 'blaze-demo-importer' ),
                    'readings'  =>  [
                        'version'  => $this->set_reading_value( esc_html__( 'Version', 'blaze-demo-importer' ), get_bloginfo( 'version' ) ),
                        'site-url'  =>  $this->set_reading_value( esc_html__( 'Site URL', 'blaze-demo-importer' ), get_site_url() ),
                        'home-url'  =>  $this->set_reading_value( esc_html__( 'Home URL', 'blaze-demo-importer' ), get_home_url() ),
                        'multisite'  =>  $this->set_reading_value( esc_html__( 'Multisite', 'blaze-demo-importer' ), $this->get_yes_no_value( is_multisite() ) ),
                        'max-upload-size'  =>  $this->set_reading_value( esc_html__( 'Max Upload Size', 'blaze-demo-importer' ), size_format( wp_max_upload_size() ), esc_html__( 'at least 1M', 'blaze-demo-importer' ) ),
                        'memory-limit'  =>  $this->set_reading_value( esc_html__( 'Memory Limit', 'blaze-demo-importer' ), WP_MEMORY_LIMIT ),
                        'max-memory-limit'  =>  $this->set_reading_value( esc_html__( 'Max Memory Limit', 'blaze-demo-importer' ), WP_MAX_MEMORY_LIMIT ),
                        'permalink-structure'  =>  $this->set_reading_value( esc_html__( 'Permalink Structure', 'blaze-demo-importer' ), get_option('permalink_structure') ),   // yet
                        'language'  =>  $this->set_reading_value( esc_html__( 'Language', 'blaze-demo-importer' ), get_bloginfo( 'language' ) ),
                        'debug-mode-enabled'  =>  $this->set_reading_value( esc_html__( 'Debug Mode Enabled', 'blaze-demo-importer' ), $this->get_yes_no_value( WP_DEBUG ) ),
                        'script-debug-mode-enabled'  =>  $this->set_reading_value( esc_html__( 'Script Debug Mode Enabled', 'blaze-demo-importer' ), $this->get_yes_no_value( SCRIPT_DEBUG ) ),
                        'blaze-demo-importer-version'  =>  $this->set_reading_value( esc_html__( 'Blaze Demo Importer Version', 'blaze-demo-importer' ), BLAZE_DEMO_IMPORTER_VERSION )
                    ]
                ],
                'theme-info'   =>  [
                    'label' =>  esc_html__( 'Theme Info', 'blaze-demo-importer' ),
                    'readings'  =>  [
                        'name'  => $this->set_reading_value( esc_html__( 'Name', 'blaze-demo-importer' ), wp_get_theme()->get( 'Name' ) ),
                        'version'  =>  $this->set_reading_value( esc_html__( 'Version', 'blaze-demo-importer' ), wp_get_theme()->get( 'Version' ) ),
                        'author'  =>  $this->set_reading_value( esc_html__( 'Author', 'blaze-demo-importer' ), wp_get_theme()->get( 'Author' ) ),
                        'author-url'  =>  $this->set_reading_value( esc_html__( 'Author URL', 'blaze-demo-importer' ), wp_get_theme()->get( 'AuthorURI' ) ),
                        'child-theme'  =>  $this->set_reading_value( esc_html__( 'Child Theme', 'blaze-demo-importer' ), $this->get_yes_no_value( is_child_theme() ) )
                    ]
                ],
                'active-plugins'   =>  [
                    'label' =>  esc_html__( 'Active Plugins', 'blaze-demo-importer' ),
                    'readings'  =>  $this->filter_plugins( $active_plugins )
                ],
                'inactive-plugins'   =>  [
                    'label' =>  esc_html__( 'Inactive Plugins', 'blaze-demo-importer' ),
                    'readings'  =>  $this->filter_plugins( $inactive_plugins )
                ]
            ];
            return $system_info;
        }

        /**
         * set readings value
         * 
         * @since 1.0.5
         */
        public function set_reading_value( $label, $value, $remark = '' ) {
            $readings_array = [
                'label' =>  esc_html( $label ),
                'value' =>  esc_html( $value )
            ];
            if( $remark ) $readings_array['remark'] = esc_html( $remark );
            return $readings_array;
        }

        /**
         * Echo yer or no
         * 
         * @since 1.0.5
         */
        public function get_yes_no_value( $condition ) {
            switch( $condition ) :
                case true:
                    return esc_html__( 'Yes', 'blaze-demo-importer' );
                    break;
                case false:
                    return esc_html__( 'No', 'blaze-demo-importer' );
                    break;
            endswitch;
        }

        /**
         * Get list of installed plugins
         * 
         * @since 1.0.5
         */
        public function get_plugins_list( $type ) {
            $all_plugins = get_plugins();
            $active_plugins_list = get_option( 'active_plugins' );
            $active_plugins_array = [];
            $inactive_plugins_array = [];
            if( ! empty( $active_plugins_list ) && is_array( $active_plugins_list ) ) :
                foreach( $all_plugins as $active_key => $active_value ) :
                    if( in_array( $active_key, $active_plugins_list ) ) :
                        $active_plugins_array[ $active_key ] = $active_value;
                    else:
                        $inactive_plugins_array[ $active_key ] = $active_value;
                    endif;
                endforeach;
            endif;

            switch( $type ) :
                case 'active-plugins' :
                    return $active_plugins_array;
                    break;
                case 'inactive-plugins' :
                    return $inactive_plugins_array;
                    break;
            endswitch;
        }

        /**
         * Filter the plugins array
         * 
         * @since 1.0.5
         */
        public function filter_plugins( $plugins ) {
            if( ! empty( $plugins ) && is_array( $plugins ) ) :
                $filtered_plugins = [];
                foreach( $plugins as $plugin ) :
                    $label = esc_html( $plugin['Name'] . ' - ' . $plugin['Version'] );
                    $value = esc_html( 'By ' . $plugin['Author'] );
                    $filtered_plugins[ $plugin['TextDomain'] ] = $this->set_reading_value( $label, $value );
                endforeach;
                return $filtered_plugins;
            endif;
        }
    }
    new Admin_Menu();
endif;