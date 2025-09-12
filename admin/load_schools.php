<?php
include '../db.php';
$res = $conn->query("SELECT school_id, school_name FROM schools ORDER BY school_name");
$data = [];
while($row = $res->fetch_assoc()) $data[] = $row;
echo json_encode($data);
