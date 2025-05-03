<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db_connect.php';

// Get fee details
$fee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$fee_id) {
    header("Location: fees.php");
    exit();
}

try {
    // Get fee details
    $stmt = $conn->prepare("
        SELECT f.*, s.roll_number, u.full_name 
        FROM fees f 
        JOIN students s ON f.student_id = s.id 
        JOIN users u ON s.user_id = u.id 
        WHERE f.id = ? AND f.student_id = (
            SELECT id FROM students WHERE user_id = ?
        )
    ");
    $stmt->execute([$fee_id, $_SESSION['user_id']]);
    $fee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fee) {
        $_SESSION['error'] = "Invalid fee selected";
        header("Location: fees.php");
        exit();
    }

    // Handle payment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
        $transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_SANITIZE_STRING);

        // Start transaction
        $conn->beginTransaction();

        // Update fee status
        $stmt = $conn->prepare("
            UPDATE fees 
            SET payment_status = 'paid',
                payment_method = ?,
                transaction_id = ?,
                payment_date = CURRENT_DATE
            WHERE id = ?
        ");
        $stmt->execute([$payment_method, $transaction_id, $fee_id]);

        // Create payment record
        $stmt = $conn->prepare("
            INSERT INTO payments (
                fee_id, 
                amount, 
                payment_method, 
                transaction_id, 
                payment_date
            ) VALUES (?, ?, ?, ?, CURRENT_DATE)
        ");
        $stmt->execute([$fee_id, $fee['amount'], $payment_method, $transaction_id]);

        $conn->commit();
        $_SESSION['success'] = "Payment processed successfully";
        header("Location: fees.php");
        exit();
    }

} catch (PDOException $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
    header("Location: fees.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Hostel Management System</title>
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
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Payment Form -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Make Payment</h2>

                        <!-- Fee Details -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Student Name</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($fee['full_name']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Roll Number</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($fee['roll_number']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Fee Type</p>
                                    <p class="font-medium"><?php echo ucfirst(htmlspecialchars($fee['fee_type'])); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Amount</p>
                                    <p class="font-medium">₹<?php echo number_format($fee['amount'], 2); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Due Date</p>
                                    <p class="font-medium"><?php echo date('d M Y', strtotime($fee['due_date'])); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Form -->
                        <form method="POST" class="space-y-6">
                            <!-- Payment Method -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                                <div class="grid grid-cols-3 gap-4">
                                    <label class="relative flex">
                                        <input type="radio" name="payment_method" value="cash" class="sr-only peer" required>
                                        <div class="w-full p-4 text-gray-600 bg-white border rounded-lg cursor-pointer peer-checked:border-indigo-600 peer-checked:text-indigo-600 hover:text-gray-600 hover:bg-gray-100">
                                            <div class="flex items-center justify-center">
                                                <i class="fas fa-money-bill-wave mr-2"></i>
                                                <span>Cash</span>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="relative flex">
                                        <input type="radio" name="payment_method" value="online_transfer" class="sr-only peer" required>
                                        <div class="w-full p-4 text-gray-600 bg-white border rounded-lg cursor-pointer peer-checked:border-indigo-600 peer-checked:text-indigo-600 hover:text-gray-600 hover:bg-gray-100">
                                            <div class="flex items-center justify-center">
                                                <i class="fas fa-university mr-2"></i>
                                                <span>Online</span>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="relative flex">
                                        <input type="radio" name="payment_method" value="cheque" class="sr-only peer" required>
                                        <div class="w-full p-4 text-gray-600 bg-white border rounded-lg cursor-pointer peer-checked:border-indigo-600 peer-checked:text-indigo-600 hover:text-gray-600 hover:bg-gray-100">
                                            <div class="flex items-center justify-center">
                                                <i class="fas fa-money-check mr-2"></i>
                                                <span>Cheque</span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Transaction ID -->
                            <div>
                                <label for="transaction_id" class="block text-sm font-medium text-gray-700">Transaction ID / Reference Number</label>
                                <input type="text" name="transaction_id" id="transaction_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <p class="mt-1 text-sm text-gray-500">Please enter the transaction ID or reference number for your payment.</p>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end space-x-4">
                                <a href="fees.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Cancel
                                </a>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Process Payment
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