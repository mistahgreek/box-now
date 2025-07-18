<?php
/*
Plugin Name: BOX NOW Delivery
Description: A Wordpress plugin from BOX NOW to integrate your eshop with our services.
Author: BOX NOW
Text Domain: box-now-delivery
Version: 2.1.9
WC tested up to: 8.5.0
WC requires at least: 8.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

// HPOS Compatibility Declaration - CRITICAL FIX
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Cancel order API call file
require_once(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-cancel-order.php');

// Include the box-now-delivery-print-order.php file
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-print-order.php';

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    // Include custom shipping method file
    include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-shipping-method.php');

    // Include admin page functions
    include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-admin-page.php');

    /**
     * Enqueue scripts and styles for Box Now Delivery plugin.
     */
    function box_now_delivery_enqueue_scripts()
    {
        if (is_checkout()) {
            $button_color = esc_attr(get_option('boxnow_button_color', '#6CD04E'));
            $button_text = esc_attr(get_option('boxnow_button_text', 'Pick a Locker'));

            wp_enqueue_script('box-now-delivery-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery.js', array('jquery'), '2.1.9', true);
            wp_enqueue_style('box-now-delivery-css', plugins_url('/css/box-now-delivery.css', __FILE__), array(), '2.1.9');

            wp_localize_script('box-now-delivery-js', 'boxNowDeliverySettings', array(
                'partnerId' => esc_attr(get_option('boxnow_partner_id', '')),
                'embeddedIframe' => esc_attr(get_option('embedded_iframe', '')),
                'displayMode' => esc_attr(get_option('box_now_display_mode', 'popup')),
                'buttonColor' => $button_color,
                'buttonText' => $button_text,
                'lockerNotSelectedMessage' => esc_js(get_option("boxnow_locker_not_selected_message", "Please select a locker first!")),
                'gps_option' => get_option('boxnow_gps_tracking', 'on'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('boxnow_checkout_nonce')
            ));
        }
    }
    add_action('wp_enqueue_scripts', 'box_now_delivery_enqueue_scripts');

    // Add a custom field to retrieve the Locker ID from the checkout page - IMPROVED
    add_filter('woocommerce_checkout_fields', 'bndp_box_now_delivery_custom_override_checkout_fields');

    /**
     * Add custom field for Locker ID on checkout.
     *
     * @param array $fields Fields on the checkout.
     * @return array $fields Modified fields.
     */
    function bndp_box_now_delivery_custom_override_checkout_fields($fields)
    {
        $fields['billing']['_boxnow_locker_id'] = array(
            'label' => __('BOX NOW Locker ID', 'box-now-delivery'),
            'placeholder' => _x('BOX NOW Locker ID', 'placeholder', 'box-now-delivery'),
            'required' => false,
            'class' => array('boxnow-form-row-hidden', 'boxnow-locker-id-field'),
            'clear' => true,
            'type' => 'text',
            'priority' => 999
        );
        return $fields;
    }

    /**
     * Hide the locker ID field on the checkout page.
     */
    function bndp_hide_box_now_delivery_locker_id_field()
    {
        if (is_checkout()) {
            ?>
            <script>
                jQuery(document).ready(function($) {
                    $('.boxnow-locker-id-field').hide();
                });
            </script>
            <?php
        }
    }
    add_action('wp_footer', 'bndp_hide_box_now_delivery_locker_id_field');

    // AJAX handler for locker selection - NEW
    add_action('wp_ajax_boxnow_set_locker', 'boxnow_set_locker_handler');
    add_action('wp_ajax_nopriv_boxnow_set_locker', 'boxnow_set_locker_handler');

    function boxnow_set_locker_handler() {
        check_ajax_referer('boxnow_checkout_nonce', 'nonce');
        
        if (isset($_POST['locker_id'])) {
            WC()->session->set('boxnow_selected_locker_id', sanitize_text_field($_POST['locker_id']));
            wp_send_json_success(array('message' => 'Locker ID saved to session'));
        } else {
            wp_send_json_error(array('message' => 'No locker ID provided'));
        }
    }

    // Validate locker selection on checkout - NEW
    add_action('woocommerce_checkout_process', 'boxnow_validate_locker_selection');
    
    function boxnow_validate_locker_selection() {
        // Check if Box Now Delivery is selected
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        if (!is_array($chosen_shipping_methods) || !in_array('box_now_delivery', $chosen_shipping_methods)) {
            return;
        }
        
        // Check if locker is selected
        $locker_id = '';
        if (isset($_POST['_boxnow_locker_id'])) {
            $locker_id = sanitize_text_field($_POST['_boxnow_locker_id']);
        }
        
        // Also check session
        if (empty($locker_id)) {
            $locker_id = WC()->session->get('boxnow_selected_locker_id');
        }
        
        if (empty($locker_id)) {
            wc_add_notice(__('Please select a BOX NOW locker before placing your order.', 'box-now-delivery'), 'error');
        }
    }

    /**
     * Remove the selected locker details from local storage when order placed
     */
    function check_order_received_page()
    {
        if (is_order_received_page()) {
            ?>
            <script>
                jQuery(document).ready(function($) {
                    localStorage.removeItem("box_now_selected_locker");
                });
            </script>
            <?php
        }
    }
    add_action('wp_footer', 'check_order_received_page');

    /* Display field value on the order edit page */
    add_action('woocommerce_admin_order_data_after_billing_address', 'bndp_box_now_delivery_checkout_field_display_admin_order_meta', 10, 1);

    /**
     * Display custom checkout field in the order edit page.
     *
     * @param WC_Order $order WooCommerce Order.
     */
    function bndp_box_now_delivery_checkout_field_display_admin_order_meta($order)
    {
        // Get the order shipping method
        $shipping_methods = $order->get_shipping_methods();
        $box_now_used = false;

        foreach ($shipping_methods as $shipping_method) {
            if ($shipping_method->get_method_id() == 'box_now_delivery') {
                $box_now_used = true;
                break;
            }
        }

        // Only proceed if Box Now Delivery was used
        if ($box_now_used) {
            $locker_id = $order->get_meta('_boxnow_locker_id');
            $warehouse_id = $order->get_meta('_selected_warehouse');

            if (!empty($locker_id) || !empty($warehouse_id)) {
                /* get names for possible warehouses */
                $warehouse_names = boxnow_get_warehouse_names();

                ?>
                <div class="boxnow_data_column">
                    <h4><?php echo esc_html__('BOX NOW Delivery', 'box-now-delivery'); ?><a href="#" class="edit_address"><?php echo esc_html__('Edit', 'woocommerce'); ?></a></h4>
                    <div class="address">
                        <?php
                        echo '<p><strong>' . esc_html__('Locker ID', 'box-now-delivery') . ':</strong> ' . esc_html($locker_id) . '</p>';
                        echo '<p><strong>' . esc_html__('Warehouse ID', 'box-now-delivery') . ':</strong> ' . esc_html($warehouse_id) . ' - ' . esc_html($warehouse_names[$warehouse_id] ?? '') . '</p>';
                        ?>
                    </div>
                    <div class="edit_address">
                        <?php
                        woocommerce_wp_text_input(array(
                            'id' => '_boxnow_locker_id',
                            'label' => esc_html__('Locker ID', 'box-now-delivery'),
                            'wrapper_class' => '_boxnow_locker_id',
                            'value' => $locker_id
                        ));

                        $warehouse_ids = explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')));
                        $warehouses_show = [];
                        foreach ($warehouse_ids as $id) {
                            $warehouses_show[$id] = $id . ' - ' . esc_html($warehouse_names[$id] ?? '');
                        }
                        woocommerce_wp_select(array(
                            'id' => '_selected_warehouse',
                            'label' => esc_html__('Warehouse ID', 'box-now-delivery'),
                            'wrapper_class' => '_selected_warehouse',
                            'options' => $warehouses_show
                        ));
                        ?>
                    </div>
                </div>
                <?php
            }
        }
    }

    /**
     * Get warehouse names - Helper function
     */
    function boxnow_get_warehouse_names() {
        $warehouse_names = [];
        try {
            $access_token = boxnow_get_access_token();
            if ($access_token) {
                $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/origins';
                $origins_args = array(
                    'method' => 'GET',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type' => 'application/json'
                    )
                );
                $warehouses_json = wp_remote_get($api_url, $origins_args);
                if (!is_wp_error($warehouses_json)) {
                    $warehouses_list = json_decode(wp_remote_retrieve_body($warehouses_json), true);
                    if (isset($warehouses_list['data'])) {
                        foreach ($warehouses_list['data'] as $warehouse) {
                            $warehouse_names[$warehouse['id']] = $warehouse['name'];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('BOX NOW: Error getting warehouse names - ' . $e->getMessage());
        }
        return $warehouse_names;
    }

    /**
     * Save custom checkout fields in the order edit page.
     *
     * @param int $post_id The post ID.
     */
    function bndp_box_now_delivery_save_checkout_field_admin_order_meta($post_id)
    {
        $order = wc_get_order($post_id);
        if (!$order) {
            return;
        }

        // Ensure we have the required POST data
        if (!isset($_POST['_boxnow_locker_id']) || !isset($_POST['_selected_warehouse'])) {
            return;
        }

        $order->update_meta_data('_boxnow_locker_id', sanitize_text_field($_POST['_boxnow_locker_id']));
        $order->update_meta_data('_selected_warehouse', sanitize_text_field($_POST['_selected_warehouse']));
        $order->save();
    }
    add_action('woocommerce_process_shop_order_meta', 'bndp_box_now_delivery_save_checkout_field_admin_order_meta');

    /**
     * Save extra details when processing the shop order.
     *
     * @param int $order_id Order ID.
     * @param string $old_status Old order status.
     * @param string $new_status New order status.
     * @param WC_Order $order Order object.
     */
    add_action('woocommerce_order_status_changed', 'boxnow_save_extra_details', 10, 4);

    function boxnow_save_extra_details($order_id, $old_status, $new_status, $order)
    {
        // Log status changes for debugging
        error_log('BOX NOW: Order ID: ' . $order_id . ', Old Status: ' . $old_status . ', New Status: ' . $new_status);
        
        $locker_id = $order->get_meta('_boxnow_locker_id');
        $warehouse_id = $order->get_meta('_selected_warehouse');
        
        error_log('BOX NOW: Locker ID: ' . $locker_id . ', Warehouse ID: ' . $warehouse_id);
    }

    /**
     * Update the order meta with field value - IMPROVED
     *
     * @param WC_Order $order The order object.
     */
    function bndp_box_now_delivery_checkout_field_update_order_meta($order)
    {
        // Get locker ID from POST data
        $locker_id = '';
        if (!empty($_POST['_boxnow_locker_id'])) {
            $locker_id = sanitize_text_field($_POST['_boxnow_locker_id']);
        }
        
        // If not in POST, check session
        if (empty($locker_id)) {
            $locker_id = WC()->session->get('boxnow_selected_locker_id');
        }
        
        // Save locker ID if available
        if (!empty($locker_id)) {
            $order->update_meta_data('_boxnow_locker_id', $locker_id);
        }
        
        // Set default warehouse if not set
        if (!metadata_exists('post', $order->get_id(), '_selected_warehouse')) {
            $warehouse_ids = explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')));
            if (!empty($warehouse_ids[0])) {
                $order->update_meta_data('_selected_warehouse', $warehouse_ids[0]);
            }
        }
        
        $order->save();
        
        // Clear session after saving
        WC()->session->set('boxnow_selected_locker_id', null);
    }
    add_action('woocommerce_checkout_create_order', 'bndp_box_now_delivery_checkout_field_update_order_meta');

} else {
    /**
     * Display admin notice if WooCommerce is not active.
     */
    function bndp_box_now_delivery_admin_notice()
    {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php _e('BOX NOW Delivery requires WooCommerce to be installed and active.', 'box-now-delivery'); ?></p>
        </div>
        <?php
    }
    add_action('admin_notices', 'bndp_box_now_delivery_admin_notice');
}

/**
 * Change Cash on delivery title to custom
 */
add_filter('woocommerce_gateway_title', 'bndp_change_cod_title_for_box_now_delivery', 20, 2);

function bndp_change_cod_title_for_box_now_delivery($title, $payment_id)
{
    if (!is_admin() && $payment_id === 'cod') {
        if (function_exists('WC') && WC()->session) {
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
            $box_now_delivery_method = 'box_now_delivery';

            if (is_array($chosen_shipping_methods) && in_array($box_now_delivery_method, $chosen_shipping_methods)) {
                $title = __('BOX NOW PAY ON THE GO!', 'box-now-delivery');
            }
        }
    }
    return $title;
}

/*
* Send information to BOX NOW api and for sending an email to the customer with the voucher
*/
add_action('woocommerce_order_status_completed', 'boxnow_order_completed');

function boxnow_order_completed($order_id)
{
    // Check if the '_manual_status_change' transient is set
    if (get_transient('_manual_status_change')) {
        delete_transient('_manual_status_change');
        return;
    }

    // Check if the Send voucher via email option is selected
    if (get_option('boxnow_voucher_option') !== 'email') {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    if ($order->has_shipping_method('box_now_delivery')) {
        // Check if the voucher has already been created
        if ($order->get_meta('_voucher_created', true)) {
            return;
        }

        $prep_data = boxnow_prepare_data($order);
        $response = boxnow_order_completed_delivery_request($prep_data, $order->get_id(), 1);
        $response_data = json_decode($response, true);

        if (isset($response_data['parcels'][0]['id'])) {
            $order->update_meta_data('_boxnow_parcel_id', $response_data['parcels'][0]['id']);
            $order->update_meta_data('_voucher_created', 'yes');
            $order->save();
        } else {
            error_log("BOX NOW: Delivery request failed for order ID: $order_id. Response: " . print_r($response_data, true));
        }
    }
}

// This is the delivery request only for the boxnow_order_completed function
function boxnow_order_completed_delivery_request($prep_data, $order_id, $num_vouchers)
{
    try {
        $access_token = boxnow_get_access_token();
        if (!$access_token) {
            throw new Exception('Failed to get access token');
        }
        
        $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
        $randStr = strval(mt_rand());
        $payment_method = $prep_data['payment_method'];
        $send_voucher_via_email = get_option('boxnow_voucher_option', 'email') === 'email';

        $items = [];
        for ($i = 0; $i < $num_vouchers; $i++) {
            $item_data = [
                "value" => $prep_data['product_price'],
                "weight" => $prep_data['weight']
            ];

            if (isset($prep_data['compartment_sizes'])) {
                $item_data["compartmentSize"] = $prep_data['compartment_sizes'][0];
            }

            $items[] = $item_data;
        }

        $order = wc_get_order($order_id);
        $client_email = $order->get_billing_email();

        $data = [
            "notifyOnAccepted" => $send_voucher_via_email ? get_option('boxnow_voucher_email', '') : '',
            "orderNumber" => $randStr,
            "invoiceValue" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
            "paymentMode" => $payment_method === 'cod' ? "cod" : "prepaid",
            "amountToBeCollected" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
            "allowReturn" => boolval(get_option('boxnow_allow_returns', '')),
            "origin" => [
                "contactNumber" => get_option('boxnow_mobile_number', ''),
                "contactEmail" => get_option('boxnow_voucher_email', ''),
                "locationId" => $prep_data['selected_warehouse'],
            ],
            "destination" => [
                "contactNumber" => $prep_data['phone'],
                "contactEmail" => $client_email,
                "contactName" => $prep_data['first_name'] . ' ' . $prep_data['last_name'],
                "locationId" => $prep_data['locker_id'],
            ],
            "items" => $items
        ];

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($response_body['id'])) {
            $parcel_ids = [];
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];
            }
            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
            $order->save();
        } else {
            error_log('BOX NOW: API Response: ' . print_r($response_body, true));
            throw new Exception('Error: Unable to create vouchers.' . json_encode($response_body));
        }
        
        return wp_remote_retrieve_body($response);
        
    } catch (Exception $e) {
        error_log('BOX NOW: Error in delivery request - ' . $e->getMessage());
        return json_encode(['error' => $e->getMessage()]);
    }
}

// Function to determine the compartment size based on dimensions
function boxnow_get_compartment_size($dimensions)
{
    // Define the dimensions for each compartment size
    $small = ['length' => 60, 'width' => 45, 'height' => 8];
    $medium = ['length' => 60, 'width' => 45, 'height' => 17];
    $large = ['length' => 60, 'width' => 45, 'height' => 36];

    // Check if all dimensions are either not set or equal to 0
    if ((!isset($dimensions['length']) || $dimensions['length'] == 0) &&
        (!isset($dimensions['width']) || $dimensions['width'] == 0) &&
        (!isset($dimensions['height']) || $dimensions['height'] == 0)
    ) {
        return 2; // Default to medium
    }

    // Check if the product dimensions fit the small compartment size
    if (
        $dimensions['length'] <= $small['length'] &&
        $dimensions['width'] <= $small['width'] &&
        $dimensions['height'] <= $small['height']
    ) {
        return 1;
    }

    // Check if the product dimensions fit the medium compartment size
    if (
        $dimensions['length'] <= $medium['length'] &&
        $dimensions['width'] <= $medium['width'] &&
        $dimensions['height'] <= $medium['height']
    ) {
        return 2;
    }

    // Check if the product dimensions fit the large compartment size
    if (
        $dimensions['length'] <= $large['length'] &&
        $dimensions['width'] <= $large['width'] &&
        $dimensions['height'] <= $large['height']
    ) {
        return 3;
    }

    // If the product dimensions don't fit any of the compartment sizes, return an error
    throw new Exception('Invalid product dimensions.');
}

function boxnow_prepare_data($order)
{
    // Update possibly edited fields
    if (isset($_POST['_boxnow_locker_id']) && !empty($_POST['_boxnow_locker_id'])) {
        $order->update_meta_data('_boxnow_locker_id', wc_clean($_POST['_boxnow_locker_id']));
    }
    if (isset($_POST['_selected_warehouse']) && !empty($_POST['_selected_warehouse'])) {
        $order->update_meta_data('_selected_warehouse', wc_clean($_POST['_selected_warehouse']));
    }
    $order->save();

    // We need the shipping address for the voucher
    $prep_data = $order->get_address('shipping');

    foreach ($order->get_meta_data() as $data) {
        $meta_key = $data->key;
        $meta_value = $data->value;

        switch ($meta_key) {
            case get_option('boxnow-save-data-addressline1', ''):
                $prep_data['locker_addressline1'] = $meta_value;
                break;
            case get_option('boxnow-save-data-postalcode', ''):
                $prep_data['locker_postalcode'] = (int)$meta_value;
                break;
            case get_option('boxnow-save-data-addressline2', ''):
                $prep_data['locker_addressline2'] = $meta_value;
                break;
            case '_boxnow_locker_id':
                $prep_data['locker_id'] = $meta_value;
                break;
            case '_selected_warehouse':
                $prep_data['selected_warehouse'] = $meta_value;
                break;
        }
    }

    $prep_data['payment_method'] = $order->get_payment_method();
    $prep_data['order_total'] = $order->get_total();
    $prep_data['product_price'] = number_format(strval($order->get_subtotal()), 2, '.', '');

    $compartment_sizes = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        // Ensure the dimensions are valid float values. If not, consider them as 0.
        $dimensions = [
            'length' => is_numeric($product->get_length()) ? floatval($product->get_length()) : 0,
            'width' => is_numeric($product->get_width()) ? floatval($product->get_width()) : 0,
            'height' => is_numeric($product->get_height()) ? floatval($product->get_height()) : 0
        ];

        $compartment_size = boxnow_get_compartment_size($dimensions);
        $quantity = $item->get_quantity();
        for ($i = 0; $i < $quantity; $i++) {
            $compartment_sizes[] = $compartment_size;
        }
    }
    $prep_data['compartment_sizes'] = $compartment_sizes;

    // Ensure the country's prefix is not missing
    // Get the billing address client phone because shipping address does not have phone
    $client_phone = $order->get_billing_phone();
    $tel = $client_phone;

    if (substr($tel, 0, 1) != '+') {
        // If the phone starts with "00", replace "00" with "+"
        if (substr($tel, 0, 2) === '00') {
            $tel = '+' . substr($tel, 2);
        }
        // If the phone starts with the specified codes and has less than 9 digits, put "+357" in the beginning
        elseif (in_array(substr($tel, 0, 2), ['22', '23', '24', '25', '26', '96', '97', '98', '99']) && strlen(preg_replace('/[^\d]/', '', $tel)) < 9) {
            $tel = '+357' . preg_replace('/[^\d]/', '', $tel);
        }
        else {
            $tel = '+30' . preg_replace('/[^\d]/', '', $tel);
        }
    }
    $prep_data['phone'] = $tel;

    // Calculate the weight and pass it
    $weight = 0;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;
        
        $quantity = $item->get_quantity();
        $product_weight = $product->get_weight();

        // Check if weight is not null and is a numeric value, else consider it as 0
        if (!is_null($product_weight) && is_numeric($product_weight)) {
            $weight += floatval($product_weight) * $quantity;
        }
    }
    $prep_data['weight'] = $weight;

    return $prep_data;
}

function boxnow_send_delivery_request($prep_data, $order_id, $num_vouchers, $compartment_sizes)
{
    try {
        $access_token = boxnow_get_access_token();
        if (!$access_token) {
            throw new Exception('Failed to get access token');
        }
        
        $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
        $randStr = strval(mt_rand());
        $payment_method = $prep_data['payment_method'];
        $send_voucher_via_email = get_option('boxnow_voucher_option', 'email') === 'email';

        // Prepare items array based on the number of vouchers
        $items = [];
        for ($i = 0; $i < $num_vouchers; $i++) {
            $items[] = [
                "value" => $prep_data['product_price'],
                "weight" => $prep_data['weight'],
                "compartmentSize" => $compartment_sizes
            ];
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // Get the billing address client email because shipping address does not have email
        $client_email = $order->get_billing_email();

        $data = [
            "notifyOnAccepted" => $send_voucher_via_email ? get_option('boxnow_voucher_email', '') : '',
            "orderNumber" => $randStr,
            "invoiceValue" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
            "paymentMode" => $payment_method === 'cod' ? "cod" : "prepaid",
            "amountToBeCollected" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
            "allowReturn" => boolval(get_option('boxnow_allow_returns', '')),
            "origin" => [
                "contactNumber" => get_option('boxnow_mobile_number', ''),
                "contactEmail" => get_option('boxnow_voucher_email', ''),
                "locationId" => $prep_data['selected_warehouse'],
            ],
            "destination" => [
                "contactNumber" => $prep_data['phone'],
                "contactEmail" => $client_email,
                "contactName" => $prep_data['first_name'] . ' ' . $prep_data['last_name'],
                "locationId" => $prep_data['locker_id'],
            ],
            "items" => $items
        ];

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($response_body['id'])) {
            $parcel_ids = [];
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];
            }
            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
            $order->save();
        } else {
            error_log('BOX NOW: API Response: ' . print_r($response_body, true));
            throw new Exception('Error: Unable to create vouchers.' . json_encode($response_body));
        }
        
        return wp_remote_retrieve_body($response);
        
    } catch (Exception $e) {
        error_log('BOX NOW: Error in delivery request - ' . $e->getMessage());
        throw $e;
    }
}

function boxnow_get_access_token()
{
    try {
        $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/auth-sessions';
        $client_id = get_option('boxnow_client_id', '');
        $client_secret = get_option('boxnow_client_secret', '');

        if (empty($client_id) || empty($client_secret)) {
            throw new Exception('Missing API credentials');
        }

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'grant_type' => 'client_credentials',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);

        // Check if the 'access_token' key exists in the response
        if (isset($json['access_token'])) {
            return $json['access_token'];
        } else {
            error_log('BOX NOW: API Response: ' . print_r($json, true));
            throw new Exception('Invalid API response - no access token');
        }
    } catch (Exception $e) {
        error_log('BOX NOW: Error getting access token - ' . $e->getMessage());
        return null;
    }
}

// Refresh the checkout page when the payment method changes
add_action('woocommerce_review_order_before_payment', 'boxnow_add_cod_payment_refresh_script');

// Print Vouchers section
function box_now_delivery_vouchers_input($order)
{
    // Get the order shipping method
    $shipping_methods = $order->get_shipping_methods();
    $box_now_used = false;

    foreach ($shipping_methods as $shipping_method) {
        if ($shipping_method->get_method_id() == 'box_now_delivery') {
            $box_now_used = true;
            break;
        }
    }

    // Only proceed if Box Now Delivery was used
    if ($box_now_used) {
        if (get_option('boxnow_voucher_option', 'email') === 'button') {
            // Get the maximum number of vouchers based on the order items
            $max_vouchers = 0;
            foreach ($order->get_items() as $item) {
                $max_vouchers += $item->get_quantity();
            }

            $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
            $vouchers_created = $order->get_meta('_boxnow_vouchers_created');
            $button_disabled = $vouchers_created ? 'disabled' : '';

            // Get the parcel IDs for the current order and pass them to the JavaScript code
            if (!empty($parcel_ids)) {
                echo '<input type="hidden" id="box_now_parcel_ids" value="' . esc_attr(json_encode($parcel_ids ?: [])) . '">';
            }

            // Add the hidden input field for create_vouchers_enabled
            echo '<input type="hidden" id="create_vouchers_enabled" value="true" />';

            echo '<input type="hidden" id="max_vouchers" value="' . esc_attr($max_vouchers) . '">';

            if ($parcel_ids) {
                $links_html = '';
                foreach ($parcel_ids as $parcel_id) {
                    $links_html .= '<a href="#" data-parcel-id="' . esc_attr($parcel_id) . '" class="parcel-id-link box-now-link">&#128196; ' . esc_html($parcel_id) . '</a> ';
                    $links_html .= '<button class="cancel-voucher-btn" data-order-id="' . esc_attr($order->get_id()) . '" style="color: white; background-color: red; border-radius: 4px; margin: 4px 0; border: none; cursor: pointer; padding: 6px 12px; font-size: 13px;">&#9664; Cancel Voucher</button><br>';
                }
            } else {
                $links_html = '';
            }
            ?>
            <div class="box-now-vouchers">
                <h4>Create BOX NOW Voucher(s)</h4>
                <p>Vouchers for this order (Max Vouchers: <span style="font-weight: bold; color: red;"><?php echo esc_html($max_vouchers); ?></span>)</p>
                <input type="hidden" id="box_now_order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
                <input pattern="^[1-<?php echo esc_attr($max_vouchers); ?>]$" type="number" id="box_now_voucher_code" name="box_now_voucher_code" min="1" max="<?php echo esc_attr($max_vouchers); ?>" value="1" placeholder="Enter voucher quantity" style="width: 50%;" />
                <!-- Add buttons for each compartment size -->
                <div class="box-now-compartment-size-buttons" style="margin-top: 10px;">
                    <button type="button" id="box_now_create_voucher_small" class="button button-primary" data-compartment-size="small" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;">Create Vouchers (Small)</button>
                    <button type="button" id="box_now_create_voucher_medium" class="button button-primary" data-compartment-size="medium" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;">Create Vouchers (Medium)</button>
                    <button type="button" id="box_now_create_voucher_large" class="button button-primary" data-compartment-size="large" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;">Create Vouchers (Large)</button>
                </div>
                <div id="box_now_voucher_link"><?php echo wp_kses_post($links_html); ?></div>
            </div>
            <?php
        }
    }
}
add_action('woocommerce_admin_order_data_after_shipping_address', 'box_now_delivery_vouchers_input', 10, 1);

function box_now_delivery_vouchers_js()
{
    // Enqueue your script here if you haven't already
    wp_enqueue_script('box-now-delivery-js', plugin_dir_url(__FILE__) . 'js/box-now-create-voucher.js', array('jquery'), '2.1.9', true);

    // Pass the nonce to your script
    wp_localize_script('box-now-delivery-js', 'myAjax', array(
        'nonce' => wp_create_nonce('box-now-delivery-nonce'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ));
}
add_action('admin_enqueue_scripts', 'box_now_delivery_vouchers_js');

function boxnow_cancel_voucher_ajax_handler()
{
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'box-now-delivery-nonce')) {
        wp_die('Invalid nonce');
    }

    // Get order ID and parcel ID from the request
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $parcel_id = isset($_POST['parcel_id']) ? sanitize_text_field($_POST['parcel_id']) : '';

    // Check if the order ID is valid
    if ($order_id > 0 && $parcel_id) {
        // Get the order object
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Invalid order ID');
            return;
        }

        // Call the function to cancel the order on the Box Now API
        $api_cancellation_result = boxnow_send_cancellation_request($parcel_id);
        if ($api_cancellation_result === 'success') {
            // Call the function to cancel the order in WooCommerce
            boxnow_order_canceled($order_id, '', 'wc-boxnow-canceled', $order);

            // Remove the parcel_id from the parcel_ids array in the order metadata
            $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
            if (($key = array_search($parcel_id, $parcel_ids)) !== false) {
                unset($parcel_ids[$key]);
                $parcel_ids = array_values($parcel_ids); // Reindex the array

                // Update the parcel_ids metadata only if the parcel ID was removed
                $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
                $order->save();
            }

            // Send a success response
            wp_send_json_success();
        } else {
            // Send an error response with the API error message
            wp_send_json_error("Box Now API cancellation failed: " . $api_cancellation_result);
        }
    } else {
        // Send an error response
        wp_send_json_error('Invalid order or parcel ID');
    }
}
add_action('wp_ajax_cancel_voucher', 'boxnow_cancel_voucher_ajax_handler');
add_action('wp_ajax_nopriv_cancel_voucher', 'boxnow_cancel_voucher_ajax_handler');

function boxnow_create_box_now_vouchers_callback()
{
    // Check for the nonce
    check_ajax_referer('box-now-delivery-nonce', 'security');

    if (!isset($_POST['order_id']) || !isset($_POST['voucher_quantity']) || !isset($_POST['compartment_size'])) {
        wp_send_json_error('Error: Missing required data.');
    }

    $order_id = intval($_POST['order_id']);
    $voucher_quantity = intval($_POST['voucher_quantity']);
    $compartment_size = intval(sanitize_text_field($_POST['compartment_size']));

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Error: Order not found.');
    }
    
    try {
        $prep_data = boxnow_prepare_data($order);
        $delivery_request_response = boxnow_send_delivery_request($prep_data, $order_id, $voucher_quantity, $compartment_size);
        $response_body = json_decode($delivery_request_response, true);
        
        if (isset($response_body['id'])) {
            $parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
            if (!$parcel_ids) {
                $parcel_ids = [];
            }
            // Save the new parcel ids in the meta data
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];

                // Save the order ID in the parcel's metadata
                update_option('_boxnow_parcel_order_id_' . $parcel['id'], $order_id);
            }
            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
            $order->update_meta_data('_boxnow_vouchers_created', 1);
            $order->save();

            // check if there are any parcel ids after the update
            $parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
            if (!$parcel_ids || count($parcel_ids) == 0) {
                throw new Exception('Error: No parcel ids available. API response: ' . json_encode($response_body));
            }
            
            $new_parcel_ids = array_slice($parcel_ids, -$voucher_quantity); // Get the new parcel IDs
            wp_send_json_success(array('new_parcel_ids' => $new_parcel_ids));
        } else {
            throw new Exception('Error: Unable to create vouchers. API response: ' . json_encode($response_body));
        }
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}
add_action('wp_ajax_create_box_now_vouchers', 'boxnow_create_box_now_vouchers_callback');

function boxnow_print_box_now_voucher_callback()
{
    if (!isset($_GET['parcel_id'])) {
        wp_die('Error: Missing required data.');
    }

    $parcel_id = sanitize_text_field($_GET['parcel_id']);

    // Retrieve the order ID from the parcel ID's metadata
    $order_id = get_option('_boxnow_parcel_order_id_' . $parcel_id);

    if (!$order_id) {
        wp_die('Error: Order not found.');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die('Error: Order not found.');
    }

    try {
        boxnow_print_voucher_pdf($parcel_id);
    } catch (Exception $e) {
        wp_die('Error: ' . $e->getMessage());
    }

    exit();
}
add_action('wp_ajax_print_box_now_voucher', 'boxnow_print_box_now_voucher_callback');
add_action('wp_ajax_nopriv_print_box_now_voucher', 'boxnow_print_box_now_voucher_callback');

/**
 * Add voucher email validation script to the admin footer.
 */
function boxnow_voucher_email_validation()
{
    if (is_admin()) {
        ?>
        <script>
            function isValidEmail(email) {
                const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(email.toLowerCase());
            }

            function displayEmailValidationMessage(message) {
                const messageContainer = document.getElementById('email_validation_message');
                if (messageContainer) {
                    messageContainer.textContent = message;
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const emailInput = document.querySelector('input[name="boxnow_voucher_email"]');

                if (emailInput) {
                    emailInput.addEventListener('input', function() {
                        if (!isValidEmail(emailInput.value)) {
                            displayEmailValidationMessage('Please use a valid email address!');
                        } else {
                            displayEmailValidationMessage('');
                        }
                    });
                }
            });
        </script>
        <?php
    }
}
add_action('admin_footer', 'boxnow_voucher_email_validation');

add_action('admin_enqueue_scripts', 'boxnow_load_jquery_in_admin');
function boxnow_load_jquery_in_admin()
{
    wp_enqueue_script('jquery');
}