<?php
include 'db.php';
$reg = 'REG0093';
$result = $conn->query("SELECT se.*, u.unit_name, u.unit_code FROM student_enrollments se JOIN course_units u ON se.unit_id = u.unit_id WHERE se.student_id = (SELECT student_id FROM students WHERE registration_number = '$reg')");
if ($result->num_rows > 0) {
    echo "Enrollments for $reg:\n";
    while ($row = $result->fetch_assoc()) {
        echo "Unit: " . $row['unit_name'] . " (" . $row['unit_code'] . ") - Academic Year: " . $row['academic_year'] . " - Semester: " . $row['semester'] . "\n";
    }
} else {
    echo "No enrollments found for $reg.\n";
}
