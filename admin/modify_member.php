<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_connect.php';

// Get statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'recent' => 0,
    'departments' => []
];

// Get total students
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'student'");
$stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get students with rooms (active)
$stmt = $conn->query("SELECT COUNT(DISTINCT u.id) as active 
                     FROM users u 
                     INNER JOIN room_allocations ra ON u.id = ra.student_id 
                     WHERE u.user_type = 'student'");
$stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

// Get recent updates in last 7 days
$stmt = $conn->query("SELECT COUNT(*) as recent FROM users 
                     WHERE user_type = 'student' 
                     AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['recent'] = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];

// Get student details for department statistics
$stmt = $conn->query("SELECT s.department, COUNT(*) as count 
                     FROM users u 
                     INNER JOIN students s ON u.id = s.user_id
                     WHERE u.user_type = 'student' 
                     AND s.department IS NOT NULL 
                     GROUP BY s.department 
                     ORDER BY count DESC 
                     LIMIT 5");
$stats['departments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent updates with student details
$stmt = $conn->query("SELECT u.*, s.department, s.roll_number 
                     FROM users u 
                     LEFT JOIN students s ON u.id = s.user_id
                     WHERE u.user_type = 'student' 
                     ORDER BY u.updated_at DESC 
                     LIMIT 5");
$recentUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle search
$searchResults = [];
$searchPerformed = false;
$searchType = isset($_GET['search_type']) ? $_GET['search_type'] : 'roll';
$search = isset($_GET['search']) ? $_GET['search'] : '';

if (!empty($search)) {
    $searchPerformed = true;
    try {
        if ($searchType === 'name') {
            $stmt = $conn->prepare("SELECT u.*, s.department, s.roll_number 
                     FROM users u 
                     LEFT JOIN students s ON u.id = s.user_id 
                                  WHERE u.user_type = 'student' 
                                  AND u.name LIKE ? 
                                  ORDER BY u.name 
                                  LIMIT 10");
            $stmt->execute(['%' . $search . '%']);
        } else {
            $stmt = $conn->prepare("SELECT u.*, s.department, s.roll_number 
                     FROM users u 
                     LEFT JOIN students s ON u.id = s.user_id 
                                  WHERE u.user_type = 'student' 
                                  AND s.roll_number = ?");
            $stmt->execute([$search]);
        }
        $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modify Members - HMS</title>
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

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
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
                <a href="dashboard.php" class="sidebar-link mb-3">
                    <i class="fas fa-chart-line w-5 h-5 mr-3"></i>
                    <span>Dashboard</span>
                </a>

                <div class="mb-6">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Members</p>
                    <a href="add_member.php" class="sidebar-link mb-2">
                        <i class="fas fa-user-plus w-5 h-5 mr-3"></i>
                        <span>Add Student</span>
                    </a>
                    <a href="view_members.php" class="sidebar-link mb-2">
                        <i class="fas fa-users w-5 h-5 mr-3"></i>
                        <span>View Members</span>
                    </a>
                    <a href="modify_member.php" class="sidebar-link active">
                        <i class="fas fa-user-edit w-5 h-5 mr-3"></i>
                        <span>Modify Members</span>
                    </a>
                </div>

                <div>
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Management</p>
                    <a href="management/room_management.php" class="sidebar-link mb-2">
                        <i class="fas fa-door-open w-5 h-5 mr-3"></i>
                        <span>Room Management</span>
                    </a>
                    <a href="management/allocation.php" class="sidebar-link mb-2">
                        <i class="fas fa-bed w-5 h-5 mr-3"></i>
                        <span>Room Allocation</span>
                    </a>
                    <a href="management/fees.php" class="sidebar-link">
                        <i class="fas fa-money-bill-wave w-5 h-5 mr-3"></i>
                        <span>Fee Management</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="header h-16 flex items-center justify-between px-6 border-b border-gray-200 bg-white">
                <div class="flex items-center">
                    <button class="text-gray-500 hover:text-gray-600 focus:outline-none md:hidden">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-2xl font-bold text-gray-800 ml-6">Modify Members</h2>
                </div>

                <?php include 'components/profile_dropdown.php'; ?>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <div class="stat-card bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-100 rounded-full p-3">
                                    <i class="fas fa-users text-blue-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Members</p>
                                    <h3 class="text-xl font-semibold text-gray-900"><?php echo $stats['total']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-100 rounded-full p-3">
                                    <i class="fas fa-user-check text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Active Members</p>
                                    <h3 class="text-xl font-semibold text-gray-900"><?php echo $stats['active']; ?></h3>
                                </div>
                            </div>
                                    </div>
                        <div class="stat-card bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-purple-100 rounded-full p-3">
                                    <i class="fas fa-clock text-purple-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Recent Updates</p>
                                    <h3 class="text-xl font-semibold text-gray-900"><?php echo $stats['recent']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                                                        <div class="flex items-center">
                                <div class="flex-shrink-0 bg-yellow-100 rounded-full p-3">
                                    <i class="fas fa-graduation-cap text-yellow-600 text-xl"></i>
                                                            </div>
                                                            <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Departments</p>
                                    <h3 class="text-xl font-semibold text-gray-900"><?php echo count($stats['departments']); ?></h3>
                                    </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Search and Edit Section -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-800 mb-6">Search Members</h3>
                                <!-- Search Form -->
                                <form method="GET" class="mb-6">
                                    <div class="space-y-4">
                                        <div class="flex space-x-4">
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="search_type" value="roll" class="form-radio text-blue-600" 
                                                       <?php echo $searchType === 'roll' ? 'checked' : ''; ?>>
                                                <span class="ml-2">Search by Roll Number</span>
                                            </label>
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="search_type" value="name" class="form-radio text-blue-600"
                                                       <?php echo $searchType === 'name' ? 'checked' : ''; ?>>
                                                <span class="ml-2">Search by Name</span>
                                            </label>
                                        </div>
                                        <div class="flex gap-4">
                                            <div class="flex-1 relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <i class="fas fa-search text-gray-400"></i>
                                                </div>
                                                <input type="text" name="search" 
                                                       placeholder="<?php echo $searchType === 'name' ? 'Enter student name...' : 'Enter roll number...'; ?>" 
                                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                       value="<?php echo htmlspecialchars($search); ?>">
                                            </div>
                                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                                Search
                                            </button>
                                        </div>
                                    </div>
                                </form>

                                <?php if ($searchPerformed): ?>
                                    <?php if (!empty($searchResults)): ?>
                                        <div class="space-y-4">
                                            <?php foreach ($searchResults as $student): ?>
                                                <div class="border rounded-lg p-4 hover:bg-gray-50 transition duration-150">
                                                    <div class="flex items-center justify-between">
                                                        <div>
                                                            <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($student['name']); ?></h4>
                                                            <p class="text-sm text-gray-500">Roll: <?php echo htmlspecialchars($student['roll_number']); ?></p>
                                                            <?php if ($student['department']): ?>
                                                                <p class="text-sm text-gray-500">Department: <?php echo htmlspecialchars($student['department']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <a href="?search=<?php echo urlencode($student['roll_number']); ?>&search_type=roll" 
                                                           class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition duration-150">
                                                            Edit Details
                                    </a>
                                </div>
                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                            <div class="flex items-center text-red-600 mb-2">
                                                <i class="fas fa-exclamation-circle mr-2"></i>
                                                <span class="font-medium">No Results Found</span>
                                            </div>
                                            <p class="text-sm text-red-600">No students found matching your search criteria.</p>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (isset($_GET['search']) && !empty($_GET['search']) && $searchType === 'roll' && !empty($searchResults)): ?>
                                    <div class="mt-6">
                                        <h4 class="text-lg font-semibold text-gray-800 mb-4">Edit Member Details</h4>
                                        <form action="update_member.php" method="POST" class="space-y-6">
                                            <input type="hidden" name="id" value="<?php echo $searchResults[0]['id']; ?>">
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div class="form-group">
                                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                                        Full Name
                                                        <span class="text-red-500">*</span>
                                        </label>
                                                    <input type="text" name="name" value="<?php echo htmlspecialchars($searchResults[0]['name']); ?>"
                                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                               required>
                                    </div>

                                                <div class="form-group">
                                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                                        Email Address
                                                        <span class="text-red-500">*</span>
                                        </label>
                                                    <input type="email" name="email" value="<?php echo htmlspecialchars($searchResults[0]['email']); ?>"
                                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                               required>
                                    </div>

                                                <div class="form-group">
                                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                                        Roll Number
                                                        <span class="text-red-500">*</span>
                                        </label>
                                                    <input type="text" name="roll_number" value="<?php echo htmlspecialchars($searchResults[0]['roll_number']); ?>"
                                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                           required>
                                    </div>

                                                <div class="form-group">
                                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                                        Department
                                                        <span class="text-red-500">*</span>
                                        </label>
                                                    <input type="text" name="department" value="<?php echo htmlspecialchars($searchResults[0]['department']); ?>"
                                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                           required>
                                    </div>

                                                <div class="md:col-span-2">
                                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                                        New Password
                                                    </label>
                                                    <input type="password" name="password"
                                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                           placeholder="Leave blank to keep current password">
                                                    <p class="mt-1 text-sm text-gray-500">Only fill this if you want to change the password</p>
                                </div>

                                                <div class="md:col-span-2 flex justify-end space-x-3">
                                                    <button type="reset" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                                        Reset Form
                                                    </button>
                                                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                                        Update Member
                                                    </button>
                                                </div>
                                        </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                                        </div>

                        <!-- Right Sidebar -->
                        <div class="lg:col-span-1 space-y-6">
                            <!-- Department Statistics -->
                            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Department Statistics</h3>
                                <div class="space-y-4">
                                    <?php foreach ($stats['departments'] as $dept): ?>
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm text-gray-600"><?php echo htmlspecialchars($dept['department']); ?></span>
                                                <span class="text-sm font-medium text-gray-900"><?php echo $dept['count']; ?> students</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo ($dept['count'] / $stats['total'] * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                </div>

                            <!-- Recent Updates -->
                            <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Updates</h3>
                                <div class="space-y-4">
                                    <?php foreach ($recentUpdates as $update): ?>
                                        <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                            <div class="flex-shrink-0">
                                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user-edit text-blue-600"></i>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                    <?php echo htmlspecialchars($update['name']); ?>
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    Roll: <?php echo htmlspecialchars($update['roll_number']); ?>
                                                </p>
                                                <p class="text-xs text-gray-400">
                                                    Updated: <?php echo date('M j, Y', strtotime($update['updated_at'])); ?>
                                                </p>
                                            </div>
                                            <a href="?search=<?php echo urlencode($update['roll_number']); ?>&search_type=roll" 
                                               class="flex-shrink-0 text-blue-600 hover:text-blue-700">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
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

        // Form validation
        const form = document.querySelector('form[action="update_member.php"]');
        if (form) {
            form.addEventListener('submit', (e) => {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('border-red-500');
                    } else {
                        field.classList.remove('border-red-500');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields');
                }
            });
        }
    </script>
</body>
</html>

