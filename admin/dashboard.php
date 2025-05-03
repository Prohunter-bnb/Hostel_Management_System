<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
include '../config/db_connect.php';

// Fetch quick statistics
try {
    // Total members
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type != 'admin'");
    $totalMembers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Recent members (last 7 days)
    $stmt = $conn->query("SELECT COUNT(*) as recent FROM users WHERE user_type != 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recentMembers = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];

    // Recent activities
    $stmt = $conn->query("SELECT u.name, u.email, u.created_at FROM users u WHERE u.user_type != 'admin' ORDER BY u.created_at DESC LIMIT 5");
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hostel Management System</title>
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

        .stat-card {
            background: var(--surface);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.1);
        }

        .header {
            background-color: var(--surface);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .search-input {
            background-color: var(--background);
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            width: 300px;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .quick-action {
            background: var(--surface);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.2s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }

        .activity-item {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            background-color: rgba(37, 99, 235, 0.05);
        }

        .avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
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
            min-width: 0;
            max-width: 100%;
            padding: 1rem;
        }
        
        @media (min-width: 640px) {
            .stat-card {
                max-width: none;
            }
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
                <a href="dashboard.php" class="sidebar-link active mb-3">
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
                    <a href="modify_member.php" class="sidebar-link">
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
                    <div class="relative ml-6">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <i class="fas fa-search text-gray-400"></i>
                        </span>
                        <input class="search-input" type="text" placeholder="Search...">
                    </div>
                </div>

                <?php include 'components/profile_dropdown.php'; ?>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="max-w-7xl mx-auto">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
                        <div class="flex space-x-4">
                            <a href="bulk_add_members.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors">
                                <i class="fas fa-users mr-2"></i>Add 200 Members
                            </a>
                            <a href="export_members.php" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-file-export mr-2"></i>Export Report
                            </a>
                        </div>
                    </div>

                    <!-- Statistics Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <!-- Total Students Card -->
                        <div class="stat-card">
                            <div class="flex items-center justify-between mb-2">
                                <div class="rounded-full p-2 bg-blue-100">
                                    <i class="fas fa-users text-blue-600 text-lg"></i>
                                </div>
                                <span class="text-xs font-medium text-green-600">+<?php echo $recentMembers; ?> new</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-1"><?php echo $totalMembers; ?></h3>
                            <p class="text-sm text-gray-600">Total Students</p>
                        </div>

                        <!-- Available Rooms Card -->
                        <div class="stat-card">
                            <div class="flex items-center justify-between mb-2">
                                <div class="rounded-full p-2 bg-green-100">
                                    <i class="fas fa-door-open text-green-600 text-lg"></i>
                                </div>
                                <span class="text-xs font-medium text-green-600">+2 today</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-1">
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'");
                                    echo $stmt->fetchColumn();
                                } catch (PDOException $e) {
                                    echo '0';
                                }
                                ?>
                            </h3>
                            <p class="text-sm text-gray-600">Available Rooms</p>
                        </div>

                        <!-- Occupied Rooms Card -->
                        <div class="stat-card">
                            <div class="flex items-center justify-between mb-2">
                                <div class="rounded-full p-2 bg-yellow-100">
                                    <i class="fas fa-bed text-yellow-600 text-lg"></i>
                                </div>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-1">
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'");
                                    echo $stmt->fetchColumn();
                                } catch (PDOException $e) {
                                    echo '0';
                                }
                                ?>
                            </h3>
                            <p class="text-sm text-gray-600">Occupied Rooms</p>
                        </div>

                        <!-- Pending Requests Card -->
                        <div class="stat-card">
                            <div class="flex items-center justify-between mb-2">
                                <div class="rounded-full p-2 bg-purple-100">
                                    <i class="fas fa-clock text-purple-600 text-lg"></i>
                                </div>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-1">
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT COUNT(*) FROM room_requests WHERE status = 'pending'");
                                    echo $stmt->fetchColumn();
                                } catch (PDOException $e) {
                                    echo '0';
                                }
                                ?>
                            </h3>
                            <p class="text-sm text-gray-600">Pending Requests</p>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-semibold text-gray-700">Quick Actions</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <a href="add_member.php" class="flex items-center p-4 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                <i class="fas fa-user-plus text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Add New Student</p>
                                <p class="text-sm text-gray-500">Register a new student</p>
                            </div>
                        </a>

                        <button onclick="if(confirm('Are you sure you want to add 200 sample students?')) window.location.href='database/run_sample_data.php';" 
                                class="flex items-center p-4 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                            <div class="p-3 rounded-full bg-green-100 text-green-500">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Add Sample Students</p>
                                <p class="text-sm text-gray-500">Add 200 sample records</p>
                            </div>
                        </button>

                        <a href="management/room_management.php" class="flex items-center p-4 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                                <i class="fas fa-door-open text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Room Management</p>
                                <p class="text-sm text-gray-500">Manage hostel rooms</p>
                            </div>
                        </a>

                        <a href="management/fees.php" class="flex items-center p-4 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                                <i class="fas fa-money-bill-wave text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Fee Management</p>
                                <p class="text-sm text-gray-500">Manage student fees</p>
                            </div>
                        </a>
                    </div>

                    <!-- Recent Activity -->
                    <div class="grid grid-cols-1">
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
                                <a href="view_members.php" class="text-sm text-primary hover:text-primary-dark">View all</a>
                            </div>
                            <div class="space-y-4">
                                <?php if (!empty($recentActivities)): ?>
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="flex items-center">
                                                <div class="avatar mr-4">
                                                    <?php echo strtoupper(substr($activity['name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-1">
                                                    <h4 class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($activity['name']); ?></h4>
                                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($activity['email']); ?></p>
                                                </div>
                                                <span class="text-sm text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($activity['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-gray-500 text-center py-4">No recent activity</p>
                                <?php endif; ?>
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

        // Add smooth scroll behavior
        document.querySelector('main').style.scrollBehavior = 'smooth';
    </script>
</body>
</html>
