<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

add_action('plugins_loaded', 'box_now_delivery_shipping_method');

/**
 * Initialize the Box Now Delivery shipping method.
 */
function box_now_delivery_shipping_method()
{
    if (!class_exists('Box_Now_Delivery_Shipping_Method')) {
        /**
         * Class Box_Now_Delivery_Shipping_Method
         *
         * @property array $form_fields
         */
        class Box_Now_Delivery_Shipping_Method extends WC_Shipping_Method
        {
            /**
             * Constructor for the shipping class.
             */
            public function __construct($instance_id = 0)
            {
                $this->id = 'box_now_delivery';
                $this->instance_id = absint($instance_id);
                $this->method_title = __('BOX NOW Delivery', 'box-now-delivery');
                $this->method_description = __('Custom settings for the BOX NOW Delivery', 'box-now-delivery');

                $this->supports = array(
                        'shipping-zones',
                        'instance-settings',
                        'instance-settings-modal',
                );

                $this->init();

                // Load the settings.
                $this->init_settings();

                // Define user set variables.
                $this->title = $this->get_option('title');
                $this->cost = $this->get_option('cost');
                $this->free_delivery_threshold = $this->get_option('free_delivery_threshold');
                $this->taxable = $this->get_option('taxable');
            }

            /**
             * Initialize settings and form fields.
             */
            function init()
            {
                $this->init_form_fields();
                $this->init_settings();
            }

            /**
             * Processes and saves options.
             * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
             *
             * @return bool was anything saved?
             */
            public function process_admin_options()
            {
                $this->init_settings();

                $post_data = $this->get_post_data();

                foreach ($this->get_form_fields() as $key => $field) {
                    if ('title' !== $this->get_field_type($field)) {
                        try {
                            $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                        } catch (Exception $e) {
                            $this->add_error($e->getMessage());
                        }
                    }
                }

                return update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
            }

            public function get_option_key()
            {
                return $this->plugin_id . $this->id . '_' . $this->instance_id . '_settings';
            }

            /**
             * Define settings fields for the shipping method.
             */
            function init_form_fields()
            {
                $this->form_fields = array(
                        'enabled' => array(
                                'title' => __('Enable/Disable', 'box-now-delivery'),
                                'type' => 'checkbox',
                                'description' => '',
                                'default' => 'yes'
                        ),
                        'title' => array(
                                'title' => __('Method Title', 'box-now-delivery'),
                                'type' => 'text',
                                'description' => __('This controls the title which the user sees during checkout.', 'box-now-delivery'),
                                'default' => __('Box Now Delivery', 'box-now-delivery'),
                                'desc_tip' => true,
                        ),
                        'cost' => array(
                                'title' => __('Cost', 'box-now-delivery'),
                                'type' => 'text',
                                'description' => __('Enter the cost for this shipping method', 'box-now-delivery'),
                                'default' => 0,
                                'desc_tip' => true,
                        ),
                        'free_delivery_threshold' => array(
                                'title' => __('Free Delivery Threshold', 'box-now-delivery'),
                                'type' => 'number',
                                'description' => __('If the cart total is above this amount, the shipping cost will be free.', 'box-now-delivery'),
                                'default' => '',
                                'desc_tip' => true,
                        ),
                        'taxable' => array(
                                'title' => __('Taxable', 'box-now-delivery'),
                                'type' => 'select',
                                'description' => __('Should the shipping cost be taxed?', 'box-now-delivery'),
                                'default' => 'yes',
                                'options' => array(
                                        'yes' => __('Yes', 'box-now-delivery'),
                                        'no' => __('No', 'box-now-delivery'),
                                ),
                        ),
                        'custom_weight' => array(
                                'title'       => __('Max Weight', 'box-now-delivery'),
                                'type'        => 'number',
                                'description' => __('Maximum weight allowed for this shipping method grams or kilos depending on the measurement unit on your products', 'box-now-delivery'),
                                'placeholder' => __('20kg', 'box-now-delivery'),
                                'default' => 20,
                                'desc_tip' => true,
                                'custom_attributes' => array(
                                        'step' => '0.1',
                                        'min' => '0',
                                ),
                        ),
                        'dimensions' => array(
                                'title' => __('Max Package Dimensions', 'box-now-delivery'),
                                'type' => 'title',
                                'description' => __('Maximum package size allowed for this shipping method', 'box-now-delivery'),
                        ),
                        'max_length' => array(
                                'title' => __('Max Length (cm)', 'box-now-delivery'),
                                'type' => 'number',
                                'description' => __('Maximum length of package allowed for this shipping method (in cm)', 'box-now-delivery'),
                                'placeholder' => __('60 cm', 'box-now-delivery'),
                                'default' => 60,
                                'custom_attributes' => array(),
                        ),
                        'max_width' => array(
                                'title' => __('Max Width (cm)', 'box-now-delivery'),
                                'type' => 'number',
                                'description' => __('Maximum width of package allowed for this shipping method (in cm)', 'box-now-delivery'),
                                'placeholder' => __('45 cm', 'box-now-delivery'),
                                'default' => 45,
                                'custom_attributes' => array(),
                        ),
                        'max_height' => array(
                                'title' => __('Max Height (cm)', 'box-now-delivery'),
                                'type' => 'number',
                                'description' => __('Maximum height of package allowed for this shipping method (in cm)', 'box-now-delivery'),
                                'placeholder' => __('36 cm', 'box-now-delivery'),
                                'default' => 36,
                                'custom_attributes' => array(),
                        ),
                        'cod_description' => array(
                                'title' => __('Cash on delivery custom description settings', 'box-now-delivery'),
                                'type' => 'title',
                                'description' => __('Enable the custom Cash on delivery description and enter your custom text', 'box-now-delivery'),
                        ),
                        'enable_custom_cod_description' => array(
                                'title' => __('Enable Custom Description for COD', 'box-now-delivery'),
                                'type' => 'checkbox',
                                'description' => __('Enable or disable the custom description when Cash on Delivery is selected.', 'box-now-delivery'),
                                'default' => 'no',
                                'class' => 'enable_custom_cod_description',
                        ),
                        'custom_cod_description' => array(
                                'title' => __('Custom COD Description', 'box-now-delivery'),
                                'type' => 'text',
                                'description' => __('Enter the custom description for Cash on Delivery.', 'box-now-delivery'),
                                'default' => '',
                                'desc_tip' => true,
                                'class' => 'custom_cod_description_field',
                        ),
                );
            }
            /**
             * Calculate the shipping cost.
             *
             * @param array $package Shipping package.
             */
            public function calculate_shipping($package = [])
            {
                // Check if any item in the cart is oversized
                if ($this->has_oversized_products()) {
                    return; // Do not display the Box Now Delivery shipping method if an item is oversized
                }

                // Taxable yes or no
                $taxable = ($this->taxable == 'yes') ? true : false;

                // Get the order total
                $order_total = WC()->cart->get_displayed_subtotal();

                // Adjust total for any coupons
                if (!empty(WC()->cart->get_coupons())) {
                    foreach (WC()->cart->get_coupons() as $code => $coupon) {
                        if ($coupon->is_type('fixed_cart')) {
                            $order_total -= $coupon->get_amount();
                        } else if ($coupon->is_type('percent')) {
                            $order_total -= ($coupon->get_amount() / 100) * $order_total;
                        }
                    }
                }

                // Get the user-defined threshold for free delivery
                $free_delivery_threshold = $this->get_option('free_delivery_threshold');

                // Check if the order total is above the threshold for free delivery
                if (!empty($free_delivery_threshold) && $order_total >= $free_delivery_threshold) {
                    $this->cost = 0;
                }

                $rate = [
                        'id'       => $this->id,
                        'label'    => $this->title,
                        'cost'     => $this->cost,
                        'taxes' => $taxable ? WC_Tax::calc_shipping_tax($this->cost, WC_Tax::get_shipping_tax_rates()) : '',
                        'calc_tax' => 'per_item',
                ];

                // Register the rate.
                $this->add_rate($rate);
            }

            /**
             * Checks if the cart contains any oversized products or if the total weight exceeds the custom weight limit.
             *
             * @return bool Returns true if the cart contains oversized products or if the total weight exceeds the custom weight limit, otherwise returns false.
             */
            private function has_oversized_products()
            {
                $custom_weight_limit = floatval($this->settings['custom_weight']);
                $oversized = false;

                // Loop through each item in the cart
                foreach (WC()->cart->get_cart_contents() as $cart_item) {
                    $length = $cart_item['data']->get_length();
                    $width = $cart_item['data']->get_width();
                    $height = $cart_item['data']->get_height();

                    // Handle the weight calculation
                    $weight = $cart_item['data']->get_weight();
                    $weight = is_numeric($weight) ? floatval($weight) : 0;

                    if ($length > $this->settings['max_length'] || $width > $this->settings['max_width'] || $height > $this->settings['max_height'] || $weight > $custom_weight_limit) {
                        $oversized = true;
                        break;
                    }
                }
                // Return true if any product has oversized dimensions or if any individual item's weight exceeds the custom weight limit
                return $oversized;
            }
        }
    }
}

// Modify the Cash on Delivery payment method's description based on the shipping zone
add_filter('woocommerce_gateway_description', 'boxnow_change_cod_description', 10, 2);
function boxnow_change_cod_description($description, $payment_id)
{
    if ('cod' !== $payment_id) {
        return $description;
    }

    // Get the chosen shipping methods from the current customer's session
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

    // Only modify the description if the chosen shipping method is 'box_now_delivery'
    if (is_array($chosen_shipping_methods) && in_array('box_now_delivery', $chosen_shipping_methods)) {
        // Get the current customer's package
        $package = array();
        if (WC()->customer) {
            $package = array(
                    'destination' => array(
                            'country' => WC()->customer->get_shipping_country(),
                            'state' => WC()->customer->get_shipping_state(),
                            'postcode' => WC()->customer->get_shipping_postcode(),
                    ),
            );
        }

        // Get the shipping zone matching the customer's package
        $shipping_zone = WC_Shipping_Zones::get_zone_matching_package($package);

        // Now you can access the shipping methods of the shipping zone
        $shipping_methods = $shipping_zone->get_shipping_methods();

        foreach ($shipping_methods as $instance_id => $shipping_method) {
            if ('box_now_delivery' === $shipping_method->id) {
                $enable_custom_cod_description = $shipping_method->get_option('enable_custom_cod_description');
                $custom_cod_description = $shipping_method->get_option('custom_cod_description');

                if ('yes' === $enable_custom_cod_description && !empty($custom_cod_description)) {
                    return $custom_cod_description;
                }
            }
        }
    }

    return $description;
}

// Refresh the checkout page when the payment method changes
add_action('woocommerce_review_order_before_payment', 'boxnow_add_cod_payment_refresh_script');
function boxnow_add_cod_payment_refresh_script()
{
    ?>
    <script>
        jQuery(document.body).on('change', 'input[name="payment_method"]', function() {
            jQuery('body').trigger('update_checkout');
        });
    </script>
    <?php
}

// Add the custom shipping method to WooCommerce
add_filter('woocommerce_shipping_methods', 'boxnow_add_box_now_delivery_shipping_method');

/**
 * Add the custom shipping method to WooCommerce.
 *
 * @param array $methods Existing shipping methods.
 * @return array Updated shipping methods.
 */
function boxnow_add_box_now_delivery_shipping_method($methods)
{
    $methods['box_now_delivery'] = 'Box_Now_Delivery_Shipping_Method';
    return $methods;
}
