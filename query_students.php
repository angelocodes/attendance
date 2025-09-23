<?php
include 'db.php';
$result = $conn->query("SELECT s.registration_number, s.first_name, s.last_name, c.course_name, sch.school_name FROM students s JOIN courses c ON s.course_id = c.course_id JOIN schools sch ON c.school_id = sch.school_id WHERE sch.school_id = 7 ORDER BY s.registration_number");
if ($result->num_rows > 0) {
    echo "Students in School of Business Studies:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['registration_number'] . ' - ' . $row['first_name'] . ' ' . $row['last_name'] . ' - ' . $row['course_name'] . ' - School: ' . $row['school_name'] . "\n";
    }
} else {
    echo "No students found in School of Finance.\n";
}
