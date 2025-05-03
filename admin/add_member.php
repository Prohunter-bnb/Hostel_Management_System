<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db_connect.php';

// Fetch available rooms for allocation
$stmt = $conn->prepare("
    SELECT r.id, r.room_number, r.floor, r.capacity, r.status,
           COUNT(a.id) as current_occupants
    FROM rooms r
    LEFT JOIN allocations a ON r.id = a.room_id
    WHERE r.status = 'available'
    GROUP BY r.id
    HAVING current_occupants < r.capacity
    ORDER BY r.floor, r.room_number
");
$stmt->execute();
$available_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
        $year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_NUMBER_INT);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $guardian_name = filter_input(INPUT_POST, 'guardian_name', FILTER_SANITIZE_STRING);
        $guardian_phone = filter_input(INPUT_POST, 'guardian_phone', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $floor_preference = filter_input(INPUT_POST, 'floor_preference', FILTER_SANITIZE_NUMBER_INT);
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Email already exists!";
            header("Location: add_member.php");
            exit();
        }

        // Start transaction
        $conn->beginTransaction();

        // Generate roll number
        $dept_code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $department), 0, 2));
        
        // Get the last roll number for this department and year
        $stmt = $conn->prepare("
            SELECT roll_number 
            FROM students 
            WHERE department = ? AND year_of_study = ?
            ORDER BY roll_number DESC 
            LIMIT 1
        ");
        $stmt->execute([$department, $year]);
        $last_roll = $stmt->fetch(PDO::FETCH_COLUMN);

        if ($last_roll) {
            // Extract the sequential number and increment
            $seq_num = intval(substr($last_roll, -3)) + 1;
        } else {
            // Start with 001 if no existing roll numbers
            $seq_num = 1;
        }

        // Format the new roll number: YYDDxxx (YY=year, DD=dept code, xxx=sequence)
        $roll_number = sprintf("%d%s%03d", $year, $dept_code, $seq_num);

        // Insert user with roll number
        $sql = "INSERT INTO users (name, email, password, user_type, roll_number) VALUES (?, ?, ?, 'student', ?)";
        $params = [$name, $email, $hashed_password, $roll_number];
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $user_id = $conn->lastInsertId();

        // Find an available room
        $room_query = "
            SELECT r.id, r.room_number, r.floor, r.capacity, r.status,
                   COUNT(a.id) as current_occupants
            FROM rooms r
            LEFT JOIN allocations a ON r.id = a.room_id
            WHERE r.status = 'available'
            " . ($floor_preference ? "AND r.floor = :floor" : "") . "
            GROUP BY r.id
            HAVING current_occupants < r.capacity
            ORDER BY RAND()
            LIMIT 1
        ";

        $stmt = $conn->prepare($room_query);
        if ($floor_preference) {
            $stmt->bindParam(':floor', $floor_preference);
        }
        $stmt->execute();
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            throw new Exception("No available rooms found" . ($floor_preference ? " on floor $floor_preference" : ""));
        }

        // Insert student details with room allocation
        $stmt = $conn->prepare("
            INSERT INTO students (
                user_id, roll_number, department, year_of_study, 
                phone, guardian_name, guardian_phone, address, room_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, $roll_number, $department, $year,
            $phone ?: null, $guardian_name, $guardian_phone, $address, $room['id']
        ]);

        $conn->commit();
        $_SESSION['success'] = "Student added successfully! Roll Number: $roll_number, Room: {$room['room_number']}";
        header("Location: add_member.php");
        exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: add_member.php");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: add_member.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - Hostel Management System</title>
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
                    <a href="add_member.php" class="sidebar-link active mb-2">
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
            <header class="header h-16 flex items-center justify-between px-6">
                <div class="flex items-center">
                    <button class="text-gray-500 hover:text-gray-600 focus:outline-none md:hidden">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-2xl font-bold text-gray-800 ml-6">Add New Student</h2>
                </div>

                <?php include 'components/profile_dropdown.php'; ?>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="max-w-7xl mx-auto">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <form action="add_member_process.php" method="POST" onsubmit="return validateForm()" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Personal Information -->
                                <div class="space-y-6">
                                    <h4 class="text-lg font-semibold text-gray-700 border-b pb-2">Personal Information</h4>
                                    
                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                                            Full Name
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            type="text" name="name" id="name" required
                                            placeholder="Enter student's full name">
                                        <div class="tooltip">Enter the student's full name as per official records</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                                            Email Address
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            type="email" name="email" id="email" required
                                            placeholder="student@example.com">
                                        <div class="tooltip">Enter a valid email address for communication</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">
                                            Phone Number
                                        </label>
                                        <input class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            type="tel" name="phone" id="phone" pattern="[0-9]{10}"
                                            placeholder="10-digit mobile number">
                                        <div class="tooltip">Enter a 10-digit mobile number (optional)</div>
                                    </div>
                                </div>

                                <!-- Academic Information -->
                                <div class="space-y-6">
                                    <h4 class="text-lg font-semibold text-gray-700 border-b pb-2">Academic Information</h4>
                                    
                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="department">
                                            Department
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            name="department" id="department" required>
                                            <option value="">Select Department</option>
                                            <option value="Computer Science">Computer Science</option>
                                            <option value="Information Technology">Information Technology</option>
                                            <option value="Electronics">Electronics</option>
                                            <option value="Electrical">Electrical</option>
                                            <option value="Mechanical">Mechanical</option>
                                            <option value="Civil">Civil</option>
                                            <option value="Chemical">Chemical</option>
                                        </select>
                                        <div class="tooltip">Select the student's department</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="year">
                                            Year of Study
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            name="year" id="year" required>
                                            <option value="">Select Year</option>
                                            <option value="1">1st Year</option>
                                            <option value="2">2nd Year</option>
                                            <option value="3">3rd Year</option>
                                            <option value="4">4th Year</option>
                                        </select>
                                        <div class="tooltip">Select the student's current year of study</div>
                                    </div>
                                </div>

                                <!-- Additional Information -->
                                <div class="space-y-6">
                                    <h4 class="text-lg font-semibold text-gray-700 border-b pb-2">Additional Information</h4>
                                    
                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="dob">
                                            Date of Birth
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            type="date" name="dob" id="dob" required>
                                        <div class="tooltip">Enter the student's date of birth</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="gender">
                                            Gender
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            name="gender" id="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <div class="tooltip">Select the student's gender</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="blood_group">
                                            Blood Group
                                        </label>
                                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            name="blood_group" id="blood_group">
                                            <option value="">Select Blood Group</option>
                                            <option value="A+">A+</option>
                                            <option value="A-">A-</option>
                                            <option value="B+">B+</option>
                                            <option value="B-">B-</option>
                                            <option value="AB+">AB+</option>
                                            <option value="AB-">AB-</option>
                                            <option value="O+">O+</option>
                                            <option value="O-">O-</option>
                                        </select>
                                        <div class="tooltip">Select the student's blood group (optional)</div>
                                    </div>
                                </div>

                                <!-- Room Allocation -->
                                <div class="space-y-6">
                                    <h4 class="text-lg font-semibold text-gray-700 border-b pb-2">Room Allocation</h4>
                                    
                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="floor_preference">
                                            Floor Preference
                                        </label>
                                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            name="floor_preference" id="floor_preference">
                                            <option value="">No Preference</option>
                                            <option value="1">Ground Floor</option>
                                            <option value="2">First Floor</option>
                                            <option value="3">Second Floor</option>
                                        </select>
                                        <div class="tooltip">Select preferred floor (optional)</div>
                                    </div>

                                    <div id="room-info" class="hidden p-4 bg-gray-50 rounded-lg">
                                        <p class="text-gray-600">A room will be automatically allocated based on availability and your floor preference.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Guardian Information -->
                            <div class="space-y-6 mt-6">
                                <h4 class="text-lg font-semibold text-gray-700 border-b pb-2">Guardian Information</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="form-group">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="guardian_name">
                                            Guardian Name
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <input class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            type="text" name="guardian_name" id="guardian_name" required
                                            placeholder="Enter guardian's full name">
                                        <div class="tooltip">Enter the guardian's full name</div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="address">
                                        Address
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                        name="address" id="address" rows="3" required
                                        placeholder="Enter complete address"></textarea>
                                    <div class="tooltip">Enter the student's complete residential address</div>
                                </div>
                            </div>

                            <!-- Password Preview -->
                            <div id="password-preview" class="mt-4 p-4 bg-gray-100 rounded-lg hidden">
                                <p class="text-gray-700 font-medium">Generated Password Preview:</p>
                                <p class="text-gray-600 mt-2">Fill in the required fields to see the generated password</p>
                            </div>

                            <div class="pt-6">
                                <button type="submit" class="w-full bg-indigo-600 text-white py-3 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200">
                                    <i class="fas fa-user-plus mr-2"></i> Add Student
                                </button>
                            </div>
                        </form>
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

        function validateForm() {
            // Get all required fields
            const requiredFields = {
                'name': 'Full Name',
                'email': 'Email Address',
                'department': 'Department',
                'year': 'Year of Study',
                'guardian_name': 'Guardian Name',
                'guardian_phone': 'Guardian Phone',
                'address': 'Address',
                'gender': 'Gender'
            };

            let isValid = true;
            let errorMessage = '';

            // Check each required field
            for (const [fieldId, fieldName] of Object.entries(requiredFields)) {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    isValid = false;
                    errorMessage += `- ${fieldName} is required\n`;
                    field.classList.add('border-red-500');
                } else {
                    field.classList.remove('border-red-500');
                }
            }

            // Validate email format
            const email = document.getElementById('email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.value && !emailRegex.test(email.value)) {
                isValid = false;
                errorMessage += '- Please enter a valid email address\n';
                email.classList.add('border-red-500');
            }

            // Validate phone numbers
            const phone = document.getElementById('phone');
            const guardianPhone = document.getElementById('guardian_phone');
            const phoneRegex = /^\d{10}$/;

            if (phone.value && !phoneRegex.test(phone.value)) {
                isValid = false;
                errorMessage += '- Phone number must be 10 digits\n';
                phone.classList.add('border-red-500');
            }

            if (!phoneRegex.test(guardianPhone.value)) {
                isValid = false;
                errorMessage += '- Guardian phone number must be 10 digits\n';
                guardianPhone.classList.add('border-red-500');
            }

            // Validate year of study
            const year = document.getElementById('year');
            const yearValue = parseInt(year.value);
            if (isNaN(yearValue) || yearValue < 1 || yearValue > 4) {
                isValid = false;
                errorMessage += '- Year of study must be between 1 and 4\n';
                year.classList.add('border-red-500');
            }

            if (!isValid) {
                alert('Please correct the following errors:\n\n' + errorMessage);
                return false;
            }

            return true;
        }

        // Add event listeners to remove error styling when user starts typing
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('input', function() {
                this.classList.remove('border-red-500');
            });
        });
    </script>
</body>
</html>