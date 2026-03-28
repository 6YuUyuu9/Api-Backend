<?php

class DB {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'reservation';

    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                    "mysql:host=".$this->host.";dbname=".$this->database.";charset=utf8",
                    $this->username,
                    $this->password
                );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }

        return $this->conn;
    }
}

?>