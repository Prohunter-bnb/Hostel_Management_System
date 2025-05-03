<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}

include '../../config/db_connect.php';

// Get fee statistics
$stats = [
    'total_pending' => 0,
    'total_paid' => 0,
    'total_overdue' => 0
];

try {
    // Get pending amount
    $stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM fees WHERE payment_status = 'pending'");
    $stats['total_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get paid amount
    $stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM fees WHERE payment_status = 'paid'");
    $stats['total_paid'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get overdue amount
    $stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM fees WHERE payment_status = 'overdue'");
    $stats['total_overdue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching fee statistics: " . $e->getMessage();
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
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar bg-white w-64 space-y-6 py-7 px-2 absolute inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition duration-200 ease-in-out shadow-lg">
            <div class="flex items-center justify-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">HMS Admin</h1>
            </div>
            
            <nav class="space-y-3">
                <a href="../dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="room_management.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                    <i class="fas fa-bed"></i>
                    <span>Room Management</span>
                </a>
                <a href="allocation.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition duration-200">
                    <i class="fas fa-key"></i>
                    <span>Room Allocation</span>
                </a>
                <a href="fee_management.php" class="flex items-center space-x-3 px-4 py-3 text-gray-700 bg-gray-100 rounded-lg">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Fee Management</span>
                </a>
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
                </div>

                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="relative z-10 block h-8 w-8 rounded-full overflow-hidden border-2 border-gray-600 focus:outline-none focus:border-indigo-600">
                            <i class="fas fa-user-circle text-2xl text-gray-600"></i>
                        </button>
                        <div class="absolute right-0 mt-2 py-2 w-48 bg-white rounded-md shadow-xl z-20 hidden">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                            <a href="../../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100">
                <div class="container mx-auto px-6 py-8">
                    <h3 class="text-gray-700 text-3xl font-medium mb-6">Fee Management</h3>

                    <!-- Fee Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- Pending Fees -->
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Pending Fees</p>
                                    <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($stats['total_pending'], 2); ?></p>
                                </div>
                                <div class="p-3 bg-yellow-100 rounded-full">
                                    <i class="fas fa-clock text-yellow-500"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Paid Fees -->
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Paid Fees</p>
                                    <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($stats['total_paid'], 2); ?></p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <i class="fas fa-check text-green-500"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Overdue Fees -->
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Overdue Fees</p>
                                    <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($stats['total_overdue'], 2); ?></p>
                                </div>
                                <div class="p-3 bg-red-100 rounded-full">
                                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Management Actions -->
                    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <h4 class="text-xl font-medium text-gray-700">Fee Management Actions</h4>
                            <button onclick="window.location.href='add_fee.php'" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200">
                                <i class="fas fa-plus mr-2"></i> Add New Fee
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <button onclick="window.location.href='generate_fee.php'" class="flex items-center justify-center space-x-2 p-4 border-2 border-gray-200 rounded-lg hover:border-indigo-500 hover:text-indigo-500 transition-all duration-200">
                                <i class="fas fa-file-invoice"></i>
                                <span>Generate Fee</span>
                            </button>

                            <button onclick="window.location.href='collect_fee.php'" class="flex items-center justify-center space-x-2 p-4 border-2 border-gray-200 rounded-lg hover:border-indigo-500 hover:text-indigo-500 transition-all duration-200">
                                <i class="fas fa-hand-holding-usd"></i>
                                <span>Collect Fee</span>
                            </button>

                            <button onclick="window.location.href='fee_reports.php'" class="flex items-center justify-center space-x-2 p-4 border-2 border-gray-200 rounded-lg hover:border-indigo-500 hover:text-indigo-500 transition-all duration-200">
                                <i class="fas fa-chart-bar"></i>
                                <span>Fee Reports</span>
                            </button>

                            <button onclick="window.location.href='fee_settings.php'" class="flex items-center justify-center space-x-2 p-4 border-2 border-gray-200 rounded-lg hover:border-indigo-500 hover:text-indigo-500 transition-all duration-200">
                                <i class="fas fa-cog"></i>
                                <span>Fee Settings</span>
                            </button>
                        </div>
                    </div>

                    <!-- Recent Fee Transactions -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h4 class="text-xl font-medium text-gray-700">Recent Fee Transactions</h4>
                            <a href="fee_transactions.php" class="text-indigo-600 hover:text-indigo-800">View All</a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee Type</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php
                                    try {
                                        $stmt = $conn->query("
                                            SELECT f.*, s.roll_number, u.full_name 
                                            FROM fees f 
                                            JOIN students s ON f.student_id = s.id 
                                            JOIN users u ON s.user_id = u.id 
                                            ORDER BY f.created_at DESC 
                                            LIMIT 5
                                        ");
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $statusColor = [
                                                'pending' => 'yellow',
                                                'paid' => 'green',
                                                'overdue' => 'red'
                                            ][$row['payment_status']];
                                            ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['roll_number']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo ucfirst(htmlspecialchars($row['fee_type'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    ₹<?php echo number_format($row['amount'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                                                        <?php echo ucfirst(htmlspecialchars($row['payment_status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo date('d M Y', strtotime($row['due_date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="view_fee.php?id=<?php echo $row['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                                    <?php if ($row['payment_status'] !== 'paid'): ?>
                                                    <a href="collect_fee.php?id=<?php echo $row['id']; ?>" class="text-green-600 hover:text-green-900">Collect</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    } catch (PDOException $e) {
                                        echo "<tr><td colspan='6' class='px-6 py-4 text-center text-red-500'>Error fetching transactions: " . $e->getMessage() . "</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
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

        // Toggle profile dropdown
        document.querySelector('.relative button').addEventListener('click', () => {
            const dropdown = document.querySelector('.relative .absolute');
            dropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.querySelector('.relative .absolute');
            const button = document.querySelector('.relative button');
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html> 