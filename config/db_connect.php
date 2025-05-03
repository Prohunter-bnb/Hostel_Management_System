<?php
// Connection parameters for the external MariaDB database
$host = '110ar.h.filess.io';
$port = 3305;
$dbname = 'Hostel_yardformix';
$user = 'Hostel_yardformix';
$pass = '9694d0fea0c70ab19513962f597c7d558871fa16';

try {
    // Creating a new PDO instance to connect to MariaDB
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
