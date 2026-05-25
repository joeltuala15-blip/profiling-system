<?php
session_start();
include('db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $survey_date = trim($_POST['survey_date'] ?? '');
    $today = date('Y-m-d');

    if ($survey_date === '') {
        header("Location: add_household.php?error=survey_date_required");
        exit();
    }

    if (strtotime($survey_date) > strtotime($today)) {
        header("Location: add_household.php?error=survey_date_future");
        exit();
    }

    $hh_no = mysqli_real_escape_string($conn, $_POST['hh_no']);
    $survey_date = mysqli_real_escape_string($conn, $survey_date);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $purok = mysqli_real_escape_string($conn, $_POST['purok']);
    $house = mysqli_real_escape_string($conn, $_POST['house']);
    $electricity = mysqli_real_escape_string($conn, $_POST['electricity']);
    $house_type = mysqli_real_escape_string($conn, $_POST['house_type']);
    $toilet = mysqli_real_escape_string($conn, $_POST['toilet']);
    $vulnerability = mysqli_real_escape_string($conn, $_POST['vulnerability']);
    $water = mysqli_real_escape_string($conn, $_POST['water']);

    $check_query = "SELECT household_no FROM households WHERE household_no = '$hh_no'";
    $result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($result) > 0) {
        header("Location: add_household.php?error=household_number_exists");
        exit();
    }

    $query = "INSERT INTO households (
                household_no, 
                survey_date, 
                address, 
                purok, 
                house, 
                house_type, 
                electricity, 
                toilet, 
                vulnerability, 
                water
              ) VALUES (
                '$hh_no', 
                '$survey_date', 
                '$address', 
                '$purok', 
                '$house', 
                '$house_type', 
                '$electricity', 
                '$toilet', 
                '$vulnerability', 
                '$water'
              )";

    if (mysqli_query($conn, $query)) {
        $_SESSION['current_household_no'] = $hh_no;
        header("Location: add_members.php?household_no=" . rawurlencode($hh_no) . "&success=household_added");
        exit();
    } else {
        die("Database Error: " . mysqli_error($conn));
    }
} else {
    header("Location: add_household.php");
    exit();
}
?>












