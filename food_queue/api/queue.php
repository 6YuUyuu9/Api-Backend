<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../model/queue.php';

$db = new DB();
$conn = $db->getConnection();
$queue = new Queue($conn);

$method = $_SERVER['REQUEST_METHOD'];
$url = $_SERVER['REQUEST_URI'];
$data = json_decode(file_get_contents("php://input"), true) ?? [];

// 1. จองคิวใหม่ (POST)
if ($method === 'POST' && preg_match('#queue.php/add#', $url)) {
    $result = $queue->create($data['user_id'], $data['table_id'], $data['person_count']);
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Queue added' : 'Failed to add queue'
    ]);
    exit();
}

// 2. ดูคิวทั้งหมด (GET)
if ($method === 'GET' && preg_match('#queue.php/list#', $url)) {
    $res = $queue->getAllQueues();
    echo json_encode($res);
    exit();
}

// 5. แก้ไขข้อมูลคิว (POST)
if ($method === 'POST' && preg_match('#queue.php/update$#', $url)) {
    $result = $queue->update(
        $data['queue_id'], 
        $data['table_id'], 
        $data['person_count']
    );
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Queue updated successfully' : 'Update failed'
    ]);
    exit();
}

// 3. อัปเดตสถานะคิว (POST/PUT)
if ($method === 'POST' && preg_match('#queue.php/update-status#', $url)) {
    $result = $queue->updateStatus($data['queue_id'], $data['status_id']);
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Status updated' : 'Update failed'
    ]);
    exit();
}

// บันทึกเวลาที่มาถึง
if ($method === 'POST' && preg_match('#queue.php/arrive#', $url)) {
    $result = $queue->markArrived($data['queue_id']);
    echo json_encode(['success' => $result]);
    exit();
}

// ข้ามคิว (Skip)
if ($method === 'POST' && preg_match('#queue.php/skip#', $url)) {
    $result = $queue->skipQueue($data['queue_id']);
    echo json_encode(['success' => $result]);
    exit();
}

// บันทึกเวลาที่เสร็จสิ้น
if ($method === 'POST' && preg_match('#queue.php/complete#', $url)) {
    $result = $queue->markCompleted($data['queue_id']);
    echo json_encode(['success' => $result]);
    exit();
}

// 4. ลบคิว (POST/DELETE)
if ($method === 'DELETE' && preg_match('#queue.php/delete#', $url)) {
    $result = $queue->delete($data['queue_id']);
    echo json_encode([
        'success' => $result
    ]);
    exit();
}

http_response_code(404);
echo json_encode(['message' => 'Queue API endpoint not found']);