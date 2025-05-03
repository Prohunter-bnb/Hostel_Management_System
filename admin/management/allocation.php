<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}
include '../../config/db_connect.php';

// Create room_allocations table if it doesn't exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS room_allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        room_id INT NOT NULL,
        allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (room_id) REFERENCES rooms(id)
    )");
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle allocation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'allocate':
                // Check if room has less than 4 occupants
                $stmt = $conn->prepare("SELECT COUNT(*) as occupants FROM room_allocations WHERE room_id = ?");
                $stmt->execute([$_POST['room_id']]);
                $occupants = $stmt->fetch(PDO::FETCH_ASSOC)['occupants'];

                if ($occupants >= 4) {
                    $error = "Room is already at full capacity (4 students)";
                } else {
                    // Check if student is already allocated
                    $stmt = $conn->prepare("SELECT COUNT(*) as has_room FROM room_allocations WHERE student_id = ?");
                    $stmt->execute([$_POST['student_id']]);
                    if ($stmt->fetch(PDO::FETCH_ASSOC)['has_room'] > 0) {
                        $error = "Student already has a room allocated";
                    } else {
                        // Allocate room
                        $stmt = $conn->prepare("INSERT INTO room_allocations (student_id, room_id) VALUES (?, ?)");
                        $stmt->execute([$_POST['student_id'], $_POST['room_id']]);

                        // Update room status if it becomes full
                        if ($occupants + 1 >= 4) {
                            $stmt = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                            $stmt->execute([$_POST['room_id']]);
                        }
                        $success = "Room allocated successfully";
                    }
                }
                break;

            case 'deallocate':
                $stmt = $conn->prepare("DELETE FROM room_allocations WHERE student_id = ?");
                $stmt->execute([$_POST['student_id']]);

                // Update room status
                $stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
                $stmt->execute([$_POST['room_id']]);
                
                $success = "Room deallocated successfully";
                break;

            case 'change_room':
                // Check if new room has less than 4 occupants
                $stmt = $conn->prepare("SELECT COUNT(*) as occupants FROM room_allocations WHERE room_id = ?");
                $stmt->execute([$_POST['new_room_id']]);
                $occupants = $stmt->fetch(PDO::FETCH_ASSOC)['occupants'];

                if ($occupants >= 4) {
                    $error = "New room is already at full capacity (4 students)";
                } else {
                    // Update the room allocation
                    $stmt = $conn->prepare("UPDATE room_allocations SET room_id = ? WHERE student_id = ?");
                    $stmt->execute([$_POST['new_room_id'], $_POST['student_id']]);

                    // Update old room status
                    $stmt = $conn->prepare("
                        UPDATE rooms r 
                        SET status = CASE 
                            WHEN (SELECT COUNT(*) FROM room_allocations WHERE room_id = r.id) = 0 
                            THEN 'available' ELSE status END 
                        WHERE id = ?");
                    $stmt->execute([$_POST['old_room_id']]);

                    // Update new room status if it becomes full
                    if ($occupants + 1 >= 4) {
                        $stmt = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                        $stmt->execute([$_POST['new_room_id']]);
                    }

                    $success = "Room changed successfully";
                }
                break;
        }
    } catch (PDOException $e) {
        $error = "Action failed: " . $e->getMessage();
    }
}

// Fetch allocation statistics
try {
    // Total students
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'student'");
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Allocated students
    $stmt = $conn->query("SELECT COUNT(DISTINCT student_id) as allocated FROM room_allocations");
    $allocatedStudents = $stmt->fetch(PDO::FETCH_ASSOC)['allocated'] ?? 0;

    $pendingAllocations = $totalStudents - $allocatedStudents;

    // Get room occupancy stats
    $stmt = $conn->query("
        SELECT r.id, r.room_number, r.floor, r.status,
               COUNT(ra.id) as current_occupants
        FROM rooms r
        LEFT JOIN room_allocations ra ON r.id = ra.room_id
        GROUP BY r.id
        ORDER BY r.floor, r.room_number
    ");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unallocated students
    $stmt = $conn->query("
        SELECT u.id, u.name, u.email, u.roll_number
        FROM users u
        LEFT JOIN room_allocations ra ON u.id = ra.student_id
        WHERE u.user_type = 'student' AND ra.id IS NULL
        ORDER BY u.name
    ");
    $unallocatedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get allocated students with room info
    $stmt = $conn->query("
        SELECT u.id, u.name, u.roll_number, r.room_number, r.floor, r.id as room_id
        FROM users u
        JOIN room_allocations ra ON u.id = ra.student_id
        JOIN rooms r ON ra.room_id = r.id
        ORDER BY r.floor, r.room_number, u.name
    ");
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Allocation - Hostel Management System</title>
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
                    <a href="allocation.php" class="sidebar-link active mb-2">
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
                    <h2 class="text-2xl font-bold text-gray-800 ml-6">Room Allocation</h2>
                </div>

                <?php include '../components/profile_dropdown.php'; ?>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="max-w-7xl mx-auto">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <?php if (isset($error)): ?>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Allocation Statistics -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="bg-white rounded-lg shadow-md p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-500">
                                        <i class="fas fa-users text-2xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-2xl font-semibold text-gray-700"><?php echo $totalStudents; ?></h4>
                                        <p class="text-gray-500">Total Students</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg shadow-md p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                                        <i class="fas fa-bed text-2xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-2xl font-semibold text-gray-700"><?php echo $allocatedStudents; ?></h4>
                                        <p class="text-gray-500">Allocated Students</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg shadow-md p-6">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                                        <i class="fas fa-clock text-2xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-2xl font-semibold text-gray-700"><?php echo $pendingAllocations; ?></h4>
                                        <p class="text-gray-500">Pending Allocations</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Room Allocation Section -->
                        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-700">Room Allocations</h3>
                                    <p class="text-sm text-gray-500 mt-1">All rooms have a standard capacity of 4 persons</p>
                                </div>
                            </div>

                            <!-- New Allocation Form -->
                            <form method="POST" class="mb-8">
                                <input type="hidden" name="action" value="allocate">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Student</label>
                                        <select name="student_id" required class="w-full px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                                            <option value="">Select Student</option>
                                            <?php foreach ($unallocatedStudents as $student): ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo htmlspecialchars($student['name'] . ' (' . $student['roll_number'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Room</label>
                                        <select name="room_id" required class="w-full px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                                            <option value="">Select Room</option>
                                            <?php foreach ($rooms as $room): ?>
                                                <?php if ($room['current_occupants'] < 4): ?>
                                                    <option value="<?php echo $room['id']; ?>">
                                                        Room <?php echo htmlspecialchars($room['room_number']); ?> 
                                                        (Floor <?php echo $room['floor']; ?>) - 
                                                        <?php echo $room['current_occupants']; ?>/4 occupied
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                            <i class="fas fa-plus mr-2"></i> Allocate Room
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Search and Filters -->
                            <div class="flex flex-wrap gap-4 mb-6">
                                <div class="flex-1">
                                    <input type="text" id="searchInput" placeholder="Search by name or room number..." 
                                           class="w-full px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                                </div>
                                <select id="floorFilter" class="px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                                    <option value="">All Floors</option>
                                    <option value="1">First Floor</option>
                                    <option value="2">Second Floor</option>
                                    <option value="3">Third Floor</option>
                                </select>
                            </div>

                            <!-- Allocations Table -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roll No.</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room No.</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Floor</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room Occupancy</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($allocations as $allocation): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($allocation['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($allocation['roll_number']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($allocation['room_number']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">Floor <?php echo htmlspecialchars($allocation['floor']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $roomOccupancy = array_count_values(array_column($allocations, 'room_id'))[$allocation['room_id']];
                                                    echo $roomOccupancy . '/4';
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="openChangeRoomModal(<?php 
                                                        echo htmlspecialchars(json_encode([
                                                            'student_id' => $allocation['id'],
                                                            'student_name' => $allocation['name'],
                                                            'current_room' => $allocation['room_number'],
                                                            'current_room_id' => $allocation['room_id']
                                                        ])); 
                                                    ?>)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                        <i class="fas fa-exchange-alt mr-1"></i> Change Room
                                                    </button>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="deallocate">
                                                        <input type="hidden" name="student_id" value="<?php echo $allocation['id']; ?>">
                                                        <input type="hidden" name="room_id" value="<?php echo $allocation['room_id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900" 
                                                                onclick="return confirm('Are you sure you want to deallocate this room?')">
                                                            <i class="fas fa-times mr-1"></i> Remove
                                                        </button>
                                                    </form>
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

    <!-- Change Room Modal -->
    <div id="changeRoomModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Change Room Assignment</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="change_room">
                    <input type="hidden" name="student_id" id="change_student_id">
                    <input type="hidden" name="old_room_id" id="change_old_room_id">
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-600">
                            Student: <span id="change_student_name" class="font-medium"></span>
                        </p>
                        <p class="text-sm text-gray-600">
                            Current Room: <span id="change_current_room" class="font-medium"></span>
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Room</label>
                        <select name="new_room_id" required class="w-full px-4 py-2 rounded-lg border-2 focus:border-indigo-500 focus:ring-0">
                            <option value="">Select New Room</option>
                            <?php foreach ($rooms as $room): ?>
                                <?php if ($room['current_occupants'] < 4): ?>
                                    <option value="<?php echo $room['id']; ?>">
                                        Room <?php echo htmlspecialchars($room['room_number']); ?> 
                                        (Floor <?php echo $room['floor']; ?>) - 
                                        <?php echo $room['current_occupants']; ?>/4 occupied
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeChangeRoomModal()"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Change Room
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

        // Change Room Modal Functions
        function openChangeRoomModal(data) {
            document.getElementById('change_student_id').value = data.student_id;
            document.getElementById('change_student_name').textContent = data.student_name;
            document.getElementById('change_current_room').textContent = data.current_room;
            document.getElementById('change_old_room_id').value = data.current_room_id;
            document.getElementById('changeRoomModal').classList.remove('hidden');
        }

        function closeChangeRoomModal() {
            document.getElementById('changeRoomModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('changeRoomModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeChangeRoomModal();
            }
        });
    </script>
</body>
</html> 