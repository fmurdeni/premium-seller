<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap premium-seller-wrap">
    <h1><?php esc_html_e( 'Seller Packages', 'premium-seller' ); ?></h1>

    <div class="premium-seller-form">
        <form method="POST">
            <h2><?php echo $edit_package ? esc_html__( 'Edit Package', 'premium-seller' ) : esc_html__( 'Add Package', 'premium-seller' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="package_name"><?php esc_html_e( 'Package Name', 'premium-seller' ); ?></label></th>
                    <td><input type="text" name="package_name" id="package_name" value="<?php echo $edit_package ? esc_attr( $edit_package['name'] ) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="package_description"><?php esc_html_e( 'Description', 'premium-seller' ); ?></label></th>
                    <td>
                        <?php
                        wp_editor(
                            $edit_package ? wp_kses_post( $edit_package['description'] ) : '',
                            'package_description',
                            array(
                                'textarea_name' => 'package_description',
                                'media_buttons' => false,
                                'textarea_rows' => 5,
                                'teeny' => true,
                                'quicktags' => true
                            )
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="package_price"><?php esc_html_e( 'Price', 'premium-seller' ); ?></label></th>
                    <td><input type="number" step="0.01" name="package_price" id="package_price" value="<?php echo $edit_package ? esc_attr( $edit_package['price'] ) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="package_credit"><?php esc_html_e( 'Credits', 'premium-seller' ); ?></label></th>
                    <td><input type="number" name="package_credit" id="package_credit" value="<?php echo $edit_package ? esc_attr( $edit_package['credit'] ) : ''; ?>" required></td>
                </tr>
            </table>
            <?php if ( $edit_package ) : ?>
                <input type="hidden" name="package_id" value="<?php echo esc_attr( $edit_package['id'] ); ?>">
            <?php endif; ?>
            <input type="hidden" name="seller_package_action" value="save">
            <?php if ( $edit_package ) : ?>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Update Package', 'premium-seller' ); ?></button>
            <?php else : ?>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Package', 'premium-seller' ); ?></button>
            <?php endif; ?>
        </form>
    </div>

    <h2><?php esc_html_e( 'Existing Packages', 'premium-seller' ); ?></h2>
    <table class="packages-table widefat">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'premium-seller' ); ?></th>
                <th><?php esc_html_e( 'Name', 'premium-seller' ); ?></th>                
                <th><?php esc_html_e( 'Price', 'premium-seller' ); ?></th>
                <th><?php esc_html_e( 'Credits', 'premium-seller' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'premium-seller' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $packages ) ) : ?>
                <?php foreach ( $packages as $package ) : ?>
                    <tr>
                        <td><?php echo esc_html( $package['id'] ); ?></td>
                        <td><?php echo esc_html( $package['name'] ); ?></td>                        
                        <td><?php echo esc_html( number_format( $package['price'], 2 ) ); ?></td>
                        <td><?php 
                            echo esc_html( $package['credit'] );
                            if ($package['credit'] > 0) {
                                echo ' (+1 bonus)';
                            }
                        ?></td>
                        <td class="action-buttons">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=seller-packages&edit=' . $package['id'] ) ); ?>" class="button-edit"><?php esc_html_e( 'Edit', 'premium-seller' ); ?></a>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=seller-packages&delete=' . $package['id'] ) ); ?>" class="button-delete" onclick="return confirm('<?php esc_html_e( 'Are you sure you want to delete this package?', 'premium-seller' ); ?>');"><?php esc_html_e( 'Delete', 'premium-seller' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6" class="empty-message"><?php esc_html_e( 'No packages found.', 'premium-seller' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
