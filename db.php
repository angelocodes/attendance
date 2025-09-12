<?php
$conn = new mysqli('localhost', 'root', '', 'uni_at');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
