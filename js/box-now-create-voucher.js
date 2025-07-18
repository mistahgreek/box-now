document.addEventListener("DOMContentLoaded", function () {
    const createVouchersEnabled = document.getElementById(
        "create_vouchers_enabled"
    );

    const createVouchersBtnSmall = document.getElementById(
        "box_now_create_voucher_small"
    );
    const createVouchersBtnMedium = document.getElementById(
        "box_now_create_voucher_medium"
    );
    const createVouchersBtnLarge = document.getElementById(
        "box_now_create_voucher_large"
    );

    const buttons = [
        createVouchersBtnSmall,
        createVouchersBtnMedium,
        createVouchersBtnLarge,
    ];
    const sizes = ["small", "medium", "large"];

    // Create the sizeMapping object
    const sizeMapping = {
        small: 1,
        medium: 2,
        large: 3,
    };

    for (let i = 0; i < buttons.length; i++) {
        let button = buttons[i];
        let size = sizes[i];

        // Check if button exists and enabled
        if (!button || createVouchersEnabled.value !== "true") {
            if (button) button.disabled = true;
            continue;
        }

        button.disabled = false;

        button.addEventListener("click", function () {
            const order_id = document.getElementById("box_now_order_id").value;
            const voucher_quantity = document.getElementById(
                "box_now_voucher_code"
            ).value;
            const max_vouchers = parseInt(
                document.getElementById("max_vouchers").value,
                10
            );

            if (!order_id || !voucher_quantity) {
                alert("Please provide the required data.");
                return;
            }

            if (voucher_quantity > max_vouchers) {
                alert(
                    "The number of vouchers you want to create is larger than the max vouchers number."
                );
                return;
            }

            // Disable the button immediately after it is clicked
            button.disabled = true;

            // Make an AJAX call to your existing function
            const data = {
                action: "create_box_now_vouchers",
                order_id: order_id,
                voucher_quantity: voucher_quantity,
                compartment_size: sizeMapping[size], // Send the selected compartment size
                security: myAjax.nonce,
            };

            jQuery.post(
                myAjax.ajaxurl,
                data,
                function (response) {
                    button.disabled = false;

                    if (response.success) {
                        if (response.data && response.data.new_parcel_ids) {
                            const parcelIds = response.data.new_parcel_ids;
                            const boxNowParcelIdsEl =
                                document.getElementById("box_now_parcel_ids");
                            if (boxNowParcelIdsEl) {
                                boxNowParcelIdsEl.value = JSON.stringify(parcelIds);
                            }

                            // Display the parcel ID links
                            displayParcelIdLinks(parcelIds);

                            // Disable all buttons after creating the vouchers
                            buttons.forEach((btn) => btn && (btn.disabled = true));
                        } else {
                            alert(
                                "Error: New parcel IDs are not available in the response data."
                            );
                            button.disabled = false; // Re-enable the button if there is an error
                        }
                    } else {
                        alert("Error: " + response.data);
                        button.disabled = false; // Re-enable the button if there is an error
                    }
                },
                "json"
            );
        });
    }

    const boxNowParcelIdsEl = document.getElementById("box_now_parcel_ids");
    if (boxNowParcelIdsEl) {
        const parcelIds = JSON.parse(boxNowParcelIdsEl.value || "[]");
        displayParcelIdLinks(parcelIds);

        // Enable or disable the button based on whether there are vouchers created for the order
        buttons.forEach((btn) => btn && (btn.disabled = parcelIds.length > 0));
    }
});

function displayParcelIdLinks(parcelIds) {
    const pdfLinkContainer = document.getElementById("box_now_voucher_link");
    pdfLinkContainer.innerHTML = ""; // Clear the container

    parcelIds.forEach((parcelId) => {
        const orderId = document.getElementById("box_now_order_id").value;

        const newLinkHtml = `
      <a href="#" data-parcel-id="${parcelId}" class="parcel-id-link box-now-link">&#128196; ${parcelId}</a>
      <button class="cancel-voucher-btn" data-order-id="${orderId}" style="color: white; background-color: red; margin: 4px 0; border: none; border-radius: 4px; cursor: pointer; padding: 6px 12px; font-size: 13px;">&#9664; Cancel Voucher</button>
      <br>`;

        pdfLinkContainer.innerHTML += newLinkHtml;
    });

    // Event delegation for parcelIdLinks and cancelButtons
    pdfLinkContainer.addEventListener("click", function (event) {
        if (event.target.matches(".parcel-id-link")) {
            event.preventDefault();
            const parcelId = event.target.getAttribute("data-parcel-id");
            window.open(
                myAjax.ajaxurl + "?action=print_box_now_voucher&parcel_id=" + parcelId,
                "_blank",
                "noopener,noreferrer"
            );
        }

        if (event.target.matches(".cancel-voucher-btn")) {
            handleCancelVoucherClick(event);
        }
    });
}

function handleCancelVoucherClick(event) {
    event.preventDefault();

    const orderId = event.target.getAttribute("data-order-id");
    const parcelId =
        event.target.previousElementSibling.getAttribute("data-parcel-id");

    const nonce = myAjax.nonce;

    const data = {
        action: "cancel_voucher",
        order_id: orderId,
        parcel_id: parcelId,
        nonce: nonce,
    };

    jQuery.post(
        myAjax.ajaxurl,
        data,
        function (response) {
            if (response.success) {
                let canceledParcelId = response.data;
                const boxNowParcelIdsEl = document.getElementById("box_now_parcel_ids");
                if (boxNowParcelIdsEl) {
                    const parcelIds = JSON.parse(boxNowParcelIdsEl.value || "[]");
                    const index = parcelIds.indexOf(canceledParcelId);
                    if (index !== -1) {
                        parcelIds.splice(index, 1);
                    }
                    boxNowParcelIdsEl.value = JSON.stringify(parcelIds);

                    // Enable the create voucher button if all vouchers have been canceled
                    if (parcelIds.length === 0) {
                        const createVouchersBtn = document.getElementById(
                            "box_now_create_voucher"
                        );
                        if (createVouchersBtn) {
                            createVouchersBtn.disabled = false;
                        }
                    }
                }
                location.reload();
            } else {
                console.error("Error canceling voucher:", response.data);
            }
        },
        "json"
    );
}
