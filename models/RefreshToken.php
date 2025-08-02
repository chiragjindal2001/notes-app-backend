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
        $insertId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        
        return $insertId ? $this->getById($insertId) : false;
    }

    public function getByToken($token) {
        $sql = 'SELECT * FROM refresh_tokens WHERE token = ? AND revoked = FALSE';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    public function getById($id) {
        $sql = 'SELECT * FROM refresh_tokens WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    public function revoke($token) {
        $sql = 'UPDATE refresh_tokens SET revoked = TRUE WHERE token = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affectedRows > 0;
    }

    public function revokeAllForUser($user_id) {
        $sql = 'UPDATE refresh_tokens SET revoked = TRUE WHERE user_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affectedRows > 0;
    }
} 