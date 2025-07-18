(function ($) {
    'use strict';

    // Debug logging
    function debugLog(message, data = null) {
        if (typeof console !== 'undefined' && console.log) {
            console.log('[BOX NOW DEBUG]: ' + message, data || '');
        }
    }

    /**
     * Add the Box Now Delivery button or embedded map.
     */
    function addButton() {
        if (
            $("#box_now_delivery_button").length === 0 &&
            boxNowDeliverySettings.displayMode === "popup"
        ) {
            var buttonText = boxNowDeliverySettings.buttonText || "Pick a locker";

            $('label[for="shipping_method_0_box_now_delivery"]').after(
                '<button type="button" id="box_now_delivery_button" style="display:none;">' +
                buttonText +
                "</button>"
            );

            attachButtonClickListener();
        } else if (boxNowDeliverySettings.displayMode === "embedded") {
            if ($("#box_now_delivery_embedded_map").length === 0) {
                $('label[for="shipping_method_0_box_now_delivery"]').after(
                    '<div id="box_now_delivery_embedded_map" style="display:none;"></div>'
                );
            }
            embedMap();
        }
        applyButtonStyles();
    }

    /**
     * Apply the custom styles for the Box Now Delivery button.
     */
    function applyButtonStyles() {
        var buttonColor = boxNowDeliverySettings.buttonColor || "#6CD04E";

        // Remove existing styles to avoid duplicates
        $("#box-now-delivery-button-styles").remove();

        var styleBlock = `
            <style id="box-now-delivery-button-styles">
                #box_now_delivery_button {
                    background-color: ${buttonColor} !important;
                    color: #fff !important;
                }
            </style>
        `;

        $("head").append(styleBlock);
    }

    /**
     * Attach click event listener to the Box Now Delivery button.
     */
    function attachButtonClickListener() {
        $("#box_now_delivery_button").off('click').on("click", function (event) {
            event.preventDefault();
            debugLog('Button clicked, creating popup map');
            createPopupMap();
        });
    }

    /**
     * Get user's selected country for widget URL
     */
    function GetUserCountry() {
        let selectedCountry;

        // Check shipping address first if "ship to different address" is checked
        if ($('#ship-to-different-address-checkbox').is(":checked")) {
            if ($('select[name="shipping_country"]').length) {
                selectedCountry = $('select[name="shipping_country"]').val();
            } else if ($('input[name="shipping_country"]').length) {
                selectedCountry = $('input[name="shipping_country"]').val();
            }
        } else {
            // Otherwise use billing address
            if ($('select[name="billing_country"]').length) {
                selectedCountry = $('select[name="billing_country"]').val();
            } else if ($('input[name="billing_country"]').length) {
                selectedCountry = $('input[name="billing_country"]').val();
            }
        }

        debugLog('Selected country:', selectedCountry);
        return selectedCountry;
    }

    /**
     * Embed the map to the page.
     */
    function embedMap() {
        var iframe = $("#box_now_delivery_embedded_map iframe");

        if (iframe.length === 0) {
            iframe = createEmbeddedIframe();

            var lockerDetailsContainer = $("<div>", {
                id: "box_now_selected_locker_details",
                css: {
                    display: "none",
                    marginTop: "10px",
                },
            });

            var lockerInfoContainer = $("<div>", {
                id: "locker_info_container",
            });

            $("#box_now_delivery_embedded_map")
                .css({
                    position: "relative",
                    width: "100%",
                    height: "80vh",
                    overflow: "auto"
                })
                .empty()
                .append(iframe)
                .append(lockerInfoContainer.append(lockerDetailsContainer));

            // Add the load event listener to the iframe
            iframe.on("load", function () {
                debugLog('Embedded iframe loaded');
                setupMessageListener();
            });
        }

        var selected = $('input[name^="shipping_method"]:checked, input[name^="shipping_method"][type="hidden"]');

        if (selected.length && selected.val().includes('box_now_delivery')) {
            $("#box_now_delivery_embedded_map").show();
            debugLog('Embedded map shown');
        } else {
            $("#box_now_delivery_embedded_map").hide();
            debugLog('Embedded map hidden');
        }
    }

    /**
     * Create overlay for popup
     */
    function createOverlay() {
        var overlay = $("<div>", {
            id: "box_now_delivery_overlay",
            css: {
                position: "fixed",
                top: 0,
                left: 0,
                width: "100%",
                height: "100%",
                backgroundColor: "rgba(0, 0, 0, 0.5)",
                zIndex: 9998,
            },
        });

        overlay.on("click", function () {
            closePopup();
        });

        $("body").append(overlay);
    }

    /**
     * Close popup and cleanup
     */
    function closePopup() {
        $("#box_now_delivery_overlay").remove();
        $("iframe[src*='widget-v5.boxnow']").remove();
        debugLog('Popup closed');
    }

    /**
     * Create an iframe for the popup map.
     */
    function createPopupMap() {
        let gpsOption = boxNowDeliverySettings.gps_option;
        let partnerId = boxNowDeliverySettings.partnerId;
        let postalCode = $('input[name="billing_postcode"]').val();
        let country = GetUserCountry();

        let src = getWidgetUrl(country, partnerId, gpsOption, postalCode, true);

        let iframe = $("<iframe>", {
            src: src,
            css: {
                position: "fixed",
                top: "50%",
                left: "50%",
                width: "80%",
                height: "80%",
                border: 0,
                borderRadius: "20px",
                transform: "translate(-50%, -50%)",
                zIndex: 9999,
            }
        });

        setupMessageListener();
        createOverlay();
        $("body").append(iframe);
        debugLog('Popup created with URL:', src);
    }

    /**
     * Get widget URL based on country and settings
     */
    function getWidgetUrl(country, partnerId, gpsOption, postalCode, isPopup = false) {
        let baseUrl;
        
        if (country === "CY") {
            baseUrl = "https://widget-v5.boxnow.cy";
        } else if (country === "BG") {
            baseUrl = "https://widget-v5.boxnow.bg";
        } else if (country === "HR") {
            baseUrl = "https://widget-v5.boxnow.hr";
        } else {
            baseUrl = "https://widget-v5.boxnow.gr";
        }

        if (isPopup) {
            baseUrl += "/popup.html";
        }

        let params = [];
        if (partnerId) {
            params.push("partnerId=" + encodeURIComponent(partnerId));
        }

        if (gpsOption === "off") {
            params.push("gps=no");
            if (postalCode) {
                params.push("zip=" + encodeURIComponent(postalCode));
            }
        } else {
            params.push("gps=yes");
        }

        if (isPopup) {
            params.push("autoclose=yes");
            params.push("autoselect=no");
        }

        if (params.length > 0) {
            baseUrl += "?" + params.join("&");
        }

        return baseUrl;
    }

    /**
     * Create an iframe for the embedded map.
     */
    function createEmbeddedIframe() {
        let gpsOption = boxNowDeliverySettings.gps_option;
        let partnerId = boxNowDeliverySettings.partnerId;
        let postalCode = $('input[name="billing_postcode"]').val();
        let country = GetUserCountry();

        let src = getWidgetUrl(country, partnerId, gpsOption, postalCode, false);

        return $("<iframe>", {
            src: src,
            css: {
                width: "100%",
                height: "70%",
                border: 0,
            },
        });
    }

    /**
     * Setup message listener for iframe communication
     */
    function setupMessageListener() {
        // Remove existing listener to avoid duplicates
        $(window).off('message.boxnow');
        
        $(window).on('message.boxnow', function(event) {
            var originalEvent = event.originalEvent;
            debugLog('Message received:', originalEvent.data);
            
            if (typeof originalEvent.data === 'object' && originalEvent.data !== null) {
                if (typeof originalEvent.data.boxnowClose !== "undefined") {
                    debugLog('Close message received');
                    closePopup();
                } else if (originalEvent.data.boxnowLockerId) {
                    debugLog('Locker selected:', originalEvent.data);
                    updateLockerDetailsContainer(originalEvent.data);
                    showSelectedLockerDetailsFromLocalStorage();
                }
            } else if (originalEvent.data === "closeIframe") {
                debugLog('Close iframe message received');
                closePopup();
            }
        });
    }

    /**
     * Update the locker details container with selected locker data.
     *
     * @param {object} lockerData Locker data object.
     */
    function updateLockerDetailsContainer(lockerData) {
        debugLog('Updating locker details:', lockerData);
        
        // Check if locker data is not undefined
        if (
            lockerData.boxnowLockerId === undefined ||
            lockerData.boxnowLockerAddressLine1 === undefined ||
            lockerData.boxnowLockerPostalCode === undefined ||
            lockerData.boxnowLockerName === undefined
        ) {
            debugLog('Invalid locker data received');
            return;
        }

        // Get the selected locker details
        var locker_id = lockerData.boxnowLockerId;
        var locker_address = lockerData.boxnowLockerAddressLine1;
        var locker_postal_code = lockerData.boxnowLockerPostalCode;
        var locker_name = lockerData.boxnowLockerName;

        // Store in localStorage
        localStorage.setItem("box_now_selected_locker", JSON.stringify(lockerData));

        // Ensure the locker details container exists
        if ($("#box_now_selected_locker_details").length === 0) {
            if ($("#box_now_delivery_button").length > 0) {
                $("#box_now_delivery_button").after(
                    '<div id="box_now_selected_locker_details" style="display:none;"></div>'
                );
            } else if ($("#box_now_delivery_embedded_map").length > 0) {
                $("#box_now_delivery_embedded_map").after(
                    '<div id="box_now_selected_locker_details" style="display:none;"></div>'
                );
            }
        }

        // Update hidden input field for locker ID
        updateHiddenField("_boxnow_locker_id", locker_id);

        // Update the locker details container
        var language = document.documentElement.lang || "el";

        var englishContent = `
            <div style="font-family: Verdana, Arial, sans-serif; font-weight: 300; margin-top: -7px;">
                <p style="margin: 1px 0px; color: #61bb46; font-weight: 400; height: 25px;"><b>Selected Locker</b></p>
                <p style="margin: 1px 0px; font-size: 13px; line-height: 20px; height: 20px;">${locker_name}</p>
                <p style="margin: 1px 0px; font-size: 13px; line-height: 20px; height: 20px;">${locker_address}</p>
                <p style="margin: 1px 0px; font-size: 13px; line-height: 20px; height: 20px;">${locker_postal_code}</p>
            </div>`;

        var greekContent = `
            <div style="font-family: Verdana, Arial, sans-serif; font-weight: 300; margin-top: -7px;">
                <p style="margin: 1px 0px; color: #61bb46; font-weight: 400; height: 25px;"><b>Επιλεγμένο locker</b></p>
                <p style="margin: 1px 0px; font-size: 13px; line-height: 20px; height: 20px;">${locker_name}</p>
                <p style="margin: 1px 0px; font-size: 13px; line-height: 20px; height: 20px;">${locker_address}</p>
                <p style="margin: 1px 0px; font-size: 13px; line-height: 20px; height: 20px;">${locker_postal_code}</p>
            </div>`;

        var content = language === "el" ? greekContent : englishContent;

        $("#box_now_selected_locker_details").html(content).show();

        // Store locker information in hidden input
        updateHiddenField("box_now_selected_locker_input", JSON.stringify(lockerData));

        // Send locker ID to server via AJAX
        sendLockerToServer(locker_id);

        // Close popup if it's open
        if (boxNowDeliverySettings.displayMode === "popup") {
            closePopup();
        }

        debugLog('Locker details updated successfully');
    }

    /**
     * Update or create hidden input field
     */
    function updateHiddenField(fieldName, value) {
        var $field = $("#" + fieldName);
        
        if ($field.length === 0) {
            $field = $("<input>", {
                type: "hidden",
                id: fieldName,
                name: fieldName,
                value: value
            });
            $("#box_now_selected_locker_details").append($field);
        } else {
            $field.val(value);
        }
    }

    /**
     * Send locker ID to server via AJAX
     */
    function sendLockerToServer(lockerId) {
        if (!lockerId) {
            return;
        }

        $.ajax({
            url: boxNowDeliverySettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'boxnow_set_locker',
                locker_id: lockerId,
                nonce: boxNowDeliverySettings.nonce
            },
            success: function(response) {
                debugLog('Locker sent to server successfully:', response);
            },
            error: function(xhr, status, error) {
                debugLog('Error sending locker to server:', error);
            }
        });
    }

    /**
     * Show the selected locker details from local storage.
     */
    function showSelectedLockerDetailsFromLocalStorage() {
        var lockerData = localStorage.getItem("box_now_selected_locker");

        if (lockerData) {
            try {
                var parsedData = JSON.parse(lockerData);
                debugLog('Showing locker from localStorage:', parsedData);
                updateLockerDetailsContainer(parsedData);
            } catch (e) {
                debugLog('Error parsing localStorage data:', e);
                localStorage.removeItem("box_now_selected_locker");
            }
        }
    }

    /**
     * Toggle the Box Now Delivery button or embedded map based on the selected shipping method.
     */
    function toggleBoxNowDelivery() {
        if (boxNowDeliverySettings.displayMode === "popup") {
            toggleBoxNowDeliveryButton();
        } else if (boxNowDeliverySettings.displayMode === "embedded") {
            embedMap();
        }
    }

    /**
     * Toggle the Box Now Delivery button visibility based on the selected shipping method.
     */
    function toggleBoxNowDeliveryButton() {
        var boxButton = $("#box_now_delivery_button");

        if (boxButton.length === 0) {
            return;
        }

        // Set the background color
        boxButton.css("background-color", boxNowDeliverySettings.buttonColor);

        var isBoxNowSelected = false;
        
        // Check radio buttons
        if ($("#shipping_method_0_box_now_delivery").is(":checked")) {
            isBoxNowSelected = true;
        }
        
        // Check hidden inputs
        var hiddenShippingMethod = $('input[type="hidden"][name="shipping_method[0]"]');
        if (hiddenShippingMethod.length && hiddenShippingMethod.val() === "box_now_delivery") {
            isBoxNowSelected = true;
        }

        if (isBoxNowSelected) {
            boxButton.show();
            debugLog('Box Now button shown');
        } else {
            boxButton.hide();
            debugLog('Box Now button hidden');
        }
    }

    /**
     * Initialize the script.
     */
    function init() {
        debugLog('Initializing Box Now Delivery script');
        addButton();
        toggleBoxNowDelivery();
        setupMessageListener();

        // Show selected locker details if Box Now is selected
        var isBoxNowSelected = $("#shipping_method_0_box_now_delivery").is(":checked") || 
                               $('input[type="hidden"][name="shipping_method[0]"]').val() === "box_now_delivery";
        
        if (isBoxNowSelected) {
            showSelectedLockerDetailsFromLocalStorage();
        }
    }

    /**
     * Remove the selected locker details from local storage and hide the locker details container
     */
    function clearSelectedLockerDetails() {
        localStorage.removeItem("box_now_selected_locker");
        $("#box_now_selected_locker_details").hide().empty();
        debugLog('Selected locker details cleared');
    }

    /**
     * Add validation for order placement to ensure locker selection.
     */
    function addOrderValidation() {
        $(document.body).on("click", "#place_order", function (event) {
            var isBoxNowSelected = false;
            var selectedShippingMethod = $('input[type="radio"][name="shipping_method[0]"]:checked').val();
            
            if (!selectedShippingMethod) {
                selectedShippingMethod = $('input[type="hidden"][name="shipping_method[0]"]').val();
            }
            
            if (selectedShippingMethod === "box_now_delivery") {
                isBoxNowSelected = true;
            }

            if (isBoxNowSelected) {
                var lockerData = localStorage.getItem("box_now_selected_locker");
                var lockerIdField = $("#_boxnow_locker_id").val();
                
                if (!lockerData && !lockerIdField) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    
                    var message = boxNowDeliverySettings.lockerNotSelectedMessage || "Please select a locker first!";
                    alert(message);
                    
                    debugLog('Order placement prevented - no locker selected');
                    return false;
                }
            }
        });
    }

    /**
     * Handle country change - clear selected locker
     */
    function handleCountryChange() {
        $('body').on('change', '#billing_country, #shipping_country', function () {
            debugLog('Country changed, clearing selected locker');
            clearSelectedLockerDetails();
        });
    }

    /**
     * Handle checkout updates
     */
    function handleCheckoutUpdates() {
        $(document.body).on("updated_checkout", function () {
            debugLog('Checkout updated, reinitializing');
            init();
        });

        // Handle shipping method changes
        $(document.body).on("change", 'input[type="radio"][name="shipping_method[0]"]', function() {
            debugLog('Shipping method changed to:', $(this).val());
            toggleBoxNowDelivery();
        });
    }

    // Document ready event
    $(document).ready(function () {
        debugLog('Document ready, starting initialization');
        
        // Initialize the plugin
        init();
        
        // Setup event handlers
        addOrderValidation();
        handleCountryChange();
        handleCheckoutUpdates();
        
        debugLog('Box Now Delivery script fully initialized');
    });

})(jQuery);