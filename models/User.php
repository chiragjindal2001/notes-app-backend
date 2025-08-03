<?php

// No namespaces - using direct PostgreSQL functions

class User
{
    private $conn;
    
    public function __construct($connection = null)
    {
        $this->conn = $connection;
    }

    public function create($data)
    {
        if ($this->emailExists($data['email'] ?? '')) {
            $sql = 'UPDATE users SET 
                    name = ?, 
                    google_id = ?, 
                    image = ?, 
                    email_verified = ?, 
                    is_verified = ?, 
                    updated_at = ? 
                    WHERE email = ?';
            $stmt = mysqli_prepare($this->conn, $sql);
            $name = $data['name'] ?? null;
            $google_id = $data['google_id'] ?? null;
            $image = $data['image'] ?? null;
            $email_verified = $data['email_verified'] ?? false;
            $is_verified = $data['is_verified'] ?? false;
            $updated_at = date('Y-m-d H:i:s');
            $email = $data['email'] ?? null;
            mysqli_stmt_bind_param($stmt, 'sssssss', $name, $google_id, $image, $email_verified, $is_verified, $updated_at, $email);
            mysqli_stmt_execute($stmt);
            
            // Fetch the updated record
            $selectStmt = mysqli_prepare($this->conn, 'SELECT id, email, name, google_id, image, email_verified, is_verified, created_at FROM users WHERE email = ?');
            mysqli_stmt_bind_param($selectStmt, 's', $email);
            mysqli_stmt_execute($selectStmt);
            $result = mysqli_stmt_get_result($selectStmt);
            return mysqli_fetch_assoc($result);
        } else {
            $sql = 'INSERT INTO users (email, name, google_id, image, email_verified, is_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($this->conn, $sql);
            $email = $data['email'] ?? null;
            $name = $data['name'] ?? null;
            $google_id = $data['google_id'] ?? null;
            $image = $data['image'] ?? null;
            $email_verified = $data['email_verified'] ?? false;
            $is_verified = $data['is_verified'] ?? false;
            $created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
            mysqli_stmt_bind_param($stmt, 'sssssss', $email, $name, $google_id, $image, $email_verified, $is_verified, $created_at);
            mysqli_stmt_execute($stmt);
            
            // Get the inserted ID
            $insertedId = mysqli_insert_id($this->conn);
            
            // Fetch the inserted record
            $selectStmt = mysqli_prepare($this->conn, 'SELECT id, email, name, google_id, image, email_verified, is_verified, created_at FROM users WHERE id = ?');
            mysqli_stmt_bind_param($selectStmt, 'i', $insertedId);
            mysqli_stmt_execute($selectStmt);
            $result = mysqli_stmt_get_result($selectStmt);
            return mysqli_fetch_assoc($result);
        }
    }

    public function getByEmail($email)
    {
        $sql = 'SELECT * FROM users WHERE email = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    public function getById($id)
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    public function findByGoogleId($googleId)
    {
        $sql = 'SELECT * FROM users WHERE google_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $googleId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    public function emailExists($email)
    {
        $sql = 'SELECT COUNT(*) as count FROM users WHERE email = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        return $row && $row['count'] > 0;
    }

    public function updateProfile($id, $name, $email = null)
    {
        if ($email) {
            $sql = 'UPDATE users SET name = ?, email = ? WHERE id = ?';
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssi', $name, $email, $id);
            mysqli_stmt_execute($stmt);
            
            // Fetch the updated record
            $selectStmt = mysqli_prepare($this->conn, 'SELECT id, email, name FROM users WHERE id = ?');
            mysqli_stmt_bind_param($selectStmt, 'i', $id);
            mysqli_stmt_execute($selectStmt);
            $result = mysqli_stmt_get_result($selectStmt);
            return mysqli_fetch_assoc($result);
        } else {
            $sql = 'UPDATE users SET name = ? WHERE id = ?';
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $name, $id);
            mysqli_stmt_execute($stmt);
            
            // Fetch the updated record
            $selectStmt = mysqli_prepare($this->conn, 'SELECT id, email, name FROM users WHERE id = ?');
            mysqli_stmt_bind_param($selectStmt, 'i', $id);
            mysqli_stmt_execute($selectStmt);
            $result = mysqli_stmt_get_result($selectStmt);
            return mysqli_fetch_assoc($result);
        }
    }

    public function updateGoogleId($userId, $googleId, $userInfo = null)
    {
        if ($userInfo) {
            // Update with additional user info if available
            $sql = 'UPDATE users SET 
                    google_id = ?, 
                    name = COALESCE(?, name),
                    email = COALESCE(?, email),
                    image = COALESCE(?, image),
                    email_verified = COALESCE(?, email_verified),
                    is_verified = TRUE
                    WHERE id = ?';
            
            $stmt = mysqli_prepare($this->conn, $sql);
            $name = $userInfo->name ?? null;
            $email = $userInfo->email ?? null;
            $image = $userInfo->picture ?? null;
            $email_verified = $userInfo->email_verified ?? true;
            mysqli_stmt_bind_param($stmt, 'sssssi', $googleId, $name, $email, $image, $email_verified, $userId);
            mysqli_stmt_execute($stmt);
            
            // Fetch the updated record
            $selectStmt = mysqli_prepare($this->conn, 'SELECT id, google_id, name, email, image, email_verified, is_verified FROM users WHERE id = ?');
            mysqli_stmt_bind_param($selectStmt, 'i', $userId);
            mysqli_stmt_execute($selectStmt);
            $result = mysqli_stmt_get_result($selectStmt);
            return mysqli_fetch_assoc($result);
        } else {
            // Just update the Google ID if no additional info is provided
            $sql = 'UPDATE users SET google_id = ?, is_verified = TRUE WHERE id = ?';
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $googleId, $userId);
            mysqli_stmt_execute($stmt);
            
            // Fetch the updated record
            $selectStmt = mysqli_prepare($this->conn, 'SELECT id, google_id, is_verified FROM users WHERE id = ?');
            mysqli_stmt_bind_param($selectStmt, 'i', $userId);
            mysqli_stmt_execute($selectStmt);
            $result = mysqli_stmt_get_result($selectStmt);
            return mysqli_fetch_assoc($result);
        }
    }

    public function updatePassword($id, $new_password_hash)
    {
        $sql = 'UPDATE users SET password_hash = ? WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $new_password_hash, $id);
        mysqli_stmt_execute($stmt);
        
        // Fetch the updated record
        $selectStmt = mysqli_prepare($this->conn, 'SELECT id FROM users WHERE id = ?');
        mysqli_stmt_bind_param($selectStmt, 'i', $id);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        return mysqli_fetch_assoc($result);
    }
}
