<?php
session_start();
include '../config/db_connect.php'; // Ensure the correct database connection path

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header("Location: ../admin/dashboard.php"); // Redirect if already logged in
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, name, email, password, user_type FROM users WHERE email = ? AND user_type = 'admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_type'] = $user['user_type'];
            header("Location: ../admin/dashboard.php"); // Redirect to admin panel
            exit();
        } else {
            $error = "Incorrect password!";
        }
    } else {
        $error = "User not found or not an admin!";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-r from-gray-700 to-gray-900 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <h2 class="text-3xl font-bold text-gray-800 text-center">Admin Login</h2>
        <p class="text-gray-600 text-center mb-4">Enter your credentials</p>
        
        <?php if (isset($error)) echo "<p class='text-red-500 text-center'>$error</p>"; ?>
        
        <form action="login.php" method="POST">
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Email</label>
                <input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Password</label>
                <input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500">
            </div>
            <button type="submit" class="w-full bg-gray-800 text-white py-2 rounded-lg hover:bg-gray-900">Login</button>
        </form>
    </div>
</body>
</html>
