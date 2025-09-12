<?php
include '../db.php';

$school_id = $_GET['school_id'] ?? '';
$department_id = $_GET['department_id'] ?? '';

$query = "SELECT d.*, s.school_name FROM departments d JOIN schools s ON d.school_id = s.school_id";
$params = [];
if($school_id) $query .= " WHERE d.school_id=" . intval($school_id);
if($department_id) $query .= $department_id ? " WHERE d.department_id=" . intval($department_id) : "";

$res = $conn->query($query);
$data = [];
while($row = $res->fetch_assoc()) $data[] = $row;
echo json_encode($data);
