<?php
require_once('../db.php');
require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

// Fetch student info
$stmt = $conn->prepare("SELECT first_name, last_name, reg_no FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Fetch attendance data
$query = "
    SELECT cu.unit_code, cu.unit_name, a.status, a.date
    FROM attendance a
    JOIN course_units cu ON a.unit_id = cu.unit_id
    WHERE a.student_id = ?
    ORDER BY a.date DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// Initialize TCPDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('University Attendance System');
$pdf->SetTitle('Attendance Report');
$pdf->AddPage();

// Header
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Attendance Report', 0, 1, 'C');
$pdf->Ln(5);

// Student Info
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, "Name: {$student['first_name']} {$student['last_name']}", 0, 1);
$pdf->Cell(0, 10, "Reg No: {$student['reg_no']}", 0, 1);
$pdf->Ln(5);

// Attendance Table
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(40, 10, 'Date', 1);
$pdf->Cell(60, 10, 'Course Unit', 1);
$pdf->Cell(30, 10, 'Code', 1);
$pdf->Cell(30, 10, 'Status', 1);
$pdf->Ln();

$pdf->SetFont('helvetica', '', 11);
while ($row = $result->fetch_assoc()) {
    $pdf->Cell(40, 10, $row['date'], 1);
    $pdf->Cell(60, 10, $row['unit_name'], 1);
    $pdf->Cell(30, 10, $row['unit_code'], 1);

    // Status with color
    $status = ucfirst($row['status']);
    if ($row['status'] === 'Present') {
        $pdf->SetTextColor(0, 150, 0);
    } elseif ($row['status'] === 'Absent') {
        $pdf->SetTextColor(200, 0, 0);
    } else {
        $pdf->SetTextColor(200, 150, 0);
    }
    $pdf->Cell(30, 10, $status, 1);
    $pdf->Ln();
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Output('attendance_report.pdf', 'I');
