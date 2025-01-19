<?php

namespace Premiumseller;

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

class sellerProduct {

    public static function init() {
        add_action( 'save_post_product', [ __CLASS__, 'validate_and_deduct_credit' ], 10, 3 );
    }

    
    public static function validate_and_deduct_credit( $post_id, $post, $update ) {

        // check if enabled
        if ( ! get_option( 'premium_seller_enable_credit_for_product', false ) ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || 'publish' !== $post->post_status ) {
            return;
        }

        $user_id = get_current_user_id();
        $roles = wp_get_current_user()->roles;
        if ( in_array( 'administrator', $roles ) ) {
            return;
        }

        if ( ! $user_id ) {
            return;
        }

        global $wpdb;
        $table_credit = $wpdb->prefix . 'seller_credit';

        
        $credit = $wpdb->get_var(
            $wpdb->prepare( "SELECT credit FROM $table_credit WHERE user_id = %d", $user_id )
        );

        if ( $credit === null || $credit <= 0 ) {
           
            wp_update_post( [
                'ID'          => $post_id,
                'post_status' => 'pending',
            ] );

            
            add_action( 'admin_notices', function() {
                echo '<div class="error"><p>' . __( 'You do not have enough credits to publish this product.', 'premium-seller' ) . '</p></div>';
            } );

            return;
        }

        
        $wpdb->update(
            $table_credit,
            [ 'credit' => $credit - 1 ],
            [ 'user_id' => $user_id ],
            [ '%d' ],
            [ '%d' ]
        );
    }
}
