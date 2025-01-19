<?php
/**
 * Plugin Name: Premium seller for WooCommerce
 * Description: Plugin untuk menambahkan fitur premium seller di WooCommerce.
 * Version: 1.0.3
 * Author: Feri Murdeni
 * Author URI: https://murdeni.com
 * Text Domain: premium-seller
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PREMIUM_seller_VERSION', '1.0.3' );
define( 'PREMIUM_seller_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PREMIUM_seller_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function premium_seller_check_woocommerce_active() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__( 'Premium seller requires WooCommerce to be installed and active.', 'premium-seller' ) . '</p></div>';
        } );
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}
add_action( 'admin_init', 'premium_seller_check_woocommerce_active' );

function premium_seller_admin_styles() {
    wp_enqueue_style(
        'premium-seller-admin',
        PREMIUM_seller_PLUGIN_URL . 'assets/css/admin-styles.css',
        array(),
        PREMIUM_seller_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'premium_seller_admin_styles' );

function premium_seller_init() {
    require_once PREMIUM_seller_PLUGIN_DIR . 'includes/class-seller-package.php';
    require_once PREMIUM_seller_PLUGIN_DIR . 'includes/class-seller-api.php';
    require_once PREMIUM_seller_PLUGIN_DIR . 'includes/class-seller-product.php';

    \Premiumseller\sellerPackage::init();
    \Premiumseller\sellerAPI::init();
    \Premiumseller\sellerProduct::init();
}
add_action( 'plugins_loaded', 'premium_seller_init' );

function premium_seller_activate() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'seller_package';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description LONGTEXT,
        price DECIMAL(10, 2) NOT NULL,
        credit INT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    $table_name = $wpdb->prefix . 'seller_credit';
    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        user_id BIGINT NOT NULL,
        credit INT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql );
}

register_activation_hook( __FILE__, 'premium_seller_activate' );

function premium_seller_uninstall() {
    global $wpdb;

    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}seller_package" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}seller_credit" );
    remove_role( 'seller' );
}
register_uninstall_hook( __FILE__, 'premium_seller_uninstall' );

function premium_seller_load_textdomain() {
    load_plugin_textdomain( 'premium-seller', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'premium_seller_load_textdomain' );
