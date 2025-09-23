<?php
include 'db.php';
$result = $conn->query("SELECT sch.school_id, sch.school_name, c.course_id, c.course_name, c.course_code FROM schools sch LEFT JOIN courses c ON sch.school_id = c.school_id ORDER BY sch.school_id, c.course_id");
echo "Schools and their courses:\n";
$current_school = '';
while ($row = $result->fetch_assoc()) {
    if ($current_school != $row['school_name']) {
        echo "\nSchool: " . $row['school_name'] . " (ID: " . $row['school_id'] . ")\n";
        $current_school = $row['school_name'];
    }
    if ($row['course_id']) {
        echo "  - " . $row['course_name'] . " (" . $row['course_code'] . ")\n";
    }
}
