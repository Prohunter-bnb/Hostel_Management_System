<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_connect.php';

// Fetch member statistics
try {
    $stmt = $conn->query("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type");
    $userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalMembers = 0;
    $memberCounts = [];
    foreach ($userStats as $stat) {
        $totalMembers += $stat['count'];
        $memberCounts[$stat['user_type']] = $stat['count'];
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
}

// Handle member deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = filter_var($_GET['delete'], FILTER_SANITIZE_NUMBER_INT);
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Member deleted successfully.";
        header("Location: view_members.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting member: " . $e->getMessage();
    }
}

// Fetch all members with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get sort parameters
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'asc';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';

try {
    // Build the query
    $where_clause = "";
    $params = [];
    
    if (!empty($filter_type)) {
        $where_clause = " WHERE user_type = ?";
        $params[] = $filter_type;
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM users" . $where_clause;
    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Determine sort column and direction
    $sort_column = match($sort_by) {
        'email' => 'email',
        'type' => 'user_type',
        'date' => 'created_at',
        default => 'name'
    };
    $sort_direction = $sort_order === 'desc' ? 'DESC' : 'ASC';

    // Fetch members for current page with sorting
    $query = "SELECT * FROM users" . $where_clause . " ORDER BY " . $sort_column . " " . $sort_direction . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    
    // Bind parameters
    $bind_position = 1;
    foreach ($params as $param) {
        $stmt->bindValue($bind_position++, $param);
    }
    $stmt->bindValue($bind_position++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($bind_position, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = $e->getMessage();
}

// Add sorting links to the table header
function getSortLink($column, $current_sort, $current_order, $current_type) {
    $new_order = ($current_sort === $column && $current_order === 'asc') ? 'desc' : 'asc';
    $params = [
        'sort' => $column,
        'order' => $new_order
    ];
    if (!empty($current_type)) {
        $params['type'] = $current_type;
    }
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Members - Hostel Management System</title>
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
                    <a href="view_members.php" class="sidebar-link active mb-2">
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
            <header class="header h-16 flex items-center justify-between px-6">
                <div class="flex items-center">
                    <button class="text-gray-500 hover:text-gray-600 focus:outline-none md:hidden">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-2xl font-bold text-gray-800 ml-6">View Members</h2>
                </div>

                <?php include 'components/profile_dropdown.php'; ?>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="max-w-7xl mx-auto">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Member Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-indigo-100 text-indigo-500">
                                    <i class="fas fa-users text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="text-2xl font-semibold text-gray-700"><?php echo $totalMembers; ?></h4>
                                    <p class="text-gray-500">Total Members</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                    <i class="fas fa-user-graduate text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="text-2xl font-semibold text-gray-700"><?php echo $memberCounts['student'] ?? 0; ?></h4>
                                    <p class="text-gray-500">Students</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-500">
                                    <i class="fas fa-user-tie text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="text-2xl font-semibold text-gray-700"><?php echo $memberCounts['school_official'] ?? 0; ?></h4>
                                    <p class="text-gray-500">School Officials</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                                    <i class="fas fa-user-shield text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h4 class="text-2xl font-semibold text-gray-700"><?php echo $memberCounts['admin'] ?? 0; ?></h4>
                                    <p class="text-gray-500">Administrators</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Member Management -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-700">Member List</h3>
                                <div class="flex space-x-2">
                                    <a href="?<?php echo http_build_query(['type' => '']); ?>" 
                                       class="px-3 py-1 rounded <?php echo empty($filter_type) ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                        All
                                    </a>
                                    <a href="?<?php echo http_build_query(['type' => 'student']); ?>" 
                                       class="px-3 py-1 rounded <?php echo $filter_type === 'student' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                        Students
                                    </a>
                                    <a href="?<?php echo http_build_query(['type' => 'school_official']); ?>" 
                                       class="px-3 py-1 rounded <?php echo $filter_type === 'school_official' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                        Officials
                                    </a>
                                    <a href="?<?php echo http_build_query(['type' => 'admin']); ?>" 
                                       class="px-3 py-1 rounded <?php echo $filter_type === 'admin' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                        Admins
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <a href="<?php echo getSortLink('name', $sort_by, $sort_order, $filter_type); ?>" class="flex items-center space-x-1 hover:text-gray-700">
                                                <span>Name</span>
                                                <?php if ($sort_by === 'name'): ?>
                                                    <i class="fas fa-sort-<?php echo $sort_order === 'asc' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <a href="<?php echo getSortLink('email', $sort_by, $sort_order, $filter_type); ?>" class="flex items-center space-x-1 hover:text-gray-700">
                                                <span>Email</span>
                                                <?php if ($sort_by === 'email'): ?>
                                                    <i class="fas fa-sort-<?php echo $sort_order === 'asc' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <a href="<?php echo getSortLink('type', $sort_by, $sort_order, $filter_type); ?>" class="flex items-center space-x-1 hover:text-gray-700">
                                                <span>Type</span>
                                                <?php if ($sort_by === 'type'): ?>
                                                    <i class="fas fa-sort-<?php echo $sort_order === 'asc' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <a href="<?php echo getSortLink('date', $sort_by, $sort_order, $filter_type); ?>" class="flex items-center space-x-1 hover:text-gray-700">
                                                <span>Join Date</span>
                                                <?php if ($sort_by === 'date'): ?>
                                                    <i class="fas fa-sort-<?php echo $sort_order === 'asc' ? 'up' : 'down'; ?>"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($members as $member): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-500"></i>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($member['name']); ?></div>
                                                    <?php if ($member['user_type'] == 'student'): ?>
                                                        <div class="text-sm text-gray-500">Roll: <?php echo htmlspecialchars($member['roll_number']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($member['email']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $typeClasses = [
                                                'student' => 'bg-blue-100 text-blue-800',
                                                'school_official' => 'bg-green-100 text-green-800',
                                                'admin' => 'bg-purple-100 text-purple-800'
                                            ];
                                            $typeClass = $typeClasses[$member['user_type']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $typeClass; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $member['user_type'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($member['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-3">
                                                <a href="modify_member.php?id=<?php echo $member['id']; ?>" 
                                                   class="inline-flex items-center px-3 py-1 border border-indigo-600 rounded-md text-indigo-600 bg-white hover:bg-indigo-600 hover:text-white transition-colors duration-200">
                                                    <i class="fas fa-edit mr-1"></i>
                                                    Edit
                                                </a>
                                                <a href="#" onclick="confirmDelete(<?php echo $member['id']; ?>)" 
                                                   class="inline-flex items-center px-3 py-1 border border-red-600 rounded-md text-red-600 bg-white hover:bg-red-600 hover:text-white transition-colors duration-200">
                                                    <i class="fas fa-trash mr-1"></i>
                                                    Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo ($page - 1); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo ($page + 1); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing
                                        <span class="font-medium"><?php echo $offset + 1; ?></span>
                                        to
                                        <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span>
                                        of
                                        <span class="font-medium"><?php echo $total_records; ?></span>
                                        results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">First</span>
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                            <a href="?page=<?php echo ($page - 1); ?>" class="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Previous</span>
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo ($i == $page) ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?php echo ($page + 1); ?>" class="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Next</span>
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                            <a href="?page=<?php echo $total_pages; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Last</span>
                                                <i class="fas fa-angle-double-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
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

        // Confirm delete
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this member? This action cannot be undone.')) {
                window.location.href = 'view_members.php?delete=' + id;
            }
        }
    </script>
</body>
</html>
