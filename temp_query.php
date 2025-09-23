<?php
include 'db.php';
$result = $conn->query('SELECT course_id, course_name, course_code FROM courses WHERE course_code LIKE "%BCOM%" OR course_code = "BCOM-FIN"');
echo "Courses with BCOM:\n";
while($row = $result->fetch_assoc()) {
    echo $row['course_id'] . ' - ' . $row['course_name'] . ' - ' . $row['course_code'] . "\n";
}

$result2 = $conn->query('SELECT s.registration_number, s.first_name, s.last_name, c.course_name, ay.start_year, ay.end_year FROM students s JOIN courses c ON s.course_id = c.course_id JOIN academic_years ay ON s.academic_year_id = ay.academic_year_id WHERE c.course_code LIKE "%BCOM%" ORDER BY s.registration_number');
if ($result2->num_rows > 0) {
    echo "\nStudents doing BCom:\n";
    while ($row = $result2->fetch_assoc()) {
        echo $row['registration_number'] . ' - ' . $row['first_name'] . ' ' . $row['last_name'] . ' - ' . $row['course_name'] . ' - Academic Year: ' . $row['start_year'] . '/' . $row['end_year'] . "\n";
    }
} else {
    echo "\nNo students found doing BCom.\n";
}
