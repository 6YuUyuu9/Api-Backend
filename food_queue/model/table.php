<?php
class Table
{
    private $conn;
    private $table = "tables";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ดึงข้อมูลโต๊ะทั้งหมด พร้อมชื่อประเภทโต๊ะ
    public function getAllTables()
    {
        $sql = "SELECT t.table_id, t.table_name, tt.type_name 
                FROM " . $this->table . " t
                JOIN table_type tt ON t.type_id = tt.type_id
                ORDER BY t.table_name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ดึงรายการประเภทโต๊ะทั้งหมด (for dropdown)
    public function getTableTypes()
    {
        $sql = "SELECT * FROM table_type ORDER BY type_id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // เพิ่มโต๊ะใหม่
    public function create($table_name, $type_id)
    {
        $sql = "INSERT INTO " . $this->table . " (table_name, type_id) VALUES (:name, :type_id)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $table_name);
        $stmt->bindParam(':type_id', $type_id);
        return $stmt->execute();
    }

    // แก้ไขข้อมูลโต๊ะ
    public function update($table_id, $table_name, $type_id)
    {
        $sql = "UPDATE " . $this->table . " 
                SET table_name = :name, type_id = :type_id 
                WHERE table_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $table_id);
        $stmt->bindParam(':name', $table_name);
        $stmt->bindParam(':type_id', $type_id);
        return $stmt->execute();
    }

    // ลบโต๊ะ
    public function delete($table_id)
    {
        $sql = "DELETE FROM " . $this->table . " WHERE table_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $table_id);
        return $stmt->execute();
    }
}