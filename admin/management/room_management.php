<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}
include '../../config/db_connect.php';

// Initialize variables
$totalRooms = 0;
$occupiedRooms = 0;
$availableRooms = 0;
$rooms = [];
$error = null;

try {
    // Create table only if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_number VARCHAR(10) NOT NULL,
        floor INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'available',
        occupancy INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert sample rooms if the table is empty
    $stmt = $conn->query("SELECT COUNT(*) FROM rooms");
    if ($stmt->fetchColumn() == 0) {
        // Insert 100 rooms per floor (3 floors)
        for ($floor = 1; $floor <= 3; $floor++) {
            for ($i = 1; $i <= 100; $i++) {
                $roomNumber = $floor . str_pad($i, 2, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("INSERT INTO rooms (room_number, floor, status) VALUES (?, ?, 'available')");
                $stmt->execute([$roomNumber, $floor]);
            }
        }
    }

    // Get floor statistics
    $stmt = $conn->query("
        SELECT 
            floor,
            COUNT(*) as total_rooms,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_rooms
        FROM rooms 
        GROUP BY floor 
        ORDER BY floor
    ");
    $floorStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get overall statistics
    $stmt = $conn->query("SELECT 
        COUNT(*) as total_rooms,
        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms
        FROM rooms");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalRooms = $stats['total_rooms'] ?? 0;
    $occupiedRooms = $stats['occupied_rooms'] ?? 0;
    $availableRooms = $totalRooms - $occupiedRooms;

    // Get room data with occupancy information
    $query = "SELECT r.*, 
              COALESCE(COUNT(ra.student_id), 0) as current_occupants 
              FROM rooms r 
              LEFT JOIN room_allocations ra ON r.id = ra.room_id 
              WHERE 1=1";
    $params = [];

    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $query .= " AND r.room_number LIKE ?";
        $params[] = "%" . trim($_GET['search']) . "%";
    }

    if (isset($_GET['floor']) && $_GET['floor'] !== '') {
        $query .= " AND r.floor = ?";
        $params[] = (int)$_GET['floor'];
    }

    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $query .= " AND r.status = ?";
        $params[] = trim($_GET['status']);
    }

    $query .= " GROUP BY r.id ORDER BY r.floor ASC, r.room_number ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle Add/Edit/Delete Room actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $conn->prepare("INSERT INTO rooms (room_number, floor, status) VALUES (?, ?, ?)");
                $stmt->execute([
                    trim($_POST['room_number']),
                    (int)$_POST['floor'],
                    trim($_POST['status'])
                ]);
                $success = "Room added successfully!";
                break;

            case 'edit':
                $stmt = $conn->prepare("UPDATE rooms SET room_number = ?, floor = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    trim($_POST['room_number']),
                    (int)$_POST['floor'],
                    trim($_POST['status']),
                    (int)$_POST['room_id']
                ]);
                $success = "Room updated successfully!";
                break;

            case 'delete':
                $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
                $stmt->execute([(int)$_POST['room_id']]);
                $success = "Room deleted successfully!";
                break;
        }
        
        // Redirect to refresh the page and prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
        exit();
    } catch (PDOException $e) {
        $error = "Action failed: " . $e->getMessage();
    }
}

// Get success message from URL if it exists
$success = isset($_GET['success']) ? $_GET['success'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Hostel Management System</title>
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
                    <a href="room_management.php" class="sidebar-link active mb-2">
                        <i class="fas fa-door-open w-5 h-5 mr-3"></i>
                        <span>Room Management</span>
                    </a>
                    <a href="allocation.php" class="sidebar-link mb-2">
                        <i class="fas fa-bed w-5 h-5 mr-3"></i>
                        <span>Room Allocation</span>
                    </a>
                    <a href="fees.php" class="sidebar-link">
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
                    <h2 class="text-2xl font-bold text-gray-800 ml-6">Room Management</h2>
                </div>

                <?php include '../components/profile_dropdown.php'; ?>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="max-w-7xl mx-auto">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <!-- Floor Statistics Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                            <?php foreach ($floorStats as $stat): ?>
                                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            Floor <?php echo $stat['floor']; ?>
                                        </h3>
                                        <span class="px-3 py-1 rounded-full text-sm <?php 
                                            echo $stat['available_rooms'] > 0 
                                                ? 'bg-green-100 text-green-800' 
                                                : 'bg-red-100 text-red-800'; 
                                        ?>">
                                            <?php echo $stat['available_rooms']; ?> Available
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-4 text-center">
                                        <div>
                                            <p class="text-2xl font-bold text-gray-700"><?php echo $stat['total_rooms']; ?></p>
                                            <p class="text-sm text-gray-500">Total</p>
                                    </div>
                                        <div>
                                            <p class="text-2xl font-bold text-green-600"><?php echo $stat['available_rooms']; ?></p>
                                            <p class="text-sm text-gray-500">Available</p>
                                </div>
                                        <div>
                                            <p class="text-2xl font-bold text-red-600"><?php echo $stat['occupied_rooms']; ?></p>
                                            <p class="text-sm text-gray-500">Occupied</p>
                                    </div>
                                    </div>
                                    <div class="mt-4 h-2 bg-gray-200 rounded-full">
                                        <div class="h-2 rounded-full bg-blue-600" style="width: <?php 
                                            echo ($stat['occupied_rooms'] / $stat['total_rooms']) * 100; 
                                        ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Room Management Actions -->
                        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                <h3 class="text-xl font-semibold text-gray-700">Room Management</h3>
                                    <p class="text-sm text-gray-500 mt-1">All rooms have a standard capacity of 4 persons</p>
                                </div>
                                <button onclick="openAddModal()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                    <i class="fas fa-plus mr-2"></i> Add New Room
                                </button>
                            </div>

                            <!-- Search and Filters -->
                            <form method="GET" class="flex flex-wrap gap-4 mb-6">
                                <div class="flex-1">
                                    <input type="text" name="search" placeholder="Search rooms..." 
                                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                           class="w-full px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                                </div>
                                <select name="floor" class="px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0" onchange="this.form.submit()">
                                    <option value="">All Floors</option>
                                    <?php for ($i = 1; $i <= 3; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo (isset($_GET['floor']) && $_GET['floor'] == $i) ? 'selected' : ''; ?>>
                                            Floor <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="status" class="px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                                    <option value="">All Status</option>
                                    <?php
                                    $selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';
                                    $statuses = ['available', 'occupied', 'maintenance'];
                                    foreach ($statuses as $status) {
                                        $selected = ($selectedStatus === $status) ? 'selected' : '';
                                        echo "<option value=\"$status\" $selected>" . ucfirst($status) . "</option>";
                                    }
                                    ?>
                                </select>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                    <i class="fas fa-search mr-2"></i> Filter
                                </button>
                                <?php if (!empty($_GET)): ?>
                                    <a href="room_management.php" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                                        <i class="fas fa-times mr-2"></i> Clear Filters
                                    </a>
                                <?php endif; ?>
                            </form>

                            <!-- Rooms Display - Always Column Layout -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white border rounded-lg overflow-hidden">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room Number</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Floor</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occupancy</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($rooms as $room): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        Room <?php echo htmlspecialchars($room['room_number']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500">
                                                        Floor <?php echo htmlspecialchars($room['floor']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php
                                                        echo match($room['status']) {
                                                            'available' => 'bg-green-100 text-green-800',
                                                            'occupied' => 'bg-red-100 text-red-800',
                                                            'maintenance' => 'bg-yellow-100 text-yellow-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst(htmlspecialchars($room['status'])); ?>
                                                    </span>
                                                </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-1">
                                                            <div class="text-sm text-gray-900">
                                                                <?php echo $room['current_occupants']; ?>/4 Students
                                                            </div>
                                                            <div class="w-24 h-2 bg-gray-200 rounded-full mt-1">
                                                                <div class="h-2 rounded-full <?php
                                                                    $percentage = ($room['current_occupants'] / 4) * 100;
                                                                    echo match(true) {
                                                                        $percentage >= 100 => 'bg-red-600',
                                                                        $percentage >= 75 => 'bg-yellow-600',
                                                                        default => 'bg-green-600'
                                                                    };
                                                                ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                            </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="flex space-x-3">
                                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($room)); ?>)" 
                                                                class="text-indigo-600 hover:text-indigo-900">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button onclick="confirmDelete(<?php echo $room['id']; ?>)" 
                                                                class="text-red-600 hover:text-red-900">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Room Modal -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Room</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Room Number</label>
                        <input type="text" name="room_number" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Floor</label>
                        <select name="floor" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <option value="<?php echo $i; ?>">Floor <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('addModal')"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Add Room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Room</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="room_id" id="edit_room_id">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Room Number</label>
                        <input type="text" name="room_number" id="edit_room_number" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Floor</label>
                        <select name="floor" id="edit_floor" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <option value="<?php echo $i; ?>">Floor <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="edit_status" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editModal')"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Update Room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Room Form (Hidden) -->
    <form id="deleteForm" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="room_id" id="delete_room_id">
    </form>

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

        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function openEditModal(room) {
            document.getElementById('edit_room_id').value = room.id;
            document.getElementById('edit_room_number').value = room.room_number;
            document.getElementById('edit_floor').value = room.floor;
            document.getElementById('edit_status').value = room.status;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function confirmDelete(roomId) {
            if (confirm('Are you sure you want to delete this room?')) {
                document.getElementById('delete_room_id').value = roomId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('fixed')) {
                event.target.classList.add('hidden');
            }
        }

        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        const floorFilter = document.getElementById('floorFilter');
        const table = document.querySelector('table tbody');
        const rows = table.getElementsByTagName('tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedFloor = floorFilter.value;

            for (let row of rows) {
                const name = row.cells[0].textContent.toLowerCase();
                const roomNumber = row.cells[2].textContent.toLowerCase();
                const floor = row.cells[3].textContent.toLowerCase();

                const matchesSearch = name.includes(searchTerm) || roomNumber.includes(searchTerm);
                const matchesFloor = !selectedFloor || floor.includes(`Floor ${selectedFloor}`);

                row.style.display = matchesSearch && matchesFloor ? '' : 'none';
            }
        }

        searchInput.addEventListener('input', filterTable);
        floorFilter.addEventListener('change', filterTable);
    </script>
</body>
</html> 