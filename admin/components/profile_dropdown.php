<?php
// Get admin details for the profile
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get first name initial with fallback
    $fullName = $admin['full_name'] ?? 'Admin';
    $firstName = explode(' ', $fullName)[0];
    $initial = strtoupper(substr($firstName, 0, 1));
    
    // If no name is available, use the first letter of the email
    if ($initial === 'A' && isset($admin['email'])) {
        $initial = strtoupper(substr($admin['email'], 0, 1));
    }
} catch (PDOException $e) {
    // Handle error silently
    $admin = null;
    $fullName = 'Admin';
    $initial = 'A';
}
?>

<div class="flex items-center space-x-4">
    <div class="relative">
        <button class="flex items-center space-x-2 text-gray-700 hover:text-indigo-600 focus:outline-none" id="profileButton">
            <div class="relative z-10 flex items-center justify-center h-8 w-8 rounded-full bg-indigo-600 text-white font-semibold text-sm">
                <?php echo $initial; ?>
            </div>
            <span class="font-medium"><?php echo htmlspecialchars($fullName); ?></span>
            <i class="fas fa-chevron-down text-sm"></i>
        </button>

        <!-- Profile dropdown menu -->
        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden" id="profileDropdown">
            <div class="px-4 py-2 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($fullName); ?></p>
                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin['email'] ?? ''); ?></p>
            </div>
            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-user text-gray-400"></i>
                    <span>My Profile</span>
                </div>
            </a>
            <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-sign-out-alt text-red-400"></i>
                    <span>Logout</span>
                </div>
            </a>
        </div>
    </div>
</div>

<script>
    // Profile dropdown functionality
    const profileButton = document.getElementById('profileButton');
    const profileDropdown = document.getElementById('profileDropdown');

    // Handle both click and touch events
    ['click', 'touchend'].forEach(eventType => {
        profileButton.addEventListener(eventType, (e) => {
            e.preventDefault();
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });
    });

    // Close dropdown when clicking/touching outside
    ['click', 'touchend'].forEach(eventType => {
        document.addEventListener(eventType, (e) => {
            if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    });
</script> 