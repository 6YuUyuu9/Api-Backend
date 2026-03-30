<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


require_once '../db.php';
require_once '../model/table.php';

$db = new DB();
$conn = $db->getConnection();
$tableObj = new Table($conn);

$method = $_SERVER['REQUEST_METHOD'];
$url = $_SERVER['REQUEST_URI'];
$data = json_decode(file_get_contents("php://input"), true) ?? [];

// 1. ดึงรายการโต๊ะทั้งหมด (GET)
if ($method === 'GET' && preg_match('#table.php/list#', $url)) {
    echo json_encode($tableObj->getAllTables());
    exit();
}

// เพิ่มส่วนนี้ใน api/table.php
if ($method === 'GET' && preg_match('#table.php/types$#', $url)) {
    echo json_encode($tableObj->getTableTypes());
    exit();
}

// 2. เพิ่มโต๊ะ (POST)
if ($method === 'POST' && preg_match('#table.php/add#', $url)) {
    $result = $tableObj->create($data['table_name'], $data['type_id']);
    echo json_encode(['success' => $result]);
    exit();
}

// 3. แก้ไขโต๊ะ (POST)
if ($method === 'POST' && preg_match('#table.php/update#', $url)) {
    $result = $tableObj->update($data['table_id'], $data['table_name'], $data['type_id']);
    echo json_encode(['success' => $result]);
    exit();
}

// 4. ลบโต๊ะ (DELETE)
if ($method === 'DELETE' && preg_match('#table.php/delete#', $url)) {
    $result = $tableObj->delete($data['table_id']);
    echo json_encode(['success' => $result]);
    exit();
}

http_response_code(404);
echo json_encode(['message' => 'Table API not found']);