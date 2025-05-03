<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/db_connect.php';

$success_message = '';
$error_message = '';

// Get student ID
try {
    $stmt = $conn->prepare("SELECT s.id FROM students s JOIN users u ON s.user_id = u.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $error_message = "Student profile not found. Please contact the administrator.";
    }
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student) {
    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validate input
    if (empty($subject) || empty($category) || empty($description)) {
        $error_message = "All fields are required.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO complaints (student_id, subject, category, description)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$student['id'], $subject, $category, $description]);
            $success_message = "Complaint submitted successfully!";
            
            // Clear form data after successful submission
            $subject = $category = $description = '';
        } catch (PDOException $e) {
            $error_message = "Error submitting complaint: " . $e->getMessage();
        }
    }
}

// Fetch existing complaints
try {
    $complaints = [];
    if ($student) {
        $stmt = $conn->prepare("
            SELECT * FROM complaints 
            WHERE student_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$student['id']]);
        $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "Error fetching complaints: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - Hostel Management System</title>
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
                        <a href="dashboard.php" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Submit a Complaint</h2>

                <?php if ($success_message): ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Complaint Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <form action="complaints.php" method="POST" class="space-y-6">
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                            <input type="text" id="subject" name="subject" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value="<?php echo htmlspecialchars($subject ?? ''); ?>">
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="category" name="category" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select Category</option>
                                <option value="maintenance" <?php echo ($category ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="roommate" <?php echo ($category ?? '') === 'roommate' ? 'selected' : ''; ?>>Roommate</option>
                                <option value="facilities" <?php echo ($category ?? '') === 'facilities' ? 'selected' : ''; ?>>Facilities</option>
                                <option value="mess" <?php echo ($category ?? '') === 'mess' ? 'selected' : ''; ?>>Mess</option>
                                <option value="other" <?php echo ($category ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="description" name="description" rows="4" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Submit Complaint
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Complaints History -->
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Complaints History</h3>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted On</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($complaints)): ?>
                                <?php foreach ($complaints as $complaint): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($complaint['subject']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo ucfirst(htmlspecialchars($complaint['category'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusColors = [
                                                'pending' => 'yellow',
                                                'in_progress' => 'blue',
                                                'resolved' => 'green',
                                                'rejected' => 'red'
                                            ];
                                            $color = $statusColors[$complaint['status']];
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800">
                                                <?php echo ucfirst(htmlspecialchars($complaint['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($complaint['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No complaints found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
</body>
</html> 