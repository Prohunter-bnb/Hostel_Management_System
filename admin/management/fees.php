<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}
include '../../config/db_connect.php';

// Fetch fee statistics
try {
    // Create fee_payments table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS fee_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        gst_amount DECIMAL(10,2) DEFAULT 0.00,
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        payment_date DATETIME NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        receipt_number VARCHAR(50) UNIQUE,
        month VARCHAR(20) NOT NULL,
        year INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id)
    )");

    // Create fee_settings table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS fee_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fee_type VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        due_day INT NOT NULL,
        late_fee DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert default fee settings if not exists
    $stmt = $conn->query("SELECT COUNT(*) FROM fee_settings");
    if ($stmt->fetchColumn() == 0) {
        $conn->query("INSERT INTO fee_settings (fee_type, amount, due_day, late_fee) VALUES 
            ('Monthly Hostel Fee', 3500.00, 10, 500.00)");
    } else {
        // Update existing fee amount
        $conn->query("UPDATE fee_settings SET amount = 3500.00 WHERE fee_type = 'Monthly Hostel Fee'");
    }

    // Get total students
    $stmt = $conn->query("SELECT COUNT(*) as total_students FROM users WHERE user_type = 'student'");
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'] ?? 0;

    // Get current month's statistics
    $currentMonth = date('Y-m');
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT student_id) as paid_students,
            COALESCE(SUM(amount), 0) as total_collected
                             FROM fee_payments 
        WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?
        AND status = 'paid'
    ");
    $stmt->execute([$currentMonth]);
    $monthStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $paidStudents = $monthStats['paid_students'] ?? 0;
    $totalCollected = $monthStats['total_collected'] ?? 0;
    $pendingPayments = $totalStudents - $paidStudents;

    // Get all time collection statistics
    $stmt = $conn->query("
        SELECT 
            COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as total_collected,
            COUNT(DISTINCT CASE WHEN status = 'paid' THEN student_id END) as total_paid_students,
            COUNT(DISTINCT CASE WHEN status = 'pending' THEN student_id END) as total_pending_students
        FROM fee_payments
    ");
    $allTimeStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent payments with student details
    $stmt = $conn->query("
        SELECT 
            fp.*,
            u.name as student_name,
            u.roll_number
        FROM fee_payments fp
        JOIN users u ON fp.student_id = u.id
        ORDER BY fp.payment_date DESC, fp.created_at DESC
        LIMIT 10
    ");
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get fee settings for monthly fee amount
    $stmt = $conn->query("SELECT amount FROM fee_settings WHERE fee_type = 'Monthly Hostel Fee' LIMIT 1");
    $monthlyFee = $stmt->fetch(PDO::FETCH_ASSOC)['amount'] ?? 3500.00;

} catch (PDOException $e) {
    $error = $e->getMessage();
    $totalStudents = 0;
    $paidStudents = 0;
    $totalCollected = 0;
    $pendingPayments = 0;
    $recentPayments = [];
    $allTimeStats = [
        'total_collected' => 0,
        'total_paid_students' => 0,
        'total_pending_students' => 0
    ];
}

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'record_payment') {
            // Get current date and time in IST
            date_default_timezone_set('Asia/Kolkata');
            $paymentDate = date('Y-m-d H:i:s');
            
            // Calculate amounts - Total amount is ₹3500 including 5% GST
            $totalAmount = 3500.00; // Fixed total amount including GST
            $baseAmount = round($totalAmount / 1.05, 2); // Base amount (3333.33)
            $gstAmount = round($totalAmount - $baseAmount, 2); // GST amount (166.67)
            
            // Generate receipt number with current date/time
            $receiptNumber = 'HMS-' . date('YmdHis') . '-' . sprintf('%04d', rand(1, 9999));
            
            $stmt = $conn->prepare("
                INSERT INTO fee_payments 
                (student_id, amount, gst_amount, total_amount, payment_date, payment_method, status, receipt_number, month, year) 
                VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?)
            ");
            
            $stmt->execute([
                $_POST['student_id'],
                $baseAmount,
                $gstAmount,
                $totalAmount,
                $paymentDate,
                $_POST['payment_method'],
                $receiptNumber,
                date('F'),
                date('Y')
            ]);
            
            $success = "Payment recorded successfully!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
            exit();
        }
    } catch (PDOException $e) {
        $error = "Failed to record payment: " . $e->getMessage();
    }
}

// Add this after the database connection code
if (isset($_POST['action']) && $_POST['action'] === 'export_report') {
    try {
        // Get all fee payments with student details
        $stmt = $conn->query("
            SELECT 
                u.name as student_name,
                u.roll_number,
                fp.amount,
                fp.payment_date,
                fp.payment_method,
                fp.receipt_number,
                fp.month,
                fp.year,
                fp.status
            FROM fee_payments fp
            JOIN users u ON fp.student_id = u.id
            ORDER BY fp.payment_date DESC, u.name ASC
        ");
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="fee_payments_report_' . date('Y-m-d') . '.csv"');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for proper Excel display
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Add headers
        fputcsv($output, [
            'Student Name',
            'Roll Number',
            'Amount',
            'Payment Date',
            'Payment Method',
            'Receipt Number',
            'Month',
            'Year',
            'Status'
        ]);

        // Add data rows
        foreach ($payments as $payment) {
            fputcsv($output, [
                $payment['student_name'],
                $payment['roll_number'],
                '₹' . number_format($payment['amount'], 2),
                date('d M Y', strtotime($payment['payment_date'])),
                ucwords(str_replace('_', ' ', $payment['payment_method'])),
                $payment['receipt_number'],
                $payment['month'],
                $payment['year'],
                ucfirst($payment['status'])
            ]);
        }

        fclose($output);
        exit();
    } catch (PDOException $e) {
        $error = "Failed to export report: " . $e->getMessage();
    }
}

// Modify the query to get all payments for each student
$query = "
    SELECT 
        u.id as student_id,
        u.name as student_name,
        u.roll_number,
        u.email,
        GROUP_CONCAT(
            CONCAT_WS('|',
                COALESCE(fp.id, ''),
                COALESCE(fp.amount, ''),
                COALESCE(fp.payment_date, ''),
                COALESCE(fp.payment_method, ''),
                COALESCE(fp.receipt_number, ''),
                COALESCE(fp.month, ''),
                COALESCE(fp.year, '')
            )
            ORDER BY fp.payment_date DESC
            SEPARATOR ';;'
        ) as payment_history,
        COALESCE(
            5000.00 - COALESCE(
                (SELECT SUM(fp2.amount) 
                FROM fee_payments fp2 
                WHERE fp2.student_id = u.id 
                AND fp2.month = DATE_FORMAT(CURRENT_DATE, '%M')
                AND fp2.year = YEAR(CURRENT_DATE)
                AND fp2.status = 'paid'),
            0),
            5000.00
        ) as due_amount
    FROM users u
    LEFT JOIN fee_payments fp ON u.id = fp.student_id
    WHERE u.user_type = 'student'
    GROUP BY u.id, u.name, u.roll_number, u.email
";

$params = [];

// Apply filters
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $query .= " AND (u.name LIKE ? OR u.roll_number LIKE ?)";
    $searchTerm = "%" . $_GET['search'] . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $query .= " AND (fp.status = ? OR (fp.status IS NULL AND ? = 'pending'))";
    $params[] = $_GET['status'];
    $params[] = $_GET['status'];
}

if (isset($_GET['time_period']) && !empty($_GET['time_period'])) {
    switch ($_GET['time_period']) {
        case 'this_month':
            $query .= " AND (DATE_FORMAT(fp.payment_date, '%Y-%m') = ? OR fp.payment_date IS NULL)";
            $params[] = date('Y-m');
            break;
        case 'last_month':
            $query .= " AND (DATE_FORMAT(fp.payment_date, '%Y-%m') = ? OR fp.payment_date IS NULL)";
            $params[] = date('Y-m', strtotime('-1 month'));
            break;
        case 'this_year':
            $query .= " AND (YEAR(fp.payment_date) = ? OR fp.payment_date IS NULL)";
            $params[] = date('Y');
            break;
        default:
            // No additional conditions for all-time filter
            break;
    }
}

$query .= " ORDER BY u.name ASC, fp.payment_date DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching payments: " . $e->getMessage();
    $payments = [];
}

// Handle mark as paid action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paid') {
    try {
        $stmt = $conn->prepare("UPDATE fee_payments SET status = 'paid' WHERE id = ?");
        $stmt->execute([$_POST['payment_id']]);
        $success = "Payment marked as paid successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
        exit();
    } catch (PDOException $e) {
        $error = "Failed to update payment status: " . $e->getMessage();
    }
}

// Get notifications
$notifications = [];
try {
    // Get overdue payments
    $stmt = $conn->prepare("
        SELECT 
            u.name,
            u.roll_number,
            DATEDIFF(CURRENT_DATE, MAX(fp.payment_date)) as days_overdue
        FROM users u
        LEFT JOIN fee_payments fp ON u.id = fp.student_id
        WHERE u.user_type = 'student'
        GROUP BY u.id
        HAVING days_overdue > 30 OR days_overdue IS NULL
        ORDER BY days_overdue DESC
        LIMIT 5
    ");
    $stmt->execute();
    $overduePayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($overduePayments as $payment) {
        $notifications[] = [
            'type' => 'overdue',
            'message' => "{$payment['name']} ({$payment['roll_number']}) has overdue fees" . 
                        ($payment['days_overdue'] ? " for {$payment['days_overdue']} days" : ""),
            'time' => date('Y-m-d H:i:s')
        ];
    }

    // Get recent payments (last 24 hours)
    $stmt = $conn->prepare("
        SELECT 
            u.name,
            u.roll_number,
            fp.amount,
            fp.payment_date
        FROM fee_payments fp
        JOIN users u ON fp.student_id = u.id
        WHERE fp.payment_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY fp.payment_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recentPayments as $payment) {
        $notifications[] = [
            'type' => 'payment',
            'message' => "{$payment['name']} ({$payment['roll_number']}) paid ₹" . number_format($payment['amount'], 2),
            'time' => $payment['payment_date']
        ];
    }

    // Sort notifications by time
    usort($notifications, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
} catch (PDOException $e) {
    // Silently handle error
    $notifications = [];
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --background: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --text-secondary: #475569;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background);
            color: var(--text);
            line-height: 1.6;
        }

        .sidebar {
            background-color: var(--surface);
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.05);
            width: 280px;
            transition: all 0.3s ease;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--text-secondary);
            border-radius: 0.5rem;
            margin: 0.25rem 0;
            transition: all 0.2s ease;
        }

        .sidebar-link:hover {
            background-color: rgba(37, 99, 235, 0.05);
            color: var(--primary);
        }

        .sidebar-link.active {
            background-color: var(--primary);
            color: white;
        }

        .header {
            background-color: var(--surface);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--secondary);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease forwards;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar fixed inset-y-0 left-0 z-30 md:relative md:translate-x-0 transform -translate-x-full transition-all duration-300">
            <div class="flex items-center justify-center h-16 border-b border-gray-200">
                <div class="text-center">
                    <h1 class="text-xl font-bold text-primary">HMS Admin</h1>
                    <p class="text-sm text-gray-500">Hostel Management System</p>
                </div>
            </div>
            
            <nav class="mt-6 px-4">
                <a href="../dashboard.php" class="sidebar-link mb-3">
                    <i class="fas fa-chart-line w-5 h-5 mr-3"></i>
                    <span>Dashboard</span>
                </a>

                <div class="mb-6">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Members</p>
                    <a href="../add_member.php" class="sidebar-link mb-2">
                        <i class="fas fa-user-plus w-5 h-5 mr-3"></i>
                        <span>Add Student</span>
                    </a>
                    <a href="../view_members.php" class="sidebar-link mb-2">
                        <i class="fas fa-users w-5 h-5 mr-3"></i>
                        <span>View Members</span>
                    </a>
                    <a href="../modify_member.php" class="sidebar-link">
                        <i class="fas fa-user-edit w-5 h-5 mr-3"></i>
                        <span>Modify Members</span>
                    </a>
                </div>

                <div>
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Management</p>
                    <a href="room_management.php" class="sidebar-link mb-2">
                        <i class="fas fa-door-open w-5 h-5 mr-3"></i>
                        <span>Room Management</span>
                    </a>
                    <a href="allocation.php" class="sidebar-link mb-2">
                        <i class="fas fa-bed w-5 h-5 mr-3"></i>
                        <span>Room Allocation</span>
                    </a>
                    <a href="fees.php" class="sidebar-link active">
                        <i class="fas fa-money-bill-wave w-5 h-5 mr-3"></i>
                        <span>Fee Management</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="header h-16 flex items-center justify-between px-6">
                <div class="flex items-center">
                    <button class="text-gray-500 hover:text-gray-600 focus:outline-none md:hidden">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-2xl font-bold text-gray-800 ml-6">Fee Management</h2>
                </div>

                <?php include '../components/profile_dropdown.php'; ?>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="max-w-7xl mx-auto">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <!-- Fee Statistics -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                            <div class="bg-white rounded-lg shadow-md p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                                        <i class="fas fa-money-bill-wave text-2xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-2xl font-semibold text-gray-700">₹<?php echo number_format($totalCollected, 2); ?></h4>
                                        <p class="text-gray-500">This Month's Collection</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg shadow-md p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                        <i class="fas fa-chart-line text-2xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-2xl font-semibold text-gray-700">₹<?php echo number_format($allTimeStats['total_collected'], 2); ?></h4>
                                        <p class="text-gray-500">Total Collection</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg shadow-md p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                                        <i class="fas fa-user-check text-2xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-2xl font-semibold text-gray-700"><?php echo $paidStudents; ?>/<?php echo $totalStudents; ?></h4>
                                        <p class="text-gray-500">Paid Students</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg shadow-md p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-red-100 text-red-500">
                                        <i class="fas fa-user-clock text-2xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-2xl font-semibold text-gray-700"><?php echo $pendingPayments; ?></h4>
                                        <p class="text-gray-500">Pending Payments</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Management Actions -->
                        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-semibold text-gray-700">Fee Records</h3>
                                <div class="space-x-2">
                                    <button onclick="openExportModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200">
                                        <i class="fas fa-file-export mr-2"></i> Export Report
                                    </button>
                                </div>
                            </div>

                            <!-- Search and Filters -->
                            <form method="GET" class="flex flex-wrap gap-4 mb-6">
                                <div class="flex-1 relative">
                                    <input type="text" name="search" 
                                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                           placeholder="Search by name or roll number..." 
                                           class="w-full px-4 py-2 pr-12 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                                    <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1 text-gray-500 hover:text-indigo-600">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <select name="status" class="px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                                    <option value="">All Status</option>
                                    <option value="paid" <?php echo (isset($_GET['status']) && $_GET['status'] === 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                </select>
                                <select name="time_period" class="px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                                    <option value="">All Time</option>
                                    <option value="this_month" <?php echo (isset($_GET['time_period']) && $_GET['time_period'] === 'this_month') ? 'selected' : ''; ?>>This Month</option>
                                    <option value="last_month" <?php echo (isset($_GET['time_period']) && $_GET['time_period'] === 'last_month') ? 'selected' : ''; ?>>Last Month</option>
                                    <option value="this_year" <?php echo (isset($_GET['time_period']) && $_GET['time_period'] === 'this_year') ? 'selected' : ''; ?>>This Year</option>
                                </select>
                                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 flex items-center">
                                    <i class="fas fa-search mr-2"></i> Search
                                </button>
                                <?php if (!empty($_GET)): ?>
                                    <a href="fees.php" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 flex items-center">
                                        <i class="fas fa-times mr-2"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </form>

                            <!-- Success/Error Messages -->
                            <?php if (isset($_GET['success'])): ?>
                                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                                    <span class="block sm:inline"><?php echo htmlspecialchars($_GET['success']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($error)): ?>
                                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                                </div>
                            <?php endif; ?>

                            <!-- Fee Records Table -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Details</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment History</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Amount</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($payments)): ?>
                                            <tr>
                                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                                    No payments found matching your criteria
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($payments as $payment): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($payment['student_name']); ?></div>
                                                        <div class="text-sm text-gray-500">Roll No: <?php echo htmlspecialchars($payment['roll_number']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($payment['email']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <?php if (!empty($payment['payment_history'])): ?>
                                                            <div class="space-y-2">
                                                                <?php 
                                                                $payments = explode(';;', $payment['payment_history']);
                                                                foreach (array_slice($payments, 0, 3) as $p): 
                                                                    list($id, $amount, $date, $method, $receipt, $month, $year) = array_pad(explode('|', $p), 7, '');
                                                                    if (!empty($amount)):
                                                                ?>
                                                                    <div class="flex items-center justify-between text-sm">
                                                                        <div class="flex items-center flex-1">
                                                                            <?php if ($receipt): ?>
                                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">
                                                                                <i class="fas fa-check-circle mr-1"></i> Paid
                                                                            </span>
                                                                            <?php else: ?>
                                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-2">
                                                                                <i class="fas fa-clock mr-1"></i> Pending
                                                                            </span>
                                                                            <?php endif; ?>
                                                                            <span class="font-medium text-gray-900">₹<?php echo number_format($amount, 2); ?></span>
                                                                            <span class="text-gray-500 ml-2"><?php echo date('d M Y', strtotime($date)); ?></span>
                                                                        </div>
                                                                        <?php if ($receipt && $id): ?>
                                                                        <a href="#" 
                                                                           onclick="openReceiptPopup('../receipt.php?id=<?php echo $id; ?>'); return false;" 
                                                                           class="text-blue-600 hover:text-blue-900 inline-flex items-center ml-2">
                                                                            <i class="fas fa-receipt mr-1"></i> Receipt
                                                                        </a>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php 
                                                                    endif;
                                                                endforeach; 
                                                                ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-gray-500">No payment history</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="<?php echo $payment['due_amount'] > 0 ? 'text-red-600 font-semibold' : 'text-green-600'; ?>">
                                                            ₹<?php echo number_format($payment['due_amount'], 2); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <?php if ($payment['due_amount'] > 0): ?>
                                                            <button onclick="openRecordPaymentModal(<?php echo htmlspecialchars($payment['student_id']); ?>, '<?php echo htmlspecialchars($payment['student_name']); ?>', <?php echo $payment['due_amount']; ?>)" 
                                                                    class="text-green-600 hover:text-green-900 mr-3">
                                                                <i class="fas fa-plus"></i> Record Payment
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div id="recordPaymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Record New Payment</h3>
                <form method="POST" class="space-y-4" onsubmit="return validatePaymentForm()">
                    <input type="hidden" name="action" value="record_payment">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Student</label>
                        <select name="student_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select Student</option>
                            <?php
                            try {
                                $stmt = $conn->query("SELECT id, name, roll_number FROM users WHERE user_type = 'student' ORDER BY name");
                                while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$student['id']}\">{$student['name']} ({$student['roll_number']})</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=\"\">Error loading students</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Amount</label>
                        <input type="number" name="amount" required min="0" step="0.01"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment Date</label>
                        <input type="date" name="payment_date" required
                               value="<?php echo date('Y-m-d'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                        <select name="payment_method" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="card">Card</option>
                        </select>
                    </div>

                    <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Receipts Modal -->
    <div id="receiptsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-3/4 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Payment Receipts</h3>
                    <button onclick="closeModal('receiptsModal')" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Search and Filter in Receipts Modal -->
                <div class="mb-4 flex gap-4">
                    <div class="flex-1 relative">
                        <input type="text" id="receiptSearch" 
                               placeholder="Search by student name, receipt number..." 
                               class="w-full px-4 py-2 pr-12 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button onclick="loadReceipts()" type="button" 
                                class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1 text-gray-500 hover:text-indigo-600">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <select id="receiptMonthFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Months</option>
                        <?php
                        for ($i = 0; $i < 12; $i++) {
                            $month = date('F Y', strtotime("-$i months"));
                            echo "<option value=\"$month\">$month</option>";
                        }
                        ?>
                    </select>
                    <select id="receiptStatusFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Status</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                    </select>
                    <button onclick="loadReceipts()" type="button" 
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 flex items-center">
                        <i class="fas fa-search mr-2"></i> Search
                    </button>
                    <button onclick="clearReceiptFilters()" type="button" 
                            class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 flex items-center">
                        <i class="fas fa-times mr-2"></i> Clear
                    </button>
                </div>

                <!-- Receipts Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="receiptsTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Receipts will be loaded here dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Options Modal -->
    <div id="exportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Export Report</h3>
                    <button onclick="closeModal('exportModal')" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" id="exportForm" class="space-y-4">
                    <input type="hidden" name="action" value="export_report">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Time Period</label>
                        <select name="time_period" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all">All Time</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_year">This Year</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment Status</label>
                        <select name="payment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all">All Status</option>
                            <option value="paid">Paid Only</option>
                            <option value="pending">Pending Only</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('exportModal')"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Export CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar
        const toggleBtn = document.querySelector('button.md\\:hidden');
        const sidebar = document.querySelector('.sidebar');
        
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768) {
                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                    sidebar.classList.add('-translate-x-full');
                }
            }
        });

        // Add smooth scroll behavior
        document.querySelector('main').style.scrollBehavior = 'smooth';

        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            return true;
        }

        function openRecordPaymentModal(studentId, studentName, dueAmount) {
            const modal = document.getElementById('recordPaymentModal');
            const studentSelect = modal.querySelector('select[name="student_id"]');
            const amountInput = modal.querySelector('input[name="amount"]');
            
            // Set the selected student
            for (let option of studentSelect.options) {
                if (option.value == studentId) {
                    option.selected = true;
                    break;
                }
            }
            
            // Set the due amount as default
            amountInput.value = dueAmount;
            amountInput.max = dueAmount;
            
            modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function validatePaymentForm() {
            const amount = document.querySelector('input[name="amount"]').value;
            const paymentDate = document.querySelector('input[name="payment_date"]').value;
            
            if (amount <= 0) {
                alert('Please enter a valid amount');
                return false;
            }

            if (new Date(paymentDate) > new Date()) {
                alert('Payment date cannot be in the future');
                return false;
            }

            return true;
        }

        function viewAllPayments(studentData) {
            // Implement a modal to show all payments for a student
            alert('All payments for ' + studentData.student_name + ' will be shown here');
        }

        function openReceiptsModal() {
            const modal = document.getElementById('receiptsModal');
            modal.classList.remove('hidden');
            loadReceipts();
        }

        function loadReceipts() {
            const search = document.getElementById('receiptSearch').value;
            const month = document.getElementById('receiptMonthFilter').value;
            const status = document.getElementById('receiptStatusFilter').value;

            fetch(`get_receipts.php?search=${encodeURIComponent(search)}&month=${encodeURIComponent(month)}&status=${encodeURIComponent(status)}`)
                .then(response => response.json())
                .then(receipts => {
                    const tbody = document.getElementById('receiptsTableBody');
                    tbody.innerHTML = '';

                    receipts.forEach(receipt => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${receipt.receipt_number}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${receipt.student_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₹${parseFloat(receipt.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(receipt.payment_date).toLocaleDateString('en-IN', {day: '2-digit', month: 'long', year: 'numeric'})}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
                                    receipt.status === 'paid' 
                                    ? 'bg-green-100 text-green-800' 
                                    : 'bg-yellow-100 text-yellow-800'
                                }">
                                    ${
                                        receipt.status === 'paid'
                                        ? '<i class="fas fa-check-circle mr-1"></i> Paid'
                                        : '<i class="fas fa-clock mr-1"></i> Pending'
                                    }
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewReceipt('${receipt.receipt_number}', '${receipt.student_name}', ${receipt.amount}, '${receipt.payment_date}', '${receipt.payment_method}', '${receipt.roll_number}')" 
                                        class="text-indigo-600 hover:text-indigo-900">
                                    <i class="fas fa-eye mr-1"></i> View
                                </button>
                                <button onclick="printReceipt('${receipt.receipt_number}')" 
                                        class="text-green-600 hover:text-green-900 ml-3">
                                    <i class="fas fa-print mr-1"></i> Print
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                })
                .catch(error => console.error('Error loading receipts:', error));
        }

        function clearReceiptFilters() {
            document.getElementById('receiptSearch').value = '';
            document.getElementById('receiptMonthFilter').value = '';
            document.getElementById('receiptStatusFilter').value = '';
            loadReceipts();
        }

        // Update the event listeners for receipt search
        document.getElementById('receiptSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadReceipts();
            }
        });

        // Remove the old input event listener and keep only the button click
        document.getElementById('receiptSearch').removeEventListener('input', debounce(loadReceipts, 300));

        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close notifications dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('notificationsDropdown');
            const button = document.querySelector('button[onclick="toggleNotifications()"]');
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        function openExportModal() {
            const modal = document.getElementById('exportModal');
            modal.classList.remove('hidden');
        }

        // Add this to your existing script section
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const params = new URLSearchParams(formData);
            window.location.href = 'export_fee_report.php?' + params.toString();
            closeModal('exportModal');
        });

        function openReceiptPopup(url) {
            // Center the popup window
            const width = 800;
            const height = 800;
            const left = (window.innerWidth - width) / 2;
            const top = (window.innerHeight - height) / 2;
            
            // Open popup with specific size and position
            window.open(url, 'receiptPopup', 
                `width=${width},
                 height=${height},
                 top=${top},
                 left=${left},
                 menubar=no,
                 toolbar=no,
                 location=no,
                 status=no,
                 scrollbars=yes`
            );
        }
    </script>
</body>
</html> 