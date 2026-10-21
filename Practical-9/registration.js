document.getElementById("registrationForm").addEventListener("submit", function(event) {

    let valid = true;

    // Clear old errors
    document.querySelectorAll("[id$='Error']").forEach(function(error) {
        error.textContent = "";
    });

    let name = document.getElementById("name").value.trim();
    let username = document.getElementById("username").value.trim();
    let email = document.getElementById("email").value.trim();
    let mobile = document.getElementById("mobile").value.trim();
    let password = document.getElementById("password").value;
    let confirmPassword = document.getElementById("confirmPassword").value;
    let course = document.getElementById("course").value;
    let year = document.getElementById("year").value;

    let gender = document.querySelector('input[name="gender"]:checked');

    let terms = document.getElementById("terms").checked;


    // Patterns
    let namePattern = /^[A-Za-z ]{3,50}$/;

    let usernamePattern = /^[A-Za-z0-9_]{3,30}$/;

    let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    let mobilePattern = /^[6-9][0-9]{9}$/;

    let passwordPattern = /^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@#$%!]).{8,}$/;


    // Name validation
    if (!namePattern.test(name)) {

        document.getElementById("nameError").textContent =
            "Please enter a name using 3-50 letters and spaces.";

        valid = false;
    }

    // Username validation
    if (!usernamePattern.test(username)) {

        document.getElementById("usernameError").textContent =
            "Please use 3-30 letters, numbers, or underscores.";

        valid = false;
    }


    // Email validation
    if (!emailPattern.test(email) || email.length > 254) {

        document.getElementById("emailError").textContent =
            "Please enter a valid email.";

        valid = false;
    }


    // Mobile validation
    if (!mobilePattern.test(mobile)) {

        document.getElementById("mobileError").textContent =
            "Enter a valid 10-digit mobile number starting with 6-9.";

        valid = false;
    }


    // Password validation
    if (password.length > 72 || !passwordPattern.test(password)) {

        document.getElementById("passwordError").textContent =
            "Use at least 8 characters with uppercase, lowercase, number, and a special character (@#$%!).";

        valid = false;
    }


    // Confirm password
    if (confirmPassword === "" || password !== confirmPassword) {

        document.getElementById("confirmPasswordError").textContent =
            "Passwords do not match.";

        valid = false;
    }


    // Course
    if (course === "") {

        document.getElementById("courseError").textContent =
            "Please select a course.";

        valid = false;
    }


    // Year
    if (year === "") {

        document.getElementById("yearError").textContent =
            "Please select your year.";

        valid = false;
    }


    // Gender
    if (!gender) {

        document.getElementById("genderError").textContent =
            "Please select your gender.";

        valid = false;
    }


    // Terms
    if (!terms) {

        document.getElementById("termsError").textContent =
            "Please accept the Terms and Conditions.";

        valid = false;
    }


    // If validation fails, stop submission
    if (!valid) {

        event.preventDefault();

    }

});