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

// 5. Match โต๊ะอัตโนมัติ
if ($method === 'POST' && preg_match('#queue.php/find-table#', $url)) {
    $date = $data['date'];
    $arriveTime = $data['arrive_time'];
    $personCount = (int)$data['person_count'];

    // หาโต๊ะที่ถูกจองในช่วงเวลาเดียวกัน (±1 ชั่วโมง)
    $bookedQuery = "
        SELECT DISTINCT table_id 
        FROM queue 
        WHERE DATE(reserve_date) = :date
        AND ABS(TIMESTAMPDIFF(MINUTE, arrive_at, CONCAT(:date2, ' ', :time))) < 60
        AND status_id != 3
    ";
    $stmt = $conn->prepare($bookedQuery);
    $stmt->execute([':date' => $date, ':date2' => $date, ':time' => $arriveTime]);
    $bookedTableIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // หาโต๊ะที่ว่าง โดย match type_name กับจำนวนคน
    if ($personCount <= 2) {
        $minType = 'for2';
    } elseif ($personCount <= 4) {
        $minType = 'for4';
    } else {
        $minType = 'for6';
    }

    if (count($bookedTableIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($bookedTableIds), '?'));
        $tableQuery = "
            SELECT t.table_id, t.table_name, tt.type_name
            FROM tables t
            JOIN table_type tt ON t.type_id = tt.type_id
            WHERE t.table_id NOT IN ($placeholders)
            AND CAST(SUBSTRING(tt.type_name, 4) AS UNSIGNED) >= ?
            ORDER BY CAST(SUBSTRING(tt.type_name, 4) AS UNSIGNED) ASC
            LIMIT 1
        ";
        $stmt2 = $conn->prepare($tableQuery);
        $stmt2->execute(array_merge($bookedTableIds, [$personCount]));
    } else {
        $tableQuery = "
            SELECT t.table_id, t.table_name, tt.type_name
            FROM tables t
            JOIN table_type tt ON t.type_id = tt.type_id
            WHERE CAST(SUBSTRING(tt.type_name, 4) AS UNSIGNED) >= ?
            ORDER BY CAST(SUBSTRING(tt.type_name, 4) AS UNSIGNED) ASC
            LIMIT 1
        ";
        $stmt2 = $conn->prepare($tableQuery);
        $stmt2->execute([$personCount]);
    }

    $table = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($table) {
        echo json_encode(['success' => true, 'table' => $table]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่มีโต๊ะว่างในช่วงเวลานี้']);
    }
    exit();
}

http_response_code(404);
echo json_encode(['message' => 'Queue API endpoint not found']);