<?php
class User {
    private $conn;
    private $table = "users";

    public function __construct($db){
        $this->conn = $db;
    }

    // REGISTER (สมัครสมาชิก)
    public function register($username, $password, $role = 'user') {
        // เข้ารหัสรหัสผ่านเพื่อความปลอดภัย
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // ตรวจสอบว่ามี username นี้หรือยัง
        $check = $this->conn->prepare("SELECT user_id FROM " . $this->table . " WHERE username = :username");
        $check->bindParam(":username", $username);
        $check->execute();
        
        if($check->rowCount() > 0) {
            return false; // หรือจะ throw error ว่า username ซ้ำก็ได้ครับ
        }

        // เพิ่มข้อมูลลงตาราง users ตามโครงสร้าง SQL ของคุณ
        $sql = "INSERT INTO " . $this->table . " (username, password, role) 
                VALUES (:username, :password, :role)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":password", $hash);
        $stmt->bindParam(":role", $role);

        return $stmt->execute();
    }

    // LOGIN
    public function login($username, $password) {
        $sql = "SELECT * FROM " . $this->table . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ตรวจสอบรหัสผ่านที่เข้ารหัสไว้
        if($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    // CHANGE PASSWORD
    public function changePassword($id, $old_password, $new_password) {
        $sql = "SELECT password FROM " . $this->table . " WHERE user_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$row || !password_verify($old_password, $row['password'])) {
            return false;
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE " . $this->table . " SET password = :password WHERE user_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":password", $new_hash);
        $stmt->bindParam(":id", $id);

        return $stmt->execute();
    }

    // GET ALL USERS
    public function getUsers() {
        // เลือกเฉพาะคอลัมน์ที่ไม่ใช่ password เพื่อความปลอดภัย
        $stmt = $this->conn->prepare("SELECT user_id, username, role, create_at FROM " . $this->table);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // GET USER BY ID
    public function getUserById($id) {
        $sql = "SELECT user_id, username, role, create_at FROM " . $this->table . " WHERE user_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }   

    // UPDATE USER (แก้ไข username หรือ role)
    public function update($id, $username, $role) {
        $sql = "UPDATE " . $this->table . " 
                SET username = :username, role = :role 
                WHERE user_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":role", $role);
        return $stmt->execute();
    }

    // DELETE USER
    public function delete($id) {
        // ในไฟล์ SQL ของคุณไม่มีฟิลด์ status แบบลบหลอก (Soft Delete) 
        // ดังนั้นจะเป็นการลบข้อมูลออกจากฐานข้อมูลจริงๆ
        $sql = "DELETE FROM " . $this->table . " WHERE user_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
}
?>