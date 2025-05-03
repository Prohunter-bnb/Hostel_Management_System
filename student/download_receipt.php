<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db_connect.php';
require_once '../vendor/autoload.php'; // Make sure you have composer and dompdf installed

use Dompdf\Dompdf;
use Dompdf\Options;

// Get payment details
$payment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$payment_id) {
    header("Location: fees.php");
    exit();
}

try {
    // Get payment details with student information
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            f.fee_type,
            f.amount as fee_amount,
            s.roll_number,
            s.department,
            u.name as student_name,
            u.email
        FROM payments p
        JOIN fees f ON p.fee_id = f.id
        JOIN students s ON f.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE p.id = ? AND s.user_id = ?
    ");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $_SESSION['error'] = "Invalid payment receipt";
        header("Location: fees.php");
        exit();
    }

    // Generate HTML for the receipt
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .receipt-container { max-width: 800px; margin: 0 auto; padding: 20px; }
            .receipt-header { border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 20px; }
            .receipt-footer { border-top: 2px solid #e5e7eb; padding-top: 10px; margin-top: 20px; }
            .section { margin-bottom: 20px; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .text-sm { font-size: 12px; }
            .text-gray-600 { color: #4B5563; }
            .font-medium { font-weight: 500; }
            .mb-4 { margin-bottom: 16px; }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="receipt-header">
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <h1 style="font-size: 24px; font-weight: bold; margin: 0;">Hostel Management System</h1>
                        <p style="color: #4B5563; margin: 0;">Payment Receipt</p>
                    </div>
                    <div style="text-align: right;">
                        <p class="text-sm text-gray-600">Receipt No: ' . $payment['id'] . '</p>
                        <p class="text-sm text-gray-600">Date: ' . date('d M Y', strtotime($payment['payment_date'])) . '</p>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Student Details</h2>
                <div class="grid">
                    <div>
                        <p class="text-sm text-gray-600">Name</p>
                        <p class="font-medium">' . htmlspecialchars($payment['student_name']) . '</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Roll Number</p>
                        <p class="font-medium">' . htmlspecialchars($payment['roll_number']) . '</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Department</p>
                        <p class="font-medium">' . htmlspecialchars($payment['department']) . '</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Email</p>
                        <p class="font-medium">' . htmlspecialchars($payment['email']) . '</p>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Payment Details</h2>
                <div class="grid">
                    <div>
                        <p class="text-sm text-gray-600">Fee Type</p>
                        <p class="font-medium">' . ucfirst(htmlspecialchars($payment['fee_type'])) . '</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Payment Method</p>
                        <p class="font-medium">' . ucfirst(htmlspecialchars($payment['payment_method'])) . '</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Transaction ID</p>
                        <p class="font-medium">' . htmlspecialchars($payment['transaction_id']) . '</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Payment Date</p>
                        <p class="font-medium">' . date('d M Y', strtotime($payment['payment_date'])) . '</p>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Amount Details</h2>
                <div class="grid">
                    <div>
                        <p class="text-sm text-gray-600">Fee Amount</p>
                        <p class="font-medium">₹' . number_format($payment['fee_amount'], 2) . '</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Amount Paid</p>
                        <p class="font-medium">₹' . number_format($payment['amount'], 2) . '</p>
                    </div>
                </div>
            </div>

            <div class="receipt-footer">
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <p class="text-sm text-gray-600">Generated on: ' . date('d M Y, h:i A') . '</p>
                    </div>
                    <div style="text-align: right;">
                        <p class="text-sm text-gray-600">This is a computer generated receipt</p>
                        <p class="text-sm text-gray-600">No signature required</p>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';

    // Configure Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    // Create PDF
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Output the generated PDF
    $dompdf->stream("receipt_" . $payment_id . ".pdf", array("Attachment" => true));

} catch (PDOException $e) {
    $_SESSION['error'] = "Error generating receipt: " . $e->getMessage();
    header("Location: fees.php");
    exit();
}
?> 