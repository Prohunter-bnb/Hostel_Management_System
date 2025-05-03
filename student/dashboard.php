<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db_connect.php';

// Initialize variables with default values
$student = [
    'full_name' => $_SESSION['user_name'] ?? 'Student',
    'roll_number' => 'Not Assigned',
    'id' => null
];
$room = null;
$fees = [
    'pending_amount' => 0,
    'paid_amount' => 0,
    'total_fees' => 0
];
$error_messages = [];

try {
    // Get student details
    $stmt = $conn->prepare("
        SELECT s.*, u.full_name, u.email 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student_data) {
        $student = $student_data;
        
        // Get room details if allocated
        $stmt = $conn->prepare("
            SELECT r.* 
            FROM rooms r 
            JOIN allocations a ON r.id = a.room_id 
            WHERE a.student_id = ? AND a.status = 'active'
        ");
        $stmt->execute([$student['id']]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get fee summary
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_fees,
                COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount
            FROM fees 
            WHERE student_id = ?
        ");
        $stmt->execute([$student['id']]);
        $fees = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error_messages[] = "Student profile not found. Please contact the administrator.";
    }
} catch (PDOException $e) {
    $error_messages[] = "Error fetching data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Hostel Management System</title>
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
                            <h1 class="text-xl font-bold">HMS Student</h1>
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
                            <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 z-50" id="user-menu">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
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
                <?php if (!empty($error_messages)): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Attention needed</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <?php foreach ($error_messages as $message): ?>
                                            <li><?php echo htmlspecialchars($message); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Welcome Section -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Welcome, <?php echo htmlspecialchars($student['full_name']); ?></h2>
                    <p class="text-gray-600">Roll Number: <?php echo htmlspecialchars($student['roll_number']); ?></p>
                </div>

                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Room Status -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                <i class="fas fa-bed text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800">Room Status</h3>
                                <?php if ($room): ?>
                                    <p class="text-gray-600">Room <?php echo htmlspecialchars($room['room_number']); ?></p>
                                    <p class="text-sm text-gray-500">Floor: <?php echo htmlspecialchars($room['floor']); ?></p>
                                <?php else: ?>
                                    <p class="text-yellow-600">Not Allocated</p>
                                    <p class="text-sm text-gray-500">Contact administration</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Status -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-500">
                                <i class="fas fa-money-bill-wave text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800">Fee Status</h3>
                                <p class="text-gray-600">Pending: ₹<?php echo number_format($fees['pending_amount'] ?? 0, 2); ?></p>
                                <p class="text-sm text-gray-500">Paid: ₹<?php echo number_format($fees['paid_amount'] ?? 0, 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                                <i class="fas fa-link text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800">Quick Links</h3>
                                <div class="mt-2 space-y-2">
                                    <a href="fees.php" class="block text-indigo-600 hover:text-indigo-800">View Fees</a>
                                    <a href="complaints.php" class="block text-indigo-600 hover:text-indigo-800">Submit Complaint</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>
                    <div class="space-y-4">
                        <?php
                        try {
                            if ($student['id']) {
                                // Get recent fee transactions
                                $stmt = $conn->prepare("
                                    SELECT * FROM fees 
                                    WHERE student_id = ? 
                                    ORDER BY created_at DESC 
                                    LIMIT 5
                                ");
                                $stmt->execute([$student['id']]);
                                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if ($transactions) {
                                    foreach ($transactions as $transaction) {
                                        $statusColor = [
                                            'pending' => 'yellow',
                                            'paid' => 'green',
                                            'overdue' => 'red'
                                        ][$transaction['payment_status']];
                                        ?>
                                        <div class="flex items-center justify-between border-b pb-4">
                                            <div>
                                                <p class="text-gray-800"><?php echo ucfirst(htmlspecialchars($transaction['fee_type'])); ?> Fee</p>
                                                <p class="text-sm text-gray-500"><?php echo date('d M Y', strtotime($transaction['created_at'])); ?></p>
                                            </div>
                                            <div>
                                                <span class="px-3 py-1 rounded-full text-sm bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                                    ₹<?php echo number_format($transaction['amount'], 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    echo "<p class='text-gray-500'>No recent transactions found.</p>";
                                }
                            } else {
                                echo "<p class='text-gray-500'>Transaction history will be available once your profile is set up.</p>";
                            }
                        } catch (PDOException $e) {
                            echo "<p class='text-red-500'>Error fetching recent activity</p>";
                        }
                        ?>
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
        
        ['click', 'touchend'].forEach(eventType => {
            userMenuButton.addEventListener(eventType, (e) => {
                e.preventDefault();
                e.stopPropagation();
                userMenu.classList.toggle('hidden');
            });
        });

        // Close menu when clicking outside
        ['click', 'touchend'].forEach(eventType => {
            document.addEventListener(eventType, (e) => {
                if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                    userMenu.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html> 