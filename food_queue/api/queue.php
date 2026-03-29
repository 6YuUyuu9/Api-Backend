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
require_once '../model/queue.php';

$db = new DB();
$conn = $db->getConnection();
$queue = new Queue($conn);

$method = $_SERVER['REQUEST_METHOD'];
$url = $_SERVER['REQUEST_URI'];
$data = json_decode(file_get_contents("php://input"), true) ?? [];

// 1. จองคิวใหม่ (POST)
if ($method === 'POST' && preg_match('#queue.php/add#', $url)) {
    
    $final_reserve = null;

    // ตรวจสอบทุกลูกแบบที่หน้าบ้านอาจส่งมา
    if (!empty($data['date']) && !empty($data['time'])) {
        // ถ้าส่งแยก date: "03/29/2026", time: "04:41 PM"
        $final_reserve = $data['date'] . ' ' . $data['time'];
    } elseif (!empty($data['reserve_date'])) {
        // ถ้าส่งรวมมาในชื่อ reserve_date
        $final_reserve = $data['reserve_date'];
    }

    // ถ้า $final_reserve ยังว่าง (แสดงว่าหน้าบ้านส่ง key มาไม่ตรง) 
    // ให้บังคับใช้เวลาปัจจุบันแค่ตรงนี้ที่เดียว
    if (!$final_reserve) {
        $final_reserve = date('Y-m-d H:i:s');
    }

    // เรียก Model โดยส่ง $final_reserve ที่เราอุตส่าห์ดักหามาได้เข้าไป
    $result = $queue->create(
        $data['user_id'], 
        $data['table_id'], 
        $data['person_count'], 
        $final_reserve
    );

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Queue added' : 'Failed to add queue',
        'debug_received' => $final_reserve // << เช็คตัวนี้ใน Network Tab ว่าเป็นวันไหน
    ]);
    exit();
}

// 2. ดูคิวทั้งหมด (GET)
if ($method === 'GET' && preg_match('#queue.php/list#', $url)) {
    $autoSkipQuery = "UPDATE queue 
                      SET status_id = 2 
                      WHERE status_id = 1 
                      AND DATE_ADD(reserve_date, INTERVAL 15 MINUTE) < NOW()";
    
    $conn->query($autoSkipQuery); 
    // --- จบส่วนที่เพิ่มเข้ามา ---

    // หลังจาก Skip แล้วค่อยดึงข้อมูลทั้งหมดไปแสดง
    $res = $queue->getAllQueues();
    echo json_encode($res);
    exit();
}

// ดูสรุปคิวของวัน (คิวล่าสุด และ จำนวนที่เหลือ) (GET)
// ตัวอย่าง URL: queue.php/summary?date=2026-03-29
if ($method === 'GET' && preg_match('#queue.php/summary#', $url)) {
    // ถ้าไม่ส่งวันที่มา ให้ใช้วันที่ปัจจุบัน
    $target_date = $_GET['date'] ?? date('Y-m-d');

    $latest = $queue->getLatestQueueByDate($target_date);
    $remaining = $queue->getRemainingQueueCount($target_date);

    echo json_encode([
        'success' => true,
        'target_date' => $target_date,
        'latest_queue' => $latest,
        'remaining_count' => $remaining
    ]);
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

// 5. Match โต๊ะอัตโนมัติ
if ($method === 'POST' && preg_match('#queue\.php/find-table#', $url)) {
    $date        = $data['date'];
    $arriveTime  = $data['arrive_time'];
    $personCount = (int)$data['person_count'];
    $reserve_datetime = $date . ' ' . $arriveTime . ':00';

    $matched = $queue->autoMatchTable($personCount, $reserve_datetime);

    if ($matched !== null) {
        echo json_encode([
            'success'  => true,
            'available' => true,
            'table_id' => $matched,
            'table'     => ['table_id' => $matched], 
            'message'  => "มีโต๊ะว่าง (table_id: $matched)"
        ]);
    } else {
        echo json_encode([
            'success'  => false,
            'available' => false,
            'message'  => 'ไม่มีโต๊ะว่างในช่วงเวลานี้'
        ]);
    }
    exit();
}

http_response_code(404);
echo json_encode(['message' => 'Queue API endpoint not found']);