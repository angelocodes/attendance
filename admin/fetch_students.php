<?php
require_once "../db.php";

if (isset($_GET['unit_id']) && isset($_GET['academic_year']) && isset($_GET['semester'])) {
    $unit_id = intval($_GET['unit_id']);
    $academic_year = $_GET['academic_year'];
    $semester = intval($_GET['semester']);
    $search = trim($_GET['search'] ?? '');

    // Get course_id from unit_id
    $course_stmt = $conn->prepare("SELECT course_id FROM course_units WHERE unit_id = ?");
    $course_stmt->bind_param("i", $unit_id);
    $course_stmt->execute();
    $course_res = $course_stmt->get_result();
    if (!$course_res->num_rows) {
        echo '';
        exit;
    }
    $course_row = $course_res->fetch_assoc();
    $course_id = $course_row['course_id'];

    $enrollment_condition = $semester > 0 ? "AND se.semester = ?" : "";
    $query = "SELECT s.student_id, s.registration_number, s.first_name, s.last_name, s.year_of_study, c.course_name, ay.start_year, ay.end_year,
                     (SELECT COUNT(*) FROM student_enrollments se
                      WHERE se.student_id = s.student_id AND se.unit_id = ? AND se.academic_year = ? $enrollment_condition) AS is_enrolled
              FROM students s
              JOIN courses c ON s.course_id = c.course_id
              JOIN academic_years ay ON s.academic_year_id = ay.academic_year_id
              WHERE s.course_id = ? AND s.status = 'active'";
    $params = $semester > 0 ? [$unit_id, $academic_year, $semester, $course_id] : [$unit_id, $academic_year, $course_id];
    $types = $semester > 0 ? "isii" : "isi";

    if ($search !== '') {
        $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.registration_number LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= "sss";
    }

    $query .= " ORDER BY s.registration_number";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
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
                        <input type='checkbox' class='enrollment-checkbox' value='{$row['student_id']}' {$checked}>
                    </td>
                  </tr>";
    }
    echo $rows;
    $stmt->close();
}
?>
