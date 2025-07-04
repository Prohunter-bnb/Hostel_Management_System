<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Initialize variables
$errors = [];
$success = false;

// Sanitize and validate input
$first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
$last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
$address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
$gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
$dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_STRING);
$class = filter_input(INPUT_POST, 'class', FILTER_SANITIZE_STRING);
$section = filter_input(INPUT_POST, 'section', FILTER_SANITIZE_STRING);

// Validate required fields
if (empty($first_name)) $errors[] = "First name is required";
if (empty($last_name)) $errors[] = "Last name is required";
if (empty($email)) $errors[] = "Email is required";
if (empty($phone)) $errors[] = "Phone number is required";
if (empty($address)) $errors[] = "Address is required";
if (empty($gender)) $errors[] = "Gender is required";
if (empty($dob)) $errors[] = "Date of birth is required";
if (empty($class)) $errors[] = "Class is required";
if (empty($section)) $errors[] = "Section is required";

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
}

// Validate phone number format (basic validation)
if (!preg_match("/^[0-9]{10}$/", $phone)) {
    $errors[] = "Invalid phone number format";
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $errors[] = "Email already exists";
}
$stmt->close();

if (empty($errors)) {
    // Generate roll number (format: YYYY-CLASS-SECTION-XXX)
    $year = date('Y');
    $roll_number = $year . '-' . $class . '-' . $section . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Generate password (first 4 letters of first name + last 4 digits of phone)
    $password = substr($first_name, 0, 4) . substr($phone, -4);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'student')");
        $stmt->bind_param("ss", $email, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();

        // Insert into students table
        $stmt = $conn->prepare("INSERT INTO students (user_id, first_name, last_name, roll_number, phone, address, gender, dob, class, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssss", $user_id, $first_name, $last_name, $roll_number, $phone, $address, $gender, $dob, $class, $section);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();
        $success = true;
        
        // Store success message and credentials
        $_SESSION['success_message'] = "Student added successfully!";
        $_SESSION['student_credentials'] = [
            'roll_number' => $roll_number,
            'password' => $password
        ];

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $errors[] = "Error adding student: " . $e->getMessage();
    }
}

// Store errors in session if any
if (!empty($errors)) {
    $_SESSION['error_messages'] = $errors;
}

// Redirect back to add member page
header("Location: add_member.php");
exit();
?>
