<?php

/**
 * Template Name: Box Now Delivery Print Order
 */
function boxnow_print_voucher_pdf()
{
    // Get the parcel ID from the URL
    $parcel_id = isset($_GET['parcel_id']) ? sanitize_text_field($_GET['parcel_id']) : '';

    if (empty($parcel_id)) {
        echo esc_html__('Parcel ID was not found!', 'box-now-delivery');
        exit;
    }

    $api_url = get_option('boxnow_api_url');
    $api_id = get_option('boxnow_client_id');
    $api_secret = get_option('boxnow_client_secret');
    $api_warehouse = get_option('boxnow_warehouse_id');
    $api_partner = get_option('boxnow_partner_id');

    // Get API session
    $auth_response = wp_remote_post('https://' . $api_url . '/api/v1/auth-sessions', array(
        'method' => 'POST',
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode(array(
            'grant_type' => 'client_credentials',
            'client_id' => $api_id,
            'client_secret' => $api_secret,
        )),
    ));

    if (is_wp_error($auth_response) || 200 != wp_remote_retrieve_response_code($auth_response)) {
        echo esc_html__('Error: Authentication failed.', 'box-now-delivery');
        exit;
    }

    $auth_json = json_decode(wp_remote_retrieve_body($auth_response), true);
    $access_token = $auth_json['access_token'];

    // Print voucher
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="label.pdf"');

    $headers = [
        'accept' => 'application/pdf',
        'Authorization' => 'Bearer ' . $access_token
    ];

    $response = wp_remote_get('https://' . $api_url . '/api/v1/parcels/' . $parcel_id . '/label.pdf', array(
        'headers' => $headers,
        'timeout' => 5,
    ));

    if (is_wp_error($response)) {
        echo esc_html__('Error: ' . $response->get_error_message(), 'box-now-delivery');
        exit;
    }

    echo wp_remote_retrieve_body($response);

    exit();
}
