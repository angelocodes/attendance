<?php
include 'db.php';
$result = $conn->query("SELECT c.course_id, c.course_name, c.course_code, d.department_name, s.school_name FROM courses c LEFT JOIN departments d ON c.department_id = d.department_id LEFT JOIN schools s ON c.school_id = s.school_id WHERE c.course_name LIKE '%Commerce%'");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Course: " . $row['course_name'] . " (" . $row['course_code'] . ") - Department: " . ($row['department_name'] ?? 'None') . " - School: " . $row['school_name'] . "\n";
    }
} else {
    echo "No courses found.\n";
}
