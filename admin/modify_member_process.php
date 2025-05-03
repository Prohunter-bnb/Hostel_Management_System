<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $user_type = mysqli_real_escape_string($conn, $_POST['user_type']);

    // Check if the member exists
    $check_sql = "SELECT * FROM users WHERE id = '$id'";
    $result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($result) > 0) {
        // Update the member details
        $update_sql = "UPDATE users SET name='$name', email='$email', user_type='$user_type' WHERE id='$id'";
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['success'] = "Member details updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating member: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Member not found!";
    }

    mysqli_close($conn);
    header("Location: modify_member.php");
    exit();
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: modify_member.php");
    exit();
}
?>
