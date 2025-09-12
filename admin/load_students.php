<?php
require_once "../db.php";

$unit_id = intval($_GET['unit_id'] ?? 0);
$academic_year = $_GET['academic_year'] ?? '';
$semester = intval($_GET['semester'] ?? 0);
$search = trim($_GET['search'] ?? '');

if (!$unit_id || !$academic_year || !$semester) {
    echo '<tr><td colspan="3" class="text-center text-yellow-400">Select all required fields.</td></tr>';
    exit;
}

// Fetch students eligible for the selected course/unit
$sql = "SELECT s.student_id, s.registration_number, s.first_name, s.last_name,
               IF(se.student_id IS NULL,0,1) AS enrolled
        FROM students s
        LEFT JOIN student_enrollments se 
          ON s.student_id = se.student_id 
         AND se.unit_id = ? 
         AND se.academic_year = ? 
         AND se.semester = ?
        WHERE s.course_id = (
            SELECT course_id FROM course_units WHERE unit_id = ?
        )
        AND s.status = 'active'
        ".($search ? "AND (s.first_name LIKE ? OR s.last_name LIKE ?)" : "")."
        ORDER BY s.first_name, s.last_name";

$stmt = $conn->prepare($sql);
if($search){
    $like = "%$search%";
    $stmt->bind_param("ississ",$unit_id,$academic_year,$semester,$unit_id,$like,$like);
} else {
    $stmt->bind_param("issi",$unit_id,$academic_year,$semester,$unit_id);
}

$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0){
    echo '<tr><td colspan="3" class="text-center text-yellow-400">No students found.</td></tr>';
    exit;
}

while($row = $result->fetch_assoc()){
    $checked = $row['enrolled'] ? 'checked' : '';
    echo '<tr class="border-b border-gray-600">
            <td class="px-2 py-1 text-center">
                <input type="checkbox" class="enrollment-checkbox" value="'.$row['student_id'].'" '.$checked.'>
            </td>
            <td class="px-2 py-1">'.htmlspecialchars($row['registration_number']).'</td>
            <td class="px-2 py-1">'.htmlspecialchars($row['first_name'].' '.$row['last_name']).'</td>
          </tr>';
}
$stmt->close();
?>
