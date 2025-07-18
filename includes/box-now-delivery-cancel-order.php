<?php

// Register custom order status
add_action('init', 'boxnow_register_boxnow_canceled_order_status');
// Add custom order status to WooCommerce
//add_filter('wc_order_statuses', 'boxnow_add_canceled_order_status');
add_filter('woocommerce_admin_order_actions', 'boxnow_add_cancel_order_button', 10, 2);
add_action('admin_head', 'boxnow_add_cancel_order_button_css');
// Add the action for sending a cancellation request to the Box Now API
add_action('woocommerce_order_status_changed', 'boxnow_order_canceled', 5, 4);
add_action('transition_post_status', 'boxnow_log_order_status_transition', 10, 3);

function boxnow_log_order_status_transition($new_status, $old_status, $post)
{
    if ('shop_order' === $post->post_type) {
        error_log("Order ID: {$post->ID} status transitioned from {$old_status} to {$new_status}");
    }
}

function boxnow_register_boxnow_canceled_order_status()
{
    register_post_status('wc-boxnow-canceled', array(
        'label' => __('Box Now Canceled', 'box-now-delivery'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Box Now Canceled <span class="count">(%s)</span>', 'Box Now Canceled <span class="count">(%s)</span>', 'box-now-delivery')
    ));
}

/**function boxnow_add_canceled_order_status($order_statuses)
{
    $order_statuses['wc-boxnow-canceled'] = __('BOX NOW Cancel Order', 'box-now-delivery');
    return $order_statuses;
}**/

function boxnow_add_cancel_order_button($actions, $order)
{
    if ($order->has_status(array('completed'))) {
        $actions['boxnow_cancel'] = array(
            'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_mark_order_status&status=wc-boxnow-canceled&order_id=' . $order->get_id()), 'woocommerce-mark-order-status'),
            'name' => __('Cancel Order', 'box-now-delivery'),
            'action' => "boxnow_cancel",
        );
    }
    return $actions;
}

function boxnow_add_cancel_order_button_css()
{
    echo '<style>
          .wc-action-button-boxnow_cancel::after {
            content: "\f153";
            color: #a00;
          }
          </style>';
}

function boxnow_order_canceled($order_id, $old_status, $new_status, $order)
{
    error_log("Order ID: $order_id status transitioned from $old_status to $new_status");

    // Check if the new status is "wc-boxnow-canceled" or "boxnow-canceled"
    if ($new_status != 'wc-boxnow-canceled' && $new_status != 'boxnow-canceled') {
        return;
    }

    if ($order->has_shipping_method('box_now_delivery')) {
        $parcel_id = $order->get_meta('_boxnow_parcel_id');

        if (!empty($parcel_id)) {
            $result = boxnow_send_cancellation_request($parcel_id);
            error_log("Box Now Cancellation Result: " . print_r($result, true));
        } else {
            error_log("No parcel ID found for order ID: $order_id");
        }
    } else {
        error_log("Order ID $order_id does not have Box Now Delivery shipping method");
    }
}

function boxnow_send_cancellation_request($parcel_id)
{
    $access_token = boxnow_get_access_token();
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/parcels/' . $parcel_id . ':cancel';

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ],
        'body' => '{}',
    ]);

    if (is_wp_error($response)) {
        error_log("Box Now Cancellation Error: " . $response->get_error_message());
        return $response->get_error_message();
    } else {
        $response_body = wp_remote_retrieve_body($response);
        error_log("Box Now Cancellation API Response: \n" . $response_body); // Log the API response

        // Check for empty response and treat as success
        if (empty($response_body)) {
            error_log("Box Now Cancellation Result: Empty response, considering as success");
            return 'success';
        }

        $response_data = json_decode($response_body, true);
        if (isset($response_data['success']) && $response_data['success'] == true) {
            return 'success';
        } else {
            error_log("Something went wrong: " . (isset($response_data['error']) ? $response_data['error'] : 'Unknown error'));
            return 'failed';
        }
    }
}
