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
        // 1. กำหนด Prefix ตามจำนวนคน
        // ตัวอย่าง: 1-4 คน = A (โต๊ะเล็ก), 5 คนขึ้นไป = B (โต๊ะใหญ่)
        $prefix = ($person_count <= 4) ? "A" : "B";

        // 2. นับจำนวนคิวของ "วันนี้" เฉพาะกลุ่ม (Prefix) นั้นๆ
        $today = date('Y-m-d');
        $sql_count = "SELECT COUNT(*) as total FROM " . $this->table . " 
                  WHERE DATE(reserve_date) = :today 
                  AND queue_name LIKE :prefix";

        $stmt_count = $this->conn->prepare($sql_count);
        $search_prefix = $prefix . "%";
        $stmt_count->bindParam(':today', $today);
        $stmt_count->bindParam(':prefix', $search_prefix);
        $stmt_count->execute();
        $row = $stmt_count->fetch(PDO::FETCH_ASSOC);

        // 3. รันเลขต่อจากลำดับเดิม (Reset ทุกวันเพราะเช็คจาก :today)
        $next_number = $row['total'] + 1;

        // ใช้ str_pad เพื่อให้เป็น A01, A02 (ดูเป็นระเบียบกว่า A1, A2)
        $queue_name = $prefix . str_pad($next_number, 2, "0", STR_PAD_LEFT);

        // 4. บันทึกข้อมูลลงฐานข้อมูล
        $sql = "INSERT INTO " . $this->table . " 
            (queue_name, user_id, table_id, person_count, status_id, reserve_date) 
            VALUES (:q_name, :u_id, :t_id, :count, 1, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':q_name', $queue_name);
        $stmt->bindParam(':u_id', $user_id);
        $stmt->bindParam(':t_id', $table_id);
        $stmt->bindParam(':count', $person_count);

        return $stmt->execute();
    }

    // ดึงคิวทั้งหมด พร้อมรายละเอียดชื่อผู้ใช้และประเภทโต๊ะ
    public function getAllQueues()
    {
        // ตรวจสอบว่ามี q.arrive_at และ q.complete_at ใน SELECT หรือยัง
        $sql = "SELECT 
                q.queue_id, 
                u.username, 
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