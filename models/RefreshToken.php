<?php
class RefreshToken {
    private $conn;
    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function create($user_id, $token, $expires_at) {
        $sql = 'INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES ($1, $2, $3) RETURNING *';
        $result = pg_query_params($this->conn, $sql, [$user_id, $token, $expires_at]);
        return $result ? pg_fetch_assoc($result) : false;
    }

    public function getByToken($token) {
        $sql = 'SELECT * FROM refresh_tokens WHERE token = $1 AND revoked = FALSE';
        $result = pg_query_params($this->conn, $sql, [$token]);
        return $result ? pg_fetch_assoc($result) : false;
    }

    public function revoke($token) {
        $sql = 'UPDATE refresh_tokens SET revoked = TRUE WHERE token = $1';
        $result = pg_query_params($this->conn, $sql, [$token]);
        return $result !== false;
    }

    public function revokeAllForUser($user_id) {
        $sql = 'UPDATE refresh_tokens SET revoked = TRUE WHERE user_id = $1';
        $result = pg_query_params($this->conn, $sql, [$user_id]);
        return $result !== false;
    }
} 