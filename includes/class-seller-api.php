<?php

namespace Premiumseller;

use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if (!function_exists('wc_api_hash')) {
    function wc_api_hash($data) {
        return hash_hmac('sha256', $data, 'wc-api');
    }
}

class sellerAPI {

    public static function init() {
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'update_seller_credit' ] );
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        register_rest_route(
            'premium-seller/v1',
            '/packages',
            [
                'methods'  => 'GET',
                'callback' => [ __CLASS__, 'get_packages' ],
                'permission_callback' => [ __CLASS__, 'check_api_key_permissions' ],
            ]
        );

        register_rest_route(
            'premium-seller/v1',
            '/order',
            [
                'methods'  => 'POST',
                'callback' => [ __CLASS__, 'create_order' ],
                'permission_callback' => [ __CLASS__, 'check_api_key_permissions' ],
            ]
        );

        register_rest_route( 'premium-seller/v1', '/seller-credit', [
            'methods' => 'GET',
            'callback' => [ __CLASS__, 'get_seller_credit' ],
            'permission_callback' => [ __CLASS__, 'check_api_key_permissions' ],
        ]);
    }

    public static function check_api_key_permissions( $request ) {
        $auth_header = $request->get_header('Authorization');

        if (!$auth_header || strpos($auth_header, 'Basic ') !== 0) {
            return false;
        }

        $base64_credentials = str_replace('Basic ', '', $auth_header);
        
        $credentials = base64_decode($base64_credentials);
        
        if (!$credentials || strpos($credentials, ':') === false) {
            return false;
        }

        list($consumer_key, $consumer_secret) = explode(':', $credentials);

        if (!class_exists('WooCommerce')) {
            return false;
        }

        global $wpdb;
        
        // Get user data from API key
        $user_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT key_id, user_id, permissions
                FROM {$wpdb->prefix}woocommerce_api_keys
                WHERE consumer_key = %s",
                wc_api_hash($consumer_key)
            )
        );

        if (empty($user_data)) {
            return false;
        }

        if (!in_array($user_data->permissions, ['read', 'write', 'read_write'], true)) {
            return false;
        }

        $api_key = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT consumer_secret
                FROM {$wpdb->prefix}woocommerce_api_keys
                WHERE consumer_key = %s",
                wc_api_hash($consumer_key)
            )
        );

        if (empty($api_key) || !hash_equals($api_key, $consumer_secret)) {
            return false;
        }

        return true;
    }

    public static function get_seller_credit( $request ) {

        if ( empty($request['seller_id'] ) || ! is_numeric($request['seller_id'])) {
            return new WP_REST_Response( 'Invalid seller ID', 401 ); 
        }

        global $wpdb;
        $table_credit = $wpdb->prefix . 'seller_credit';
        $seller_id = $request['seller_id'];
        
        // Check and expire free credits if needed
        $free_credits = get_user_meta($seller_id, 'free_credits', true);
        
        if (is_array($free_credits)) {
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
                update_user_meta($seller_id, 'free_credits', $updated_credits);
                
                // Decrease the expired credit from total credit
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE $table_credit SET credit = GREATEST(credit - %d, 0) WHERE user_id = %d",
                        $expired_credit_total,
                        $seller_id
                    )
                );
            }
        }
        
        $credit = $wpdb->get_var(
            $wpdb->prepare( "SELECT credit FROM $table_credit WHERE user_id = %d", $seller_id )
        );
       
        if ( $credit === null ) {
            $credit = 0;
        }

        // Get active free credits info
        $active_free_credits = array();
        if (is_array($free_credits)) {
            foreach ($free_credits as $credit_info) {
                if (strtotime($current_time) <= strtotime($credit_info['expiry_date'])) {
                    $active_free_credits[] = array(
                        'amount' => $credit_info['amount'],
                        'expiry_date' => $credit_info['expiry_date'],
                        'package_id' => $credit_info['package_id'],
                        'purchase_date' => $credit_info['purchase_date']
                    );
                }
            }
        }

        return new WP_REST_Response( [
            'success' => true,
            'seller_id' => intval($seller_id),
            'credit' => floatval($credit),
            'total_free_credits' => count($active_free_credits),
            'active_free_credits' => $active_free_credits,
            'total_credits' => floatval($credit) + count($active_free_credits)
        ], 200 );
    }

    public static function get_user_from_api_key( $request ) {

        $auth_header = $request->get_header( 'Authorization' );
        if ( ! $auth_header || strpos( $auth_header, 'Basic ' ) !== 0 ) {
            return false;
        }

        $base64_credentials = str_replace( 'Basic ', '', $auth_header );
        $decoded_credentials = base64_decode( $base64_credentials );

        list( $consumer_key, $consumer_secret ) = explode( ":", $decoded_credentials );

        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s AND status = 'active'",
            $consumer_key
        );

        $user_id = $wpdb->get_var( $query );

        if ( ! $user_id ) {
            return false;
        }

        return get_user_by( 'id', $user_id );
    }


    public static function get_packages( $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seller_package';

        $packages = $wpdb->get_results( "SELECT id, name, description, price, credit FROM $table_name", ARRAY_A );

        if ( empty( $packages ) ) {
            return rest_ensure_response( [
                'success' => false,
                'message' => __( 'No packages found.', 'premium-seller' ),
            ] );
        }

        // Add bonus credit information to each package
        foreach ($packages as &$package) {
            if ($package['credit'] > 0) {
                $package['bonus_credit'] = 1;
                $package['total_credit'] = $package['credit'] + $package['bonus_credit'];
            } else {
                $package['bonus_credit'] = 0;
                $package['total_credit'] = $package['credit'];
            }
        }

        return rest_ensure_response( [
            'success' => true,
            'data'    => $packages,
        ] );
    }

    public static function create_order( $request ) {
        $params = $request->get_json_params();

        if ( empty( $params['package_id'] ) || ! is_numeric( $params['package_id'] ) ) {
            return rest_ensure_response( [
                'success' => false,
                'message' => __( 'Invalid package ID.', 'premium-seller' ),
            ] );
        }

        if ( empty( $params['seller_id'] ) || ! is_numeric( $params['seller_id'] ) ) {
            return rest_ensure_response( [
                'success' => false,
                'message' => __( 'Invalid seller ID.', 'premium-seller' ),
            ] );
        }

        $seller_id = intval( $params['seller_id'] );
        $package_id = intval( $params['package_id'] );


        global $wpdb;
        $table_name = $wpdb->prefix . 'seller_package';

        $package = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $package_id ),
            ARRAY_A
        );

        if ( ! $package ) {
            return rest_ensure_response( [
                'success' => false,
                'message' => __( 'Package not found.', 'premium-seller' ),
            ] );
        }

       
        $order = \wc_create_order();
        $product_id = self::get_or_create_product( $package );

        $order->add_product( \wc_get_product( $product_id ), 1, [
            'subtotal' => $package['price'],
            'total'    => $package['price'],
        ] );

        $order->set_customer_id( $seller_id );
        $order->calculate_totals();

        
        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'Order created successfully.', 'premium-seller' ),
            'order_id' => $order->get_id(),
            'checkout_url' => $order->get_checkout_payment_url(),
        ] );
    }

    public static function update_seller_credit( $order_id ) {
        $order = \wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $user_id = $order->get_customer_id();
        if ( ! $user_id ) {
            return;
        }

        global $wpdb;
        $table_package = $wpdb->prefix . 'seller_package';
        $table_credit  = $wpdb->prefix . 'seller_credit';

        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $product    = \wc_get_product( $product_id );

            if ( ! $product ) {
                continue;
            }

           
            $package = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM $table_package WHERE name = %s", $product->get_name() ),
                ARRAY_A
            );

            if ( ! $package ) {
                continue;
            }

           
            $credit = (int) $package['credit'];

            
            $existing_credit = $wpdb->get_var(
                $wpdb->prepare( "SELECT credit FROM $table_credit WHERE user_id = %d", $user_id )
            );

            if ( $existing_credit !== null ) {
                
                $wpdb->update(
                    $table_credit,
                    [ 'credit' => $existing_credit + $credit ],
                    [ 'user_id' => $user_id ],
                    [ '%d' ],
                    [ '%d' ]
                );
            } else {
                
                $wpdb->insert(
                    $table_credit,
                    [ 'user_id' => $user_id, 'credit' => $credit ],
                    [ '%d', '%d' ]
                );
            }

            $expiry_days = get_option('premium_seller_free_credit_expiry_days', 7);
            $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
            
            // Store the expiry information in user meta when credits are purchased
            if ( $user_id ) {
                $free_credits = get_user_meta( $user_id, 'free_credits', true );

                if ( ! is_array( $free_credits ) ) {
                    $free_credits = array();
                }

                // Add new free credit entry
                $free_credits[] = array(
                    'amount' => 1,
                    'expiry_date' => $expiry_date,
                    'package_id' => $package['id'],
                    'purchase_date' => current_time( 'mysql' )
                );
                
                update_user_meta($user_id, 'free_credits', $free_credits);
            }
        }
    }

    private static function get_or_create_product( $package ) {
        $product_name = $package['name'];
        $existing_product = get_page_by_title( $product_name, OBJECT, 'product' );

        if ( $existing_product ) {
            return $existing_product->ID;
        }

        // Buat produk baru
        $product = new \WC_Product_Simple();
        $product->set_name( $product_name );
        $product->set_regular_price( $package['price'] );
        $product->set_catalog_visibility( 'hidden' );
        $product->save();

        return $product->get_id();
    }
}
