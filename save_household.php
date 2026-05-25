<?php
session_start();
include('db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    $_SESSION['temp_household_data'] = [
        'hh_no' => $_POST['hh_no'],
        'survey_date' => $survey_date,
        'address' => $_POST['address'],
        'purok' => $_POST['purok'],
        'house' => $_POST['house'],
        'electricity' => $_POST['electricity'],
        'house_type' => $_POST['house_type'],
        'toilet' => $_POST['toilet'],
        'vulnerability' => $_POST['vulnerability'],
        'water' => $_POST['water']
    ];

    header("Location: add_member.php");
    exit();
}
?>













