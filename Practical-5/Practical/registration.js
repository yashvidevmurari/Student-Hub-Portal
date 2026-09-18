document.getElementById("registrationForm").addEventListener("submit", function(event) {

    event.preventDefault();

    let valid = true;

    
    document.querySelectorAll("span").forEach(function(span) {
        span.innerHTML = "";
    });

    let name = document.getElementById("name").value.trim();
    let email = document.getElementById("email").value.trim();
    let mobile = document.getElementById("mobile").value.trim();
    let password = document.getElementById("password").value;
    let confirmPassword = document.getElementById("confirmPassword").value;
    let course = document.getElementById("course").value;
    let year = document.getElementById("year").value;
    let gender = document.querySelector('input[name="gender"]:checked');
    let terms = document.getElementById("terms").checked;

    
    let namePattern = /^[A-Za-z ]{3,30}$/;
    let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    let mobilePattern = /^[6-9][0-9]{9}$/;
    let passwordPattern = /^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@#$%!]).{8,}$/;


    if (!namePattern.test(name)) {
        document.getElementById("nameError").innerHTML =
            "❌ Please enter a valid name.";
        valid = false;
    }

    if (!emailPattern.test(email)) {
        document.getElementById("emailError").innerHTML =
            "❌ Please enter a valid email.";
        valid = false;
    }

    if (!mobilePattern.test(mobile)) {
        document.getElementById("mobileError").innerHTML =
            "❌ Enter a valid 10-digit mobile number.";
        valid = false;
    }

    if (!passwordPattern.test(password)) {
        document.getElementById("passwordError").innerHTML =
            "❌ Password must have 8 characters, uppercase, lowercase, number and special character.";
        valid = false;
    }

    if (password !== confirmPassword) {
        document.getElementById("confirmPasswordError").innerHTML =
            "❌ Passwords do not match.";
        valid = false;
    }

    if (course === "") {
        document.getElementById("courseError").innerHTML =
            "❌ Please select a course.";
        valid = false;
    }

    if (year === "") {
        document.getElementById("yearError").innerHTML =
            "❌ Please select your year.";
        valid = false;
    }

    if (!gender) {
        document.getElementById("genderError").innerHTML =
            "❌ Please select your gender.";
        valid = false;
    }

    if (!terms) {
        document.getElementById("termsError").innerHTML =
            "❌ Please accept the Terms and Conditions.";
        valid = false;
    }

    if (valid) {
        alert("Registration Successful!");
        window.location.href = "student-dashboard.html";
    }

});