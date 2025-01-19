<?php

namespace Premiumseller;

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

class sellerPackage {

    private static $table_name;
    private static $default_credit_expiry_days = 7; // Default expiry time for free credit

    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'seller_package';

        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'init', [ __CLASS__, 'check_and_expire_credits' ] );
    }

    public static function register_settings() {
        register_setting( 'premium_seller_settings', 'premium_seller_free_credit_expiry_days' );
        register_setting( 'premium_seller_settings', 'premium_seller_enable_credit_for_product' );
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( $hook === 'toplevel_page_seller-packages' ) {
            wp_enqueue_editor();
            wp_enqueue_style( 'premium-seller-admin-styles', PREMIUM_seller_PLUGIN_URL . 'assets/css/admin-styles.css', [], PREMIUM_seller_VERSION );
            wp_enqueue_script( 'premium-seller-admin-scripts', PREMIUM_seller_PLUGIN_URL . 'assets/js/admin-scripts.js', [ 'jquery' ], PREMIUM_seller_VERSION, true );
        }
    }

    public static function add_admin_menu() {
        add_menu_page(
            __( 'Seller Packages', 'premium-seller' ),
            __( 'Seller Packages', 'premium-seller' ),
            'manage_options',
            'seller-packages',
            [ __CLASS__, 'render_admin_page' ],
            'dashicons-cart',
            30
        );

        add_submenu_page(
            'seller-packages',
            __( 'Settings', 'premium-seller' ),
            __( 'Settings', 'premium-seller' ),
            'manage_options',
            'seller-packages&tab=settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'packages';
        ?>
        <div class="wrap">
            <h2 class="nav-tab-wrapper">
                <a href="?page=seller-packages&tab=packages" class="nav-tab <?php echo $active_tab == 'packages' ? 'nav-tab-active' : ''; ?>"><?php _e('Packages', 'premium-seller'); ?></a>
                <a href="?page=seller-packages&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Settings', 'premium-seller'); ?></a>
            </h2>

            <?php
            if ($active_tab == 'settings') {
                self::render_settings_page();
            } else {
                $edit_package = null;

                if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
                    $edit_package = self::get_package_by_id(intval($_GET['edit']));
                }

                if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
                    self::delete_package(intval($_GET['delete']));
                    wp_redirect(admin_url('admin.php?page=seller-packages'));
                    exit;
                }

                if (isset($_POST['seller_package_action'])) {
                    self::handle_form_submission();
                }

                $packages = self::get_all_packages();
                include PREMIUM_seller_PLUGIN_DIR . 'templates/admin/list-packages.php';
            }
            ?>
        </div>
        <?php
    }

    public static function render_settings_page() {
        $free_credit_expiry_days = get_option('premium_seller_free_credit_expiry_days', self::$default_credit_expiry_days);
        ?>
        <div class="wrap">
            <form method="post" action="options.php">
                <?php settings_fields('premium_seller_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable', 'premium-seller'); ?></th>
                        <td>
                            <label for="premium_seller_enable_credit_for_product">
                                <input type="checkbox" name="premium_seller_enable_credit_for_product" id="premium_seller_enable_credit_for_product" value="1" <?php checked(get_option('premium_seller_enable_credit_for_product', 0), 1); ?> />
                                <?php _e('Enable', 'premium-seller'); ?>
                            </label>
                            <p class="description"><?php _e('If enable, the seller must have a credit to publish the product.', 'premium-seller'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Free Credit', 'premium-seller'); ?></th>
                        <td>
                            <p><?php echo __('We offer free 1 credit to our sellers after purchase any package. You can set the number of days before the free credit expires. So that the seller have to use the credit before the expiry date.', 'premium-seller') ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Free Credit Expiry Days', 'premium-seller'); ?></th>
                        <td>
                            <input type="number" name="premium_seller_free_credit_expiry_days" value="<?php echo esc_attr($free_credit_expiry_days); ?>" min="1" />
                            <p class="description"><?php _e('Number of days before the free credit expires. Default is 7 days.', 'premium-seller'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function handle_form_submission() {
        global $wpdb;

        $id = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
        $name = sanitize_text_field($_POST['package_name']);
        $description = wp_kses_post($_POST['package_description']);
        $price = floatval($_POST['package_price']);
        $credit = intval($_POST['package_credit']);

        if ($id > 0) {
            $wpdb->update(
                self::$table_name,
                [
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'credit' => $credit
                ],
                ['id' => $id]
            );
        } else {
            $wpdb->insert(
                self::$table_name,
                [
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'credit' => $credit
                ]
            );
        }

        wp_redirect(admin_url('admin.php?page=seller-packages'));
        exit;
    }

    public static function get_all_packages() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM " . self::$table_name, ARRAY_A );
    }

    public static function get_package_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::$table_name . " WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public static function delete_package( $id ) {
        global $wpdb;
        $wpdb->delete( self::$table_name, [ 'id' => $id ] );
    }

    public static function check_and_expire_credits() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $free_credits = get_user_meta($user_id, 'free_credits', true);
        
        if (!is_array($free_credits)) {
            return;
        }

        $current_time = current_time('mysql');
        $expired_credit_total = 0;
        $updated_credits = array();

        foreach ($free_credits as $credit) {
            if (strtotime($current_time) > strtotime($credit['expiry_date'])) {
                // Add to expired total
                $expired_credit_total += $credit['amount'];
            } else {
                // Keep unexpired credits
                $updated_credits[] = $credit;
            }
        }

        if ($expired_credit_total > 0) {
            // Update the free credits array to remove expired ones
            update_user_meta($user_id, 'free_credits', $updated_credits);
            
            // Decrease the total credits in the database
            global $wpdb;
            $table_credit = $wpdb->prefix . 'seller_credit';
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table_credit SET credit = GREATEST(credit - %d, 0) WHERE user_id = %d",
                    $expired_credit_total,
                    $user_id
                )
            );
        }
    }
}
