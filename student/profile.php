<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db_connect.php';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize inputs
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $emergency_contact = filter_input(INPUT_POST, 'emergency_contact', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $current_password = filter_input(INPUT_POST, 'current_password', FILTER_SANITIZE_STRING);
        $new_password = filter_input(INPUT_POST, 'new_password', FILTER_SANITIZE_STRING);

        // Start transaction
        $conn->beginTransaction();

        // Update student information
        $stmt = $conn->prepare("
            UPDATE students 
            SET phone = ?, emergency_contact = ?, address = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$phone, $emergency_contact, $address, $_SESSION['user_id']]);

        // Update password if provided
        if ($current_password && $new_password) {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            } else {
                throw new Exception("Current password is incorrect");
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Profile updated successfully";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get student details
try {
    $stmt = $conn->prepare("
        SELECT s.*, u.full_name, u.email 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching profile: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - Hostel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <!-- Top Navigation -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <a href="dashboard.php" class="text-xl font-bold">HMS Student</a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="ml-3 relative">
                            <div>
                                <button type="button" class="flex items-center max-w-xs rounded-full text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="user-menu-button">
                                    <span class="sr-only">Open user menu</span>
                                    <i class="fas fa-user-circle text-2xl text-gray-600"></i>
                                </button>
                            </div>
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5" id="user-menu">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 bg-gray-100">Profile</a>
                                <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-700 hover:bg-gray-100">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Profile Information -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Profile Information</h2>
                        <form method="POST" class="space-y-6">
                            <!-- Basic Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Full Name</label>
                                    <input type="text" value="<?php echo htmlspecialchars($student['full_name']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50" disabled>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" value="<?php echo htmlspecialchars($student['email']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50" disabled>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Roll Number</label>
                                    <input type="text" value="<?php echo htmlspecialchars($student['roll_number']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50" disabled>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Emergency Contact</label>
                                    <input type="tel" name="emergency_contact" value="<?php echo htmlspecialchars($student['emergency_contact']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Address</label>
                                    <textarea name="address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><?php echo htmlspecialchars($student['address']); ?></textarea>
                                </div>
                            </div>

                            <!-- Change Password -->
                            <div class="border-t pt-6">
                                <h3 class="text-lg font-medium text-gray-800 mb-4">Change Password</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Current Password</label>
                                        <input type="password" name="current_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">New Password</label>
                                        <input type="password" name="new_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200">
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white shadow-lg mt-8">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-gray-500 text-sm">
                    &copy; <?php echo date('Y'); ?> Hostel Management System. All rights reserved.
                </p>
            </div>
        </footer>
    </div>

    <script>
        // Toggle user menu
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenu = document.getElementById('user-menu');
        
        userMenuButton.addEventListener('click', () => {
            userMenu.classList.toggle('hidden');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html> 