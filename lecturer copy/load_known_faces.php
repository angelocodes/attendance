<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'lecturer') {
    header('Location: ../login.php');
    exit;
}

$lecturer_id = $_SESSION['user_id'];
require_once "../db.php";

$assignment_id = intval($_GET['assignment_id'] ?? 0);

// Verify assignment belongs to lecturer
$stmt = $conn->prepare("SELECT assignment_id FROM lecturer_assignments WHERE assignment_id = ? AND lecturer_id = ?");
$stmt->bind_param("ii", $assignment_id, $lecturer_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(403);
    exit();
}

// Example structure:
// [
//   {
//     "label": "student_id",
//     "descriptors": [
//        [0.1, 0.2, ..., 0.3],
//        ...
//     ]
//   },
//   ...
// ]

// Here we mock some sample data, in real life you'd fetch from your storage of face descriptors
// You can extend this to load from files or database blobs

$knownFaces = [];

// Query students enrolled in this unit/assignment
$stmt = $conn->prepare("
    SELECT u.user_id, u.fullname FROM enrollments e
    JOIN users u ON e.student_id = u.user_id
    WHERE e.assignment_id = ?
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();

while ($student = $result->fetch_assoc()) {
    // Here we should load stored descriptors, for demo return empty array or mock data
    $knownFaces[] = [
        "label" => strval($student['user_id']),
        "descriptors" => [
            // Insert real Float32Array descriptor arrays here from your data
        ],
    ];
}

header('Content-Type: application/json');
echo json_encode($knownFaces);
