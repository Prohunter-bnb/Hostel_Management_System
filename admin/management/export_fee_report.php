<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}

require_once '../../config/db_connect.php';

// Get export parameters
$timePeriod = $_GET['time_period'] ?? 'all';
$paymentStatus = $_GET['payment_status'] ?? 'all';

// Build the query with filters
$query = "
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
    WHERE 1=1
";

$params = [];

// Add time period filter
if ($timePeriod !== 'all') {
    switch ($timePeriod) {
        case 'this_month':
            $query .= " AND DATE_FORMAT(fp.payment_date, '%Y-%m') = ?";
            $params[] = date('Y-m');
            break;
        case 'last_month':
            $query .= " AND DATE_FORMAT(fp.payment_date, '%Y-%m') = ?";
            $params[] = date('Y-m', strtotime('-1 month'));
            break;
        case 'this_year':
            $query .= " AND YEAR(fp.payment_date) = ?";
            $params[] = date('Y');
            break;
    }
}

// Add payment status filter
if ($paymentStatus !== 'all') {
    $query .= " AND fp.status = ?";
    $params[] = $paymentStatus;
}

$query .= " ORDER BY fp.payment_date DESC, u.name ASC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fee_payments_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
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
    $_SESSION['error'] = "Error exporting report: " . $e->getMessage();
    header("Location: fees.php");
    exit();
}
?> 