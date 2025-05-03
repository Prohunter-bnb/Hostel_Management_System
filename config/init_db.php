<?php
require_once 'db_connect.php';

try {
    // Create users table
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        user_type ENUM('admin', 'student', 'management') NOT NULL,
        roll_number VARCHAR(20) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create students table
    $conn->exec("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        roll_number VARCHAR(20) NOT NULL UNIQUE,
        department VARCHAR(100) NOT NULL,
        year_of_study INT NOT NULL,
        phone VARCHAR(20),
        guardian_name VARCHAR(255) NOT NULL,
        guardian_phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        gender ENUM('male', 'female', 'other') NOT NULL,
        blood_group VARCHAR(5),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create rooms table
    $conn->exec("CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_number VARCHAR(20) NOT NULL UNIQUE,
        floor INT NOT NULL,
        capacity INT NOT NULL DEFAULT 4,
        status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create room_allocations table
    $conn->exec("CREATE TABLE IF NOT EXISTS room_allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        room_id INT NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
    )");

    // Create fee_settings table
    $conn->exec("CREATE TABLE IF NOT EXISTS fee_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fee_type VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        due_day INT NOT NULL,
        late_fee DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create fee_payments table
    $conn->exec("CREATE TABLE IF NOT EXISTS fee_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        gst_amount DECIMAL(10,2) DEFAULT 0.00,
        total_amount DECIMAL(10,2) DEFAULT 0.00,
        payment_date DATETIME NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
        receipt_number VARCHAR(50) UNIQUE,
        month VARCHAR(20) NOT NULL,
        year INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create complaints table
    $conn->exec("CREATE TABLE IF NOT EXISTS complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        category VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('pending', 'resolved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Insert default admin if not exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->exec("INSERT INTO users (name, email, password, user_type) VALUES 
            ('Admin', 'admin@hms.com', '$admin_password', 'admin')");
    }

    // Insert default fee settings if not exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM fee_settings");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("INSERT INTO fee_settings (fee_type, amount, due_day, late_fee) VALUES 
            ('Monthly Hostel Fee', 3500.00, 10, 500.00)");
    }

    echo "Database initialized successfully!";
} catch (PDOException $e) {
    die("Error initializing database: " . $e->getMessage());
}
?> 