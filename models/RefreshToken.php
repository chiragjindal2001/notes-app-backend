<?php
class RefreshToken {
    private $conn;
    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function create($user_id, $token, $expires_at) {
        $sql = 'INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iss', $user_id, $token, $expires_at);
        mysqli_stmt_execute($stmt);
        
        // Get the inserted ID
        $insertedId = mysqli_insert_id($this->conn);
        
        // Fetch the inserted record
        $selectStmt = mysqli_prepare($this->conn, 'SELECT * FROM refresh_tokens WHERE id = ?');
        mysqli_stmt_bind_param($selectStmt, 'i', $insertedId);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        return mysqli_fetch_assoc($result);
    }

    public function getByToken($token) {
        $sql = 'SELECT * FROM refresh_tokens WHERE token = ? AND revoked = FALSE';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    public function revoke($token) {
        $sql = 'UPDATE refresh_tokens SET revoked = TRUE WHERE token = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $token);
        return mysqli_stmt_execute($stmt);
    }

    public function revokeAllForUser($user_id) {
        $sql = 'UPDATE refresh_tokens SET revoked = TRUE WHERE user_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        return mysqli_stmt_execute($stmt);
    }
} 