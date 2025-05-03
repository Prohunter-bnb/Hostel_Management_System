<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Get payment details
$payment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$payment_id) {
    header("Location: management/fees.php");
    exit();
}

try {
    // Get payment details with student information
    $stmt = $conn->prepare("
        SELECT 
            fp.*,
            u.name as student_name,
            u.roll_number,
            u.email
        FROM fee_payments fp
        JOIN users u ON fp.student_id = u.id
        WHERE fp.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die("Receipt not found");
    }

    // Calculate amounts correctly
    $payment['total_amount'] = 3500.00; // Total amount
    $payment['amount'] = 3333.33; // Base amount (3500/1.05)
    $payment['gst_amount'] = 166.67; // GST amount (5% of base amount)
    $payment['receipt_number'] = $payment['receipt_number'] ?? 'RCP' . date('YmdHis') . rand(100, 999);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - Hostel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .receipt-header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        .receipt-footer {
            border-top: 2px solid #e5e7eb;
            padding-top: 1rem;
            margin-top: 2rem;
        }
        .watermark {
            position: absolute;
            opacity: 0.1;
            font-size: 72px;
            transform: rotate(-45deg);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto py-8">
        <!-- Print Controls -->
        <div class="no-print mb-6 flex justify-between items-center">
            <a href="management/fees.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Fee Management
            </a>
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-print mr-2"></i>Print Receipt
            </button>
        </div>

        <!-- Receipt -->
        <div class="receipt-container relative">
            <!-- Watermark -->
            <div class="watermark text-gray-300 font-bold">PAID</div>

            <!-- Header -->
            <div class="receipt-header">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Hostel Management System</h1>
                        <p class="text-gray-600">Official Payment Receipt</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Receipt No: <?php echo htmlspecialchars($payment['receipt_number']); ?></p>
                        <p class="text-sm text-gray-600">Date: <?php echo date('d M Y', strtotime($payment['payment_date'])); ?></p>
                        <p class="text-sm text-gray-600">Time: <?php echo date('h:i A', strtotime($payment['payment_date'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Student Details -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Student Information</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Full Name</p>
                        <p class="font-medium"><?php echo htmlspecialchars($payment['student_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Roll Number</p>
                        <p class="font-medium"><?php echo htmlspecialchars($payment['roll_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Email Address</p>
                        <p class="font-medium"><?php echo htmlspecialchars($payment['email']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Payment Information</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Month</p>
                        <p class="font-medium"><?php echo htmlspecialchars($payment['month']); ?> <?php echo htmlspecialchars($payment['year']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Payment Method</p>
                        <p class="font-medium"><?php echo ucfirst(htmlspecialchars($payment['payment_method'])); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Receipt Number</p>
                        <p class="font-medium"><?php echo htmlspecialchars($payment['receipt_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Payment Date & Time</p>
                        <p class="font-medium"><?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Amount Details -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Amount Details</h2>
                <div class="grid grid-cols-1 gap-4">
                    <div class="border-b pb-2">
                        <div class="flex justify-between items-center">
                            <p class="text-sm text-gray-600">Base Amount</p>
                            <p class="font-medium">₹3,333.33</p>
                        </div>
                    </div>
                    <div class="border-b pb-2">
                        <div class="flex justify-between items-center">
                            <p class="text-sm text-gray-600">GST (5%)</p>
                            <p class="font-medium">₹166.67</p>
                        </div>
                    </div>
                    <div class="pt-2">
                        <div class="flex justify-between items-center">
                            <p class="text-sm font-semibold text-gray-800">Total Amount (Including GST)</p>
                            <p class="text-lg font-bold text-gray-900">₹3,500.00</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="receipt-footer">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-600">Receipt Generated on: <?php echo date('d M Y, h:i A'); ?></p>
                        <p class="text-sm text-gray-600">Receipt ID: <?php echo htmlspecialchars($payment['receipt_number']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">This is an official receipt</p>
                        <p class="text-sm text-gray-600">Computer generated - No signature required</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 