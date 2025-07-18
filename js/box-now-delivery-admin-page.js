document.addEventListener("DOMContentLoaded", function () {
    var apiUrlInput = document.querySelector(
        'select[name="boxnow_api_url"], input[name="boxnow_api_url"]'
    );

    // Check if apiUrlInput exists (Not needed Dropdown)
    // if (apiUrlInput) {
    //   apiUrlInput.addEventListener("input", function () {
    //     var currentValue = apiUrlInput.value;
    //     var newValue = currentValue
    //       .replace(/^https?:\/\//i, "")
    //       .replace(/\/+$/, "");
    //     apiUrlInput.value = newValue;
    //   });
    // } else {
    //   console.error('Element with name "boxnow_api_url" was not found');
    // }

    //const emailOption = document.getElementById("send_voucher_email");
    //const buttonOption = document.getElementById("display_voucher_button");
    //const emailInputContainer = document.getElementById("email_input_container");

    //This is to show the email field at all times
    /*function toggleEmailInput() {
      emailInputContainer.style.display = "block";
    }

    emailOption.addEventListener("change", toggleEmailInput);
    buttonOption.addEventListener("change", toggleEmailInput);
    */
});
