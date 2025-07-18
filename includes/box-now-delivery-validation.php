<?php

class BNDP_Serializer
{
    public function init()
    {
        add_action('admin_post_boxnow-settings-save', array($this, 'boxnow_settings_save'));
    }

    public function boxnow_settings_save()
    {
        if (!$this->has_valid_nonce()) {
            wp_die('Invalid nonce specified.');
        }

        if (isset($_POST['boxnow_api_url'])) {
            update_option('boxnow_api_url', sanitize_text_field($_POST['boxnow_api_url']));
        }

        if (isset($_POST['boxnow_warehouse_id'])) {
            update_option('boxnow_warehouse_id', sanitize_text_field($_POST['boxnow_warehouse_id']));
        }

        if (isset($_POST['boxnow_client_id'])) {
            update_option('boxnow_client_id', sanitize_text_field($_POST['boxnow_client_id']));
        }

        if (isset($_POST['boxnow_partner_id'])) {
            update_option('boxnow_partner_id', sanitize_text_field($_POST['boxnow_partner_id']));
        }

        if (isset($_POST['boxnow_client_secret'])) {
            update_option('boxnow_client_secret', sanitize_text_field($_POST['boxnow_client_secret']));
        }

        if (isset($_POST['boxnow_button_color'])) {
            update_option('boxnow_button_color', sanitize_hex_color($_POST['boxnow_button_color']));
        }

        if (isset($_POST['boxnow_button_text'])) {
            update_option('boxnow_button_text', sanitize_text_field($_POST['boxnow_button_text']));
        }

        if (isset($_POST['box_now_display_mode'])) {
            update_option('box_now_display_mode', sanitize_key($_POST['box_now_display_mode']));
        }

        if (isset($_POST['boxnow_gps_tracking'])) {
            update_option('boxnow_gps_tracking', sanitize_key($_POST['boxnow_gps_tracking']));
        }

        if (isset($_POST['boxnow_voucher_option'])) {
            update_option('boxnow_voucher_option', sanitize_key($_POST['boxnow_voucher_option']));
        }
        if (isset($_POST['boxnow_voucher_email'])) {
            update_option('boxnow_voucher_email', sanitize_email($_POST['boxnow_voucher_email']));
        }
        if (isset($_POST['boxnow_allow_returns'])) {
            update_option('boxnow_allow_returns', sanitize_text_field($_POST['boxnow_allow_returns']));
        }
        if (isset($_POST['boxnow_mobile_number'])) {
            update_option('boxnow_mobile_number', sanitize_text_field($_POST['boxnow_mobile_number']));
        }
        if (isset($_POST['boxnow_locker_not_selected_message'])) {
            update_option('boxnow_locker_not_selected_message', sanitize_text_field($_POST['boxnow_locker_not_selected_message']));
        }

        $this->redirect();
    }

    private function has_valid_nonce()
    {
        // Check if the nonce field is set
        if (!isset($_POST['boxnow-custom-message'])) {
            return false;
        }

        // Sanitize the nonce field value (remove any unexpected characters)
        $field = sanitize_text_field(wp_unslash($_POST['boxnow-custom-message']));

        // Validate the nonce
        $action = 'boxnow-settings-save';
        if (!wp_verify_nonce($field, $action)) {
            return false;
        }

        return true;
    }

    private function redirect()
    {
        $url = admin_url('admin.php?page=box-now-delivery&status=success');
        wp_safe_redirect($url);
        exit;
    }
}
