$(document).ready(function () {

function showError(field, message) {
  field.addClass("is-invalid").removeClass("is-valid");
  field.closest(".form-floating, .mb-3, .col-md-6, .col-12")
       .find(".invalid-feedback")
       .text(message);
}

function showSuccess(field) {
  field.removeClass("is-invalid").addClass("is-valid");
}

  /* ================= EMAIL ================= */
  $("#email, #contact_email, #user_email, #admin_email").on("input", function () {
    const value = $(this).val().trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (value === "") {
      showError($(this), "Email is required.");
    } else if (!emailRegex.test(value)) {
      showError($(this), "Enter a valid email.");
    } else {
      showSuccess($(this));
    }
  });

  /* ================= NAME ================= */
  $("#name, #contact_name").on("input", function () {
    const value = $(this).val().trim();
    const nameRegex = /^[A-Za-z\s]+$/;

    if (value.length < 3) {
      showError($(this), "Name must be at least 3 characters.");
    } else if (!nameRegex.test(value)) {
      showError($(this), "Only alphabets allowed.");
    } else {
      showSuccess($(this));
    }
  });

  /* ================= PHONE ================= */
  $("#phone, #contact_phone").on("input", function () {
    const phoneRegex = /^[0-9]{10}$/;

    if (!phoneRegex.test($(this).val().trim())) {
      showError($(this), "Enter valid 10 digit phone number.");
    } else {
      showSuccess($(this));
    }
  });

  /* ================= PASSWORD ================= */
  $("#password, #user_password, #admin_password").on("input", function () {

    const value = $(this).val();
    const strongPassword =
      /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[@$!%*?&]).{8,}$/;

    if (!strongPassword.test(value)) {
      showError($(this),
        "Min 8 chars, 1 uppercase, 1 lowercase, 1 number & 1 special char."
      );
    } else {
      showSuccess($(this));
    }
  });

  /* ================= CONFIRM PASSWORD ================= */
  $("#confirm").on("input", function () {
    if ($(this).val() !== $("#password").val()) {
      showError($(this), "Passwords do not match.");
    } else {
      showSuccess($(this));
    }
  });

  /* ================= MESSAGE ================= */
  $("#contact_message, #address").on("input", function () {
    if ($(this).val().trim().length < 10) {
      if ($(this).attr("id") === "address") {
        showError($(this), "Address must be at least 10 characters.");
      } else {
        showError($(this), "Message must be at least 10 characters.");
      }
    } else {
      showSuccess($(this));
    }
  });

  /* ================= FINAL SUBMIT CHECK ================= */
  $("form").on("submit", function (e) {
    let valid = true;

    $(this).find("input[required], textarea[required]").each(function () {
      if ($(this).val().trim() === "") {
        showError($(this), "This field is required.");
        valid = false;
      }
    });

    if (!valid) {
      e.preventDefault();
    }
  });

});
