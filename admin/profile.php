<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_connect.php';

// Get admin details
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        $_SESSION['error'] = "Admin not found.";
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching profile: " . $e->getMessage();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Verify current password
        if (!password_verify($current_password, $admin['password'])) {
            throw new Exception("Current password is incorrect.");
        }

        // Verify new passwords match
        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match.");
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $_SESSION['user_id']]);

        $_SESSION['success'] = "Password updated successfully.";
        header("Location: profile.php");
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Hostel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar bg-white w-64 space-y-6 py-7 px-2 absolute inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition duration-200 ease-in-out shadow-lg">
            <div class="flex items-center justify-center mb-8">
                <div class="text-center">
                    <h1 class="text-2xl font-bold text-gray-800">HMS Admin</h1>
                    <p class="text-sm text-gray-500">Hostel Management System</p>
                </div>
            </div>
            
            <nav class="space-y-3">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <div class="space-y-3">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Members</p>
                    <a href="add_member.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Member</span>
                    </a>
                    <a href="view_members.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                        <i class="fas fa-users"></i>
                        <span>View Members</span>
                    </a>
                    <a href="modify_member.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                        <i class="fas fa-user-edit"></i>
                        <span>Modify Members</span>
                    </a>
                </div>

                <div class="space-y-3">
                    <p class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Management</p>
                    <a href="management/room_management.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                        <i class="fas fa-door-open"></i>
                        <span>Room Management</span>
                    </a>
                    <a href="management/allocation.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                        <i class="fas fa-bed"></i>
                        <span>Room Allocation</span>
                    </a>
                    <a href="management/fees.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Fee Management</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="flex justify-between items-center py-4 px-6 bg-white border-b-2">
                <div class="flex items-center">
                    <button class="text-gray-500 focus:outline-none md:hidden">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="text-2xl font-semibold text-gray-700 ml-4">Admin Profile</h2>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="flex items-center space-x-2 text-gray-700 hover:text-indigo-600 focus:outline-none">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="font-medium">Profile</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Profile dropdown menu -->
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span>My Profile</span>
                                </div>
                            </a>
                            <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span>Logout</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50">
                <div class="container mx-auto px-6 py-8">
                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p><?php echo $error; ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p><?php echo $_SESSION['success']; ?></p>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <div class="max-w-4xl mx-auto">
                        <!-- Profile Information -->
                        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-600 mr-4">
                                        <i class="fas fa-user text-xl"></i>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-700">Profile Information</h3>
                                </div>
                            </div>

                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Full Name</label>
                                        <input type="text" value="<?php echo htmlspecialchars($admin['full_name']); ?>" 
                                               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" disabled>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Email</label>
                                        <input type="email" value="<?php echo htmlspecialchars($admin['email']); ?>" 
                                               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" disabled>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">User Type</label>
                                        <input type="text" value="Administrator" 
                                               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" disabled>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Last Updated</label>
                                        <input type="text" value="<?php echo date('F j, Y', strtotime($admin['updated_at'])); ?>" 
                                               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                        <i class="fas fa-key text-xl"></i>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-700">Change Password</h3>
                                </div>
                            </div>

                            <form method="POST" class="p-6" onsubmit="return validatePasswordForm()">
                                <div class="space-y-6">
                                    <div>
                                        <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                                        <input type="password" name="current_password" id="current_password" 
                                               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                    </div>

                                    <div>
                                        <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                                        <input type="password" name="new_password" id="new_password" 
                                               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                    </div>

                                    <div>
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                        <input type="password" name="confirm_password" id="confirm_password" 
                                               class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" required>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" 
                                                class="px-6 py-2 bg-indigo-600 text-white rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                                            <i class="fas fa-save mr-2"></i>
                                            Update Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.querySelector('button.md\\:hidden').addEventListener('click', () => {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('-translate-x-full');
        });

        // Toggle profile dropdown with improved touch support
        const profileButton = document.querySelector('.relative button');
        const profileDropdown = document.querySelector('.relative .absolute');
        
        // Handle both click and touch events
        ['click', 'touchend'].forEach(eventType => {
            profileButton.addEventListener(eventType, (e) => {
                e.preventDefault();
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });
        });

        // Close dropdown when clicking/touching outside
        ['click', 'touchend'].forEach(eventType => {
            document.addEventListener(eventType, (e) => {
                if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                }
            });
        });

        // Password form validation
        function validatePasswordForm() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return false;
            }
            return true;
        }
    </script>
</body>
</html> 