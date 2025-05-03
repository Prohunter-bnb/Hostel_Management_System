<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_connect.php';

if (isset($_GET['id'])) {
    try {
        $id = $_GET['id'];

        // First, check if the user exists and is a student
        $stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['user_type'] !== 'student') {
            $_SESSION['error'] = "Invalid user selected for deletion";
            header("Location: modify_member.php");
            exit();
        }

        // Begin transaction
        $conn->beginTransaction();

        // Delete related records first
        // Delete from room_allocations
        $stmt = $conn->prepare("DELETE FROM room_allocations WHERE student_id = ?");
        $stmt->execute([$id]);

        // Delete from fee_payments
        $stmt = $conn->prepare("DELETE FROM fee_payments WHERE student_id = ?");
        $stmt->execute([$id]);

        // Finally, delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        // Commit transaction
        $conn->commit();

        $_SESSION['success'] = "Member deleted successfully";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting member: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "No member selected for deletion";
}

header("Location: modify_member.php");
exit();
?> 