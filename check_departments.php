<?php
include 'db.php';
$result = $conn->query("SELECT d.department_id, d.department_name, s.school_name FROM departments d JOIN schools s ON d.school_id = s.school_id WHERE s.school_name = 'School of Business Studies'");
if ($result->num_rows > 0) {
    echo "Departments in School of Business Studies:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['department_id'] . " - " . $row['department_name'] . "\n";
    }
} else {
    echo "No departments found.\n";
}
