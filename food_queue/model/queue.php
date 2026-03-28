<?php
class Queue
{
    private $conn;
    private $table = "queue";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // เพิ่มคิวใหม่
    public function create($user_id, $table_id, $person_count)
    {
        $sql = "INSERT INTO " . $this->table . " 
                (user_id, table_id, person_count, status_id, reserve_date) 
                VALUES (:user_id, :table_id, :person_count, 1, NOW())"; // status_id 1 = reserved

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':table_id', $table_id);
        $stmt->bindParam(':person_count', $person_count);

        return $stmt->execute();
    }

    // ดึงคิวทั้งหมด พร้อมรายละเอียดชื่อผู้ใช้และประเภทโต๊ะ
    public function getAllQueues() {
    // ตรวจสอบว่ามี q.arrive_at และ q.complete_at ใน SELECT หรือยัง
    $sql = "SELECT 
                q.queue_id, 
<<<<<<< Updated upstream
                q.user_id, 
                u.username,
=======
                q.queue_name,
                q.user_id,
                u.username, 
>>>>>>> Stashed changes
                t.table_name, 
                tt.type_name, 
                q.person_count, 
                s.status_name, 
                q.status_id,
                q.reserve_date, 
                q.arrive_at,    -- << ต้องมีตัวนี้
                q.complete_at   -- << ต้องมีตัวนี้
            FROM " . $this->table . " q
            JOIN users u ON q.user_id = u.user_id
            JOIN tables t ON q.table_id = t.table_id
            JOIN table_type tt ON t.type_id = tt.type_id
            JOIN status s ON q.status_id = s.status_id
            ORDER BY q.reserve_date DESC";
    
    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    // แก้ไขข้อมูลคิว (เช่น เปลี่ยนโต๊ะ หรือ จำนวนคน)
    public function update($queue_id, $table_id, $person_count)
    {
        $sql = "UPDATE " . $this->table . " 
            SET table_id = :table_id, 
                person_count = :person_count 
            WHERE queue_id = :queue_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':table_id', $table_id);
        $stmt->bindParam(':person_count', $person_count);
        $stmt->bindParam(':queue_id', $queue_id);

        return $stmt->execute();
    }

    // อัปเดตสถานะคิว (เช่น เป็น skipped หรือ completed)
    public function updateStatus($queue_id, $status_id)
    {
        $sql = "UPDATE " . $this->table . " 
                SET status_id = :status_id, 
                    arrive_at = IF(:status_id = 3, arrive_at, arrive_at), -- ปรับ logic เวลาตามต้องการ
                    complete_at = IF(:status_id = 3, NOW(), complete_at)
                WHERE queue_id = :queue_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':status_id', $status_id);
        $stmt->bindParam(':queue_id', $queue_id);

        return $stmt->execute();
    }

    public function markArrived($queue_id)
    {
        $sql = "UPDATE " . $this->table . " 
            SET arrive_at = NOW() 
            WHERE queue_id = :queue_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':queue_id', $queue_id);
        return $stmt->execute();
    }

    public function skipQueue($queue_id)
    {
        $sql = "UPDATE " . $this->table . " 
            SET status_id = 2 
            WHERE queue_id = :queue_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':queue_id', $queue_id);
        return $stmt->execute();
    }

    public function markCompleted($queue_id)
    {
        // เปลี่ยนสถานะเป็น 3 (completed) พร้อมบันทึกเวลา
        $sql = "UPDATE " . $this->table . " 
            SET status_id = 3, 
                complete_at = NOW() 
            WHERE queue_id = :queue_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':queue_id', $queue_id);
        return $stmt->execute();
    }

    // ลบคิว
    public function delete($queue_id)
    {
        $sql = "DELETE FROM " . $this->table . " WHERE queue_id = :queue_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':queue_id', $queue_id);
        return $stmt->execute();
    }
}