<?php
require_once "../db.php";

if (isset($_POST['course_id']) && isset($_POST['academic_year']) && isset($_POST['unit_id']) && isset($_POST['semester'])) {
    $course_id = intval($_POST['course_id']);
    $academic_year = $_POST['academic_year'];
    $unit_id = intval($_POST['unit_id']);
    $semester = intval($_POST['semester']);

    $query = "SELECT s.student_id, s.registration_number, s.first_name, s.last_name, s.year_of_study, c.course_name, ay.start_year, ay.end_year,
                     (SELECT COUNT(*) FROM student_enrollments se
                      WHERE se.student_id = s.student_id AND se.unit_id = ? AND se.academic_year = ? AND se.semester = ?) AS is_enrolled
              FROM students s
              JOIN courses c ON s.course_id = c.course_id
              JOIN academic_years ay ON s.academic_year_id = ay.academic_year_id
              WHERE s.course_id = ? AND s.status = 'active' AND CONCAT(ay.start_year, '/', ay.end_year) = ?
              ORDER BY s.registration_number";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isisi", $unit_id, $academic_year, $semester, $course_id, $academic_year);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = '';
    while ($row = $result->fetch_assoc()) {
        $name = htmlspecialchars($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
        $checked = $row['is_enrolled'] > 0 ? 'checked' : '';
        $academic_year_display = htmlspecialchars($row['start_year'] . '/' . $row['end_year']);
        $rows .= "<tr class='border-b border-gray-700'>
                    <td class='px-4 py-2'>" . htmlspecialchars($row['registration_number']) . "</td>
                    <td class='px-4 py-2'>{$name}</td>
                    <td class='px-4 py-2'>" . htmlspecialchars($row['course_name']) . "</td>
                    <td class='px-4 py-2 text-center'>{$row['year_of_study']}</td>
                    <td class='px-4 py-2 text-center'>{$academic_year_display}</td>
                    <td class='px-4 py-2 text-center'>
                        <input type='checkbox' class='enrollment-checkbox' data-student-id='{$row['student_id']}' {$checked}>
                    </td>
                  </tr>";
    }
    echo $rows;
    $stmt->close();
}
?>