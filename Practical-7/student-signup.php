<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form data
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mobile = trim($_POST["mobile"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirmPassword"] ?? "";
    $course = trim($_POST["course"] ?? "");
    $year = trim($_POST["year"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $terms = isset($_POST["terms"]);

    $errors = [];


    // Validate Name
    if ($name == "") {
        $errors[] = "Full Name is required.";
    }


    // Validate Email
    if ($email == "") {
        $errors[] = "Email Address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }


    // Validate Mobile
    if ($mobile == "") {
        $errors[] = "Phone Number is required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
        $errors[] = "Phone Number must contain exactly 10 digits.";
    }


    // Validate Password
    if ($password == "") {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must contain at least 6 characters.";
    }


    // Validate Confirm Password
    if ($confirmPassword == "") {
        $errors[] = "Confirm Password is required.";
    } elseif ($password != $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }


    // Validate Course
    if ($course == "") {
        $errors[] = "Please select a course.";
    }


    // Validate Year
    if ($year == "") {
        $errors[] = "Please select a year.";
    }


    // Validate Gender
    if ($gender == "") {
        $errors[] = "Please select gender.";
    }


    // Validate Terms
    if (!$terms) {
        $errors[] = "Please accept Terms and Conditions.";
    }


    // Show errors
    if (!empty($errors)) {

        echo "<h2 style='color:red;'>Registration Failed</h2>";

        foreach ($errors as $error) {
            echo "<p style='color:red;'>$error</p>";
        }

        echo "<br>";

        echo "<a href='student-signup.html'>Go Back</a>";

        exit;
    }


    // Sanitize data
    $name = htmlspecialchars($name);
    $email = htmlspecialchars($email);
    $mobile = htmlspecialchars($mobile);
    $course = htmlspecialchars($course);
    $year = htmlspecialchars($year);
    $gender = htmlspecialchars($gender);


    // CSV file
    $csvFile = __DIR__ . "/students-signups.csv";


    // Open CSV
    $file = fopen($csvFile, "a");

    if ($file === false) {

        die("<h2 style='color:red;'>Unable to open CSV file.</h2>");
    }


    // Store data
    $result = fputcsv($file, [
        $name,
        $email,
        $mobile,
        $course,
        $year,
        $gender
    ]);


    // Close CSV
    fclose($file);


    // Check if data was stored
    if ($result === false) {

        die("<h2 style='color:red;'>Data could not be stored.</h2>");
    }


    // Success message
    echo "<script>";
echo "alert('Registration Successful! Your data has been saved.');";
echo "window.location.href = 'student-dashboard.html';";
echo "</script>";
}

?>