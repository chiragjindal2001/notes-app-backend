<?php

// No namespaces - using direct PostgreSQL functions

class User
{
    private $conn;

    public function __construct($connection = null)
    {
        $this->conn = $connection;
    }

    // Existing methods
    public function create($data)
    {
        if (isset($data['password_hash'])) {
            $sql = 'INSERT INTO users (email, password_hash, name, google_id, image, email_verified, is_verified, created_at) 
                    VALUES ($1, $2, $3, $4, $5, $6, $7, $8) 
                    RETURNING id, email, name, google_id, image, email_verified, is_verified, created_at';
            $result = pg_query_params($this->conn, $sql, [
                $data['email'] ?? null,
                $data['password_hash'] ?? null,
                $data['name'] ?? null,
                $data['google_id'] ?? null,
                $data['image'] ?? null,
                $data['email_verified'] ?? false,
                $data['is_verified'] ?? false,
                $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);
        } else {
            $sql = 'INSERT INTO users (email, name, google_id, image, email_verified, is_verified, created_at) 
                    VALUES ($1, $2, $3, $4, $5, $6, $7) 
                    RETURNING id, email, name, google_id, image, email_verified, is_verified, created_at';
            $result = pg_query_params($this->conn, $sql, [
                $data['email'] ?? null,
                $data['name'] ?? null,
                $data['google_id'] ?? null,
                $data['image'] ?? null,
                $data['email_verified'] ?? false,
                $data['is_verified'] ?? false,
                $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
        
        return $result ? pg_fetch_assoc($result) : false;
    }

    public function getByEmail($email)
    {
        $sql = 'SELECT * FROM users WHERE email = $1';
        $result = pg_query_params($this->conn, $sql, [$email]);
        return $result ? pg_fetch_assoc($result) : false;
    }

    public function getById($id)
    {
        $sql = 'SELECT * FROM users WHERE id = $1';
        $result = pg_query_params($this->conn, $sql, [$id]);
        return $result ? pg_fetch_assoc($result) : false;
    }

    public function findByGoogleId($googleId)
    {
        $sql = 'SELECT * FROM users WHERE google_id = $1';
        $result = pg_query_params($this->conn, $sql, [$googleId]);
        return $result ? pg_fetch_assoc($result) : false;
    }

    public function emailExists($email)
    {
        $sql = 'SELECT COUNT(*) as count FROM users WHERE email = $1';
        $result = pg_query_params($this->conn, $sql, [$email]);
        $row = pg_fetch_assoc($result);
        return $row && $row['count'] > 0;
    }

    public function updateProfile($id, $name, $email = null)
    {
        if ($email) {
            $sql = 'UPDATE users SET name = $1, email = $2 WHERE id = $3 RETURNING id, email, name';
            $result = pg_query_params($this->conn, $sql, [$name, $email, $id]);
        } else {
            $sql = 'UPDATE users SET name = $1 WHERE id = $2 RETURNING id, email, name';
            $result = pg_query_params($this->conn, $sql, [$name, $id]);
        }
        return $result ? pg_fetch_assoc($result) : false;
    }

    public function updateGoogleId($userId, $googleId, $userInfo = null)
    {
        if ($userInfo) {
            // Update with additional user info if available
            $sql = 'UPDATE users SET 
                    google_id = $1, 
                    name = COALESCE($2, name),
                    email = COALESCE($3, email),
                    image = COALESCE($4, image),
                    email_verified = COALESCE($5, email_verified),
                    is_verified = TRUE
                    WHERE id = $6 
                    RETURNING id, google_id, name, email, image, email_verified, is_verified';
            
            $result = pg_query_params($this->conn, $sql, [
                $googleId,
                $userInfo->name ?? null,
                $userInfo->email ?? null,
                $userInfo->picture ?? null,
                $userInfo->email_verified ?? true,
                $userId
            ]);
        } else {
            // Just update the Google ID if no additional info is provided
            $sql = 'UPDATE users SET google_id = $1, is_verified = TRUE WHERE id = $2 RETURNING id, google_id, is_verified';
            $result = pg_query_params($this->conn, $sql, [$googleId, $userId]);
        }
        
        return $result ? pg_fetch_assoc($result) : false;
    }

    public function updatePassword($id, $new_password_hash)
    {
        $sql = 'UPDATE users SET password_hash = $1 WHERE id = $2 RETURNING id';
        $result = pg_query_params($this->conn, $sql, [$new_password_hash, $id]);
        return $result ? pg_fetch_assoc($result) : false;
    }
}
