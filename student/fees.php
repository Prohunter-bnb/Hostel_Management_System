<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db_connect.php';

try {
    // Get student details
    $stmt = $conn->prepare("
        SELECT s.*, u.full_name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get fee summary
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
            SUM(CASE WHEN payment_status = 'overdue' THEN amount ELSE 0 END) as overdue_amount
        FROM fees 
        WHERE student_id = ?
    ");
    $stmt->execute([$student['id']]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get all fees
    $stmt = $conn->prepare("
        SELECT 
            f.*,
            p.id as payment_id,
            p.payment_date,
            p.payment_method,
            p.transaction_id
        FROM fees f 
        LEFT JOIN payments p ON f.id = p.fee_id
        WHERE f.student_id = ? 
        ORDER BY f.due_date DESC
    ");
    $stmt->execute([$student['id']]);
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching fee details: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management - Hostel Management System</title>
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
                <!-- Fee Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Pending Fees -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800">Pending Fees</h3>
                                <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($summary['pending_amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Paid Fees -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-500">
                                <i class="fas fa-check text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800">Paid Fees</h3>
                                <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($summary['paid_amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Overdue Fees -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-500">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800">Overdue Fees</h3>
                                <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($summary['overdue_amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fee History -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Fee History</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee Type</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($fees as $fee): 
                                        $statusColor = [
                                            'pending' => 'yellow',
                                            'paid' => 'green',
                                            'overdue' => 'red'
                                        ][$fee['payment_status']];
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo ucfirst(htmlspecialchars($fee['fee_type'])); ?>
                                                </div>
                                                <?php if ($fee['description']): ?>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($fee['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">₹<?php echo number_format($fee['amount'], 2); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo date('d M Y', strtotime($fee['due_date'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                                    <?php echo ucfirst(htmlspecialchars($fee['payment_status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $fee['payment_date'] ? date('d M Y', strtotime($fee['payment_date'])) : '-'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if ($fee['payment_status'] !== 'paid'): ?>
                                                    <a href="make_payment.php?id=<?php echo $fee['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Pay Now</a>
                                                <?php else: ?>
                                                    <a href="receipt.php?id=<?php echo $fee['payment_id']; ?>" class="text-green-600 hover:text-green-900">
                                                        <i class="fas fa-receipt mr-1"></i>View Receipt
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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