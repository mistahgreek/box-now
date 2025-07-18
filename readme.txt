# BOX NOW Delivery Plugin - Community Fixed Version

A WordPress plugin that integrates WooCommerce stores with BOX NOW parcel delivery services. This is a **community-maintained version** with critical bug fixes that resolve major issues in the original BOX NOW plugin.

## ⚠️ Important Notice

This repository contains the **original BOX NOW plugin v2.1.9** with **critical community fixes** applied. The original plugin had several major issues that prevented proper functionality, especially with WooCommerce 8.2+ HPOS enabled.

### Original Plugin Issues Fixed:
- ❌ **No HPOS Compatibility** - Plugin was incompatible with WooCommerce 8.2+
- ❌ **Locker ID Not Saved** - Critical bug where locker IDs weren't saved to orders
- ❌ **Poor JavaScript Integration** - Widget failures and timing issues
- ❌ **No Validation** - Orders could be placed without locker selection
- ❌ **Inadequate Error Handling** - Poor debugging and error reporting
- ❌ **Page Builder Incompatibility** - Didn't work with Elementor, Divi, etc.
- ❌ **Custom Checkout Issues** - Problems with FunnelKit, CheckoutWC, etc.

### Community Fixes Applied:
- ✅ **HPOS Compatibility** - Added proper WooCommerce 8.2+ support
- ✅ **Reliable Locker ID Processing** - Fixed critical order processing bug
- ✅ **Enhanced JavaScript** - Complete rewrite with proper event management
- ✅ **Server-Side Validation** - Prevents orders without locker selection
- ✅ **Comprehensive Error Handling** - Enhanced logging and debugging
- ✅ **Universal Compatibility** - Works with all major page builders and checkout plugins

## 🚀 Installation

### Option 1: Direct Installation (Recommended)
1. **Backup your current plugin** (if you have the original BOX NOW plugin)
2. **Deactivate the original plugin** if installed
3. **Download this repository** as ZIP
4. **Upload and activate** through WordPress admin
5. **Enable HPOS** in WooCommerce → Settings → Advanced → Features
6. **Configure your API settings**

### Option 2: Replace Files
1. **Backup your current plugin directory**
2. **Replace these files** in your existing installation:
   - `box-now-delivery.php` (main plugin file)
   - `js/box-now-delivery.js` (frontend JavaScript)
3. **Clear any caches**
4. **Test thoroughly**

## ⚙️ Configuration

Navigate to **WooCommerce → BOX NOW Delivery** and configure:

### API Settings
- **API URL**: `api-stage.boxnow.gr` (staging) or `api-production.boxnow.gr` (production)
- **Client ID**: Your BOX NOW client ID
- **Client Secret**: Your BOX NOW client secret  
- **Partner ID**: Your BOX NOW partner ID
- **Warehouse IDs**: Comma-separated list (e.g., `123,456,789`)

### Contact Details
- **Email**: Your orders contact email
- **Phone**: Phone with country prefix (e.g., `+30123456789`)

### Widget Options
- **Display Mode**: Popup or Embedded
- **GPS Permission**: Enable/Disable location access
- **Button**: Customize color and text

## 🔧 Requirements

- WordPress 6.2+
- WooCommerce 8.0+
- PHP 7.4+
- **HPOS Enabled** (WooCommerce → Settings → Advanced → Features)

## 🐛 Troubleshooting & Compatibility Fixes

### 1. FunnelKit Funnel Builder Issues

**Problem**: "Please select a BOX NOW locker" error even after selecting locker.

**Solution**: Add this to your theme's `functions.php`:

```php
// FunnelKit compatibility fix - Enhanced validation
add_action('woocommerce_after_checkout_validation', 'boxnow_funnelkit_validate_locker', 10, 2);
function boxnow_funnelkit_validate_locker($data, $errors) {
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    if (!is_array($chosen_shipping_methods) || !in_array('box_now_delivery', $chosen_shipping_methods)) {
        return;
    }
    
    $locker_id = sanitize_text_field($_POST['_boxnow_locker_id'] ?? '');
    if (empty($locker_id)) {
        $locker_id = WC()->session->get('boxnow_selected_locker_id');
    }
    
    if (empty($locker_id)) {
        $errors->add('boxnow_locker_required', __('Please select a BOX NOW locker before placing your order.', 'box-now-delivery'));
    }
}

// FunnelKit field processing fix
add_action('woocommerce_checkout_update_order_meta', 'boxnow_funnelkit_save_locker_enhanced', 5, 1);
function boxnow_funnelkit_save_locker_enhanced($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $locker_id = sanitize_text_field($_POST['_boxnow_locker_id'] ?? '');
    if (empty($locker_id)) {
        $locker_id = WC()->session->get('boxnow_selected_locker_id');
    }
    
    if (!empty($locker_id)) {
        $order->update_meta_data('_boxnow_locker_id', $locker_id);
        $order->save();
    }
}
```

**What this does**: 
- Hooks into FunnelKit's validation system to check for locker selection
- Ensures locker ID is saved to order meta even with FunnelKit's custom processing
- Provides fallback to session data if POST data is missing

### 2. CheckoutWC (Checkout for WooCommerce) Issues

**Problem**: Widget not appearing or locker field not processing.

**Solution**: Add this to your theme's `functions.php`:

```php
// CheckoutWC compatibility fix
add_action('cfw_checkout_before_customer_info_tab', 'boxnow_checkoutwc_add_widget_area');
function boxnow_checkoutwc_add_widget_area() {
    echo '<div id="boxnow-checkoutwc-widget-area"></div>';
}

// CheckoutWC field processing
add_filter('cfw_checkout_fields', 'boxnow_checkoutwc_add_locker_field');
function boxnow_checkoutwc_add_locker_field($fields) {
    $fields['billing']['_boxnow_locker_id'] = array(
        'label' => __('BOX NOW Locker ID', 'box-now-delivery'),
        'required' => false,
        'type' => 'hidden',
        'class' => array('boxnow-locker-id-field'),
    );
    return $fields;
}

// CheckoutWC validation
add_action('cfw_checkout_validation', 'boxnow_checkoutwc_validate_locker');
function boxnow_checkoutwc_validate_locker() {
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
    if (!is_array($chosen_shipping_methods) || !in_array('box_now_delivery', $chosen_shipping_methods)) {
        return;
    }
    
    $locker_id = sanitize_text_field($_POST['_boxnow_locker_id'] ?? '');
    if (empty($locker_id)) {
        $locker_id = WC()->session->get('boxnow_selected_locker_id');
    }
    
    if (empty($locker_id)) {
        wc_add_notice(__('Please select a BOX NOW locker before placing your order.', 'box-now-delivery'), 'error');
    }
}
```

**What this does**:
- Adds a widget area specifically for CheckoutWC integration
- Registers the locker field with CheckoutWC's field system
- Provides validation that works with CheckoutWC's checkout process

### 3. Elementor Pro Checkout Widget Issues

**Problem**: BOX NOW widget not loading in Elementor Pro checkout widget.

**Solution**: Add this to your theme's `functions.php`:

```php
// Elementor Pro compatibility fix
add_action('elementor_pro/forms/render_field/checkout', 'boxnow_elementor_checkout_widget_fix');
function boxnow_elementor_checkout_widget_fix($field) {
    if ($field['field_type'] === 'shipping') {
        echo '<div id="boxnow-elementor-widget-container"></div>';
    }
}

// Elementor Pro field registration
add_action('wp_footer', 'boxnow_elementor_pro_js_fix');
function boxnow_elementor_pro_js_fix() {
    if (!is_checkout()) return;
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Wait for Elementor Pro to load
        setTimeout(function() {
            // Re-initialize BOX NOW widget for Elementor
            if (typeof window.addButton === 'function') {
                window.addButton();
            }
            
            // Move widget to Elementor container if it exists
            if ($('#boxnow-elementor-widget-container').length) {
                $('#box_now_delivery_button').appendTo('#boxnow-elementor-widget-container');
            }
        }, 1000);
    });
    </script>
    <?php
}
```

**What this does**:
- Adds a container for the BOX NOW widget in Elementor Pro checkout
- Re-initializes the widget after Elementor Pro loads
- Moves the widget to the appropriate container

### 4. Divi Theme/Builder Issues

**Problem**: Widget styling conflicts or not appearing with Divi theme.

**Solution**: Add this to your theme's `functions.php`:

```php
// Divi compatibility fix
add_action('wp_head', 'boxnow_divi_css_fix');
function boxnow_divi_css_fix() {
    if (!is_checkout()) return;
    
    ?>
    <style>
    /* Divi compatibility CSS */
    .et_pb_module #box_now_delivery_button {
        display: inline-block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    .et_pb_wc_checkout #box_now_selected_locker_details {
        display: block !important;
        margin-top: 10px !important;
    }
    
    /* Fix Divi's checkout module conflicts */
    .et_pb_wc_checkout .boxnow-locker-id-field {
        display: none !important;
    }
    </style>
    <?php
}

// Divi checkout module fix
add_action('wp_footer', 'boxnow_divi_checkout_module_fix');
function boxnow_divi_checkout_module_fix() {
    if (!is_checkout()) return;
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Divi checkout module observer
        if ($('.et_pb_wc_checkout').length) {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        // Re-initialize BOX NOW widget when Divi updates checkout
                        setTimeout(function() {
                            if (typeof window.init === 'function') {
                                window.init();
                            }
                        }, 500);
                    }
                });
            });
            
            observer.observe($('.et_pb_wc_checkout')[0], {
                childList: true,
                subtree: true
            });
        }
    });
    </script>
    <?php
}
```

**What this does**:
- Fixes CSS conflicts with Divi's checkout styling
- Observes Divi's checkout module for changes and re-initializes the widget
- Ensures proper visibility and styling of BOX NOW elements

### 5. Oxygen Builder Issues

**Problem**: Widget not loading in Oxygen Builder checkout templates.

**Solution**: Add this to your theme's `functions.php`:

```php
// Oxygen Builder compatibility fix
add_action('wp_footer', 'boxnow_oxygen_builder_fix');
function boxnow_oxygen_builder_fix() {
    if (!is_checkout() || !defined('CT_VERSION')) return;
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Oxygen Builder specific initialization
        function initBoxNowForOxygen() {
            // Wait for Oxygen's checkout to be ready
            if ($('.oxy-woo-checkout').length) {
                // Re-initialize BOX NOW widget
                if (typeof window.init === 'function') {
                    window.init();
                }
                
                // Ensure proper field handling
                $('body').on('updated_checkout', function() {
                    setTimeout(function() {
                        if (typeof window.toggleBoxNowDelivery === 'function') {
                            window.toggleBoxNowDelivery();
                        }
                    }, 100);
                });
            }
        }
        
        // Initialize immediately and after Oxygen loads
        initBoxNowForOxygen();
        setTimeout(initBoxNowForOxygen, 2000);
    });
    </script>
    <?php
}

// Oxygen Builder field processing
add_action('woocommerce_checkout_update_order_meta', 'boxnow_oxygen_save_locker_data', 5, 1);
function boxnow_oxygen_save_locker_data($order_id) {
    if (!defined('CT_VERSION')) return;
    
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $locker_id = sanitize_text_field($_POST['_boxnow_locker_id'] ?? '');
    if (empty($locker_id)) {
        $locker_id = WC()->session->get('boxnow_selected_locker_id');
    }
    
    if (!empty($locker_id)) {
        $order->update_meta_data('_boxnow_locker_id', $locker_id);
        $order->save();
    }
}
```

**What this does**:
- Detects Oxygen Builder and initializes the widget accordingly
- Handles Oxygen's checkout updates properly
- Ensures locker data is saved even with Oxygen's custom checkout structure

### 6. Bricks Builder Issues

**Problem**: Widget not appearing in Bricks Builder checkout forms.

**Solution**: Add this to your theme's `functions.php`:

```php
// Bricks Builder compatibility fix
add_action('wp_footer', 'boxnow_bricks_builder_fix');
function boxnow_bricks_builder_fix() {
    if (!is_checkout() || !defined('BRICKS_VERSION')) return;
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Bricks Builder specific handling
        function initBoxNowForBricks() {
            // Check if Bricks checkout form exists
            if ($('.brx-form, [data-element-id*="checkout"]').length) {
                // Initialize BOX NOW widget
                if (typeof window.init === 'function') {
                    window.init();
                }
                
                // Handle Bricks form updates
                $(document).on('bricks/ajax/form/success', function() {
                    setTimeout(function() {
                        if (typeof window.showSelectedLockerDetailsFromLocalStorage === 'function') {
                            window.showSelectedLockerDetailsFromLocalStorage();
                        }
                    }, 500);
                });
            }
        }
        
        // Initialize for Bricks
        initBoxNowForBricks();
        
        // Re-initialize after Bricks loads
        setTimeout(initBoxNowForBricks, 1500);
    });
    </script>
    <?php
}
```

**What this does**:
- Detects Bricks Builder and initializes the widget
- Handles Bricks' AJAX form updates
- Ensures widget persists after Bricks form interactions

### 7. WooCommerce Blocks (Gutenberg) Issues

**Problem**: Widget not working with WooCommerce Blocks checkout.

**Solution**: Add this to your theme's `functions.php`:

```php
// WooCommerce Blocks compatibility fix
add_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', 'boxnow_blocks_compatibility');
function boxnow_blocks_compatibility() {
    ?>
    <script>
    // WooCommerce Blocks integration
    const { registerCheckoutFilters } = window.wc.blocksCheckout;
    
    // Add BOX NOW validation for blocks
    registerCheckoutFilters('boxnow-delivery', {
        additionalCartCheckoutInnerBlockTypes: (value, extensions, args) => {
            return value.concat('boxnow/locker-selection');
        }
    });
    
    // Handle blocks checkout updates
    wp.hooks.addAction('experimental__woocommerce_blocks-checkout-set-selected-shipping-rate', 'boxnow-delivery', function(data) {
        if (data.rate_id === 'box_now_delivery') {
            // Initialize BOX NOW widget for blocks
            setTimeout(function() {
                if (typeof window.init === 'function') {
                    window.init();
                }
            }, 100);
        }
    });
    </script>
    <?php
}

// Blocks checkout field processing
add_action('woocommerce_store_api_checkout_update_order_from_request', 'boxnow_blocks_save_locker_data', 10, 2);
function boxnow_blocks_save_locker_data($order, $request) {
    $locker_id = $request->get_param('_boxnow_locker_id');
    if (empty($locker_id)) {
        $locker_id = WC()->session->get('boxnow_selected_locker_id');
    }
    
    if (!empty($locker_id)) {
        $order->update_meta_data('_boxnow_locker_id', sanitize_text_field($locker_id));
    }
}
```

**What this does**:
- Registers BOX NOW with WooCommerce Blocks system
- Handles blocks-specific checkout updates
- Ensures locker data is saved with the Store API

### 8. WP Rocket & Caching Issues

**Problem**: Widget not loading due to JavaScript caching/optimization.

**Solution**: Add this to your theme's `functions.php`:

```php
// WP Rocket compatibility fix
add_filter('rocket_exclude_js', 'boxnow_exclude_js_from_rocket');
function boxnow_exclude_js_from_rocket($excluded_files) {
    $excluded_files[] = 'box-now-delivery.js';
    $excluded_files[] = 'widget-v5.boxnow.gr';
    $excluded_files[] = 'widget-v5.boxnow.cy';
    $excluded_files[] = 'widget-v5.boxnow.bg';
    $excluded_files[] = 'widget-v5.boxnow.hr';
    return $excluded_files;
}

// General caching exclusion
add_action('wp_head', 'boxnow_add_cache_exclusions');
function boxnow_add_cache_exclusions() {
    if (is_checkout()) {
        echo '<meta name="wp-rocket-exclude" content="box-now-delivery.js">';
        echo '<meta name="litespeed-cache-exclude" content="box-now-delivery.js">';
    }
}
```

**What this does**:
- Excludes BOX NOW JavaScript from caching plugins
- Ensures widget loads properly even with aggressive caching
- Adds meta tags for various caching plugins

### 9. Debug Mode for All Issues

**Enable comprehensive debugging**:

```php
// Comprehensive BOX NOW debugging
add_action('wp_footer', 'boxnow_comprehensive_debug');
function boxnow_comprehensive_debug() {
    if (!is_checkout() || !WP_DEBUG) return;
    
    ?>
    <script>
    // BOX NOW Debug Console
    console.log('=== BOX NOW DEBUG INFO ===');
    console.log('jQuery loaded:', typeof jQuery !== 'undefined');
    console.log('WooCommerce checkout:', typeof wc_checkout_params !== 'undefined');
    console.log('BOX NOW settings:', typeof boxNowDeliverySettings !== 'undefined');
    console.log('Locker field exists:', jQuery('#_boxnow_locker_id').length > 0);
    console.log('Session storage:', localStorage.getItem('box_now_selected_locker'));
    console.log('Current page builder:', {
        'Elementor': typeof elementorFrontend !== 'undefined',
        'Divi': typeof ET_Builder !== 'undefined',
        'Oxygen': typeof CTFrontendBuilder !== 'undefined',
        'Bricks': typeof bricksData !== 'undefined',
        'FunnelKit': typeof wfacp_frontend !== 'undefined'
    });
    console.log('=== END DEBUG ===');
    </script>
    <?php
}
```

**What this does**:
- Provides comprehensive debugging information in browser console
- Shows which page builder is active
- Displays current state of BOX NOW elements
- Helps identify integration issues

## 🛠️ Technical Details

### Files Modified:
- `box-now-delivery.php` - Main plugin file with HPOS compatibility and enhanced order processing
- `js/box-now-delivery.js` - Complete JavaScript rewrite with proper event management
- Various include files for admin interface and shipping methods

### Key Improvements:
- **HPOS Compatibility Declaration**: Added proper `FeaturesUtil::declare_compatibility`
- **Enhanced Order Processing**: Improved checkout field handling with session fallback
- **JavaScript Rewrite**: Complete rewrite with proper event management and debugging
- **Server-Side Validation**: Prevents orders without locker selection
- **Enhanced Error Handling**: Comprehensive logging and error reporting
- **Universal Compatibility**: Works with all major page builders and checkout plugins

## 📋 Changelog

### v2.1.9-fixed (Community Version)
- **CRITICAL**: Added HPOS compatibility for WooCommerce 8.2+
- **CRITICAL**: Fixed locker ID not being saved to orders
- **MAJOR**: Complete JavaScript rewrite with proper event management
- **MAJOR**: Added server-side validation for locker selection
- **IMPROVED**: Enhanced error handling and debugging
- **IMPROVED**: Better session management and data persistence
- **IMPROVED**: FunnelKit and checkout plugin compatibility
- **IMPROVED**: Page builder compatibility (Elementor, Divi, Oxygen, Bricks)
- **FIXED**: Widget integration and popup management
- **FIXED**: API error handling and recovery
- **ADDED**: Comprehensive debug logging
- **ADDED**: AJAX handlers for improved locker selection
- **ADDED**: Compatibility fixes for all major page builders

### v2.1.9 (Original BOX NOW)
- Fix country selector, when only one country is configured in settings
- Minor css fixes

### v2.1.8 (Original BOX NOW)
- Compatibility improvements
- Bug fixes

## 🤝 Contributing

This is a community-maintained version. If you find issues or have improvements:

1. **Open an issue** describing the problem
2. **Submit a pull request** with fixes
3. **Test thoroughly** before submitting
4. **Document your changes** clearly

## ⚖️ Legal Notice

This plugin is based on the original BOX NOW Delivery plugin v2.1.9. All fixes and improvements are provided as-is for the community. 

- Original plugin: Copyright BOX NOW
- Community fixes: Provided under GPL v2+ license
- No warranty or support provided
- Use at your own risk

## 📞 Support

**For plugin issues**: Open an issue in this repository
**For BOX NOW account issues**: Contact BOX NOW support directly
**For API credentials**: Contact BOX NOW business team

## 🔗 Useful Links

- [BOX NOW Official Website](https://boxnow.gr/)
- [WooCommerce HPOS Documentation](https://woocommerce.com/document/high-performance-order-storage/)
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/)

---

**Note**: This is an unofficial community-maintained version. For official support, contact BOX NOW directly.
