<?php
/*
  Plugin Name: WC Checkout Quantity
  Description: Adds a custom quantity field under order total that preserves quantity across refreshes, plus an admin option for the label.
  Version: 1.0
  Author: Kanaleto
  Text Domain: wc-checkout-quantity
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1) Create a submenu under "WooCommerce" for our label customization.
 */
add_action('admin_menu', 'wc_checkout_quantity_admin_menu');
function wc_checkout_quantity_admin_menu() {
    add_submenu_page(
        'woocommerce',
        'Checkout Quantity Settings',
        'Checkout Quantity',
        'manage_woocommerce',
        'wc-checkout-quantity-settings',
        'wc_checkout_quantity_settings_page'
    );
}

/**
 * 2) Register the setting so we can store our label.
 */
add_action('admin_init', 'wc_checkout_quantity_register_setting');
function wc_checkout_quantity_register_setting() {
    register_setting('wc_checkout_quantity_settings_group', 'wc_checkout_quantity_label');
}

/**
 * 3) Render the settings page.
 */
function wc_checkout_quantity_settings_page() {
    ?>
    <div class="wrap">
        <h1>Checkout Quantity Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('wc_checkout_quantity_settings_group');
                do_settings_sections('wc_checkout_quantity_settings_group');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Label for Quantity:</th>
                    <td>
                        <input
                            type="text"
                            name="wc_checkout_quantity_label"
                            value="<?php echo esc_attr(get_option('wc_checkout_quantity_label', 'How Many Minutes?')); ?>"
                            style="width: 300px;"
                        />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Helper function: get the current (total) cart quantity to display in the field.
 *
 * If you have multiple items, we sum their quantities.
 * Then we unify the entire cart to this single quantity on update.
 */
function wc_checkout_quantity_get_current_cart_quantity() {
    $total_qty = 0;
    if (WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $total_qty += $cart_item['quantity'];
        }
    }
    // Default to 1 if cart is empty or sum is zero (to avoid showing 0)
    return $total_qty > 0 ? $total_qty : 1;
}

/**
 * 4) Add a new row under "order-total" in the review order table.
 *    Pulls label text from our admin option, displays the sum from the cart.
 */
add_action('woocommerce_review_order_after_order_total', 'wc_checkout_quantity_field');
function wc_checkout_quantity_field() {
    $label_text = get_option('wc_checkout_quantity_label', 'How Many Minutes?');
    $current_qty = wc_checkout_quantity_get_current_cart_quantity();
    ?>
    <tr class="wc-checkout-quantity-row">
        <th><?php echo esc_html($label_text); ?></th>
        <td>
            <input
                type="number"
                id="wc-checkout-quantity"
                name="wc-checkout-quantity"
                value="<?php echo esc_attr($current_qty); ?>"
                min="1"
                max="999"
                style="width:80px; text-align:center;"
            />
        </td>
    </tr>
    <?php
}

/**
 * 5) When WooCommerce does an AJAX update_checkout, parse the posted data
 *    and update all cart items to match that quantity.
 */
add_filter('woocommerce_checkout_update_order_review', 'wc_checkout_quantity_update_cart');
function wc_checkout_quantity_update_cart($posted_data) {
    parse_str($posted_data, $params);

    if (isset($params['wc-checkout-quantity'])) {
        $new_quantity = (int) $params['wc-checkout-quantity'];
        if ($new_quantity < 1) {
            $new_quantity = 1;
        }
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                WC()->cart->set_quantity($cart_item_key, $new_quantity, true);
            }
        }
    }
    return $posted_data;
}

/**
 * 6) Debounce script to handle quick arrow clicks or typing changes.
 */
add_action('wp_footer', 'wc_checkout_quantity_inline_script');
function wc_checkout_quantity_inline_script() {
    if (is_checkout()) {
        ?>
        <script>
        (function($){
            var debounceTimer;
            $(document).on('input', '#wc-checkout-quantity', function(){
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function(){
                    $('body').trigger('update_checkout');
                }, 350);
            });
        })(jQuery);
        </script>
        <?php
    }
}
