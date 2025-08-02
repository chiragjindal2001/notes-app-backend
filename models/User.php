<?php

// No namespaces - using direct MySQL functions

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
            $sql = 'INSERT INTO users (email, password_hash, name, google_id, avatar_url, email_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssssss', 
                $data['email'] ?? null,
                $data['password_hash'] ?? null,
                $data['name'] ?? null,
                $data['google_id'] ?? null,
                $data['avatar_url'] ?? null,
                $data['email_verified'] ?? false,
                $data['created_at'] ?? date('Y-m-d H:i:s')
            );
            mysqli_stmt_execute($stmt);
            $insertId = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);
            
            return $this->getById($insertId);
        } else {
            $sql = 'INSERT INTO users (email, name, google_id, avatar_url, email_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssssss', 
                $data['email'] ?? null,
                $data['name'] ?? null,
                $data['google_id'] ?? null,
                $data['avatar_url'] ?? null,
                $data['email_verified'] ?? false,
                $data['created_at'] ?? date('Y-m-d H:i:s')
            );
            mysqli_stmt_execute($stmt);
            $insertId = mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);
            
            return $this->getById($insertId);
        }
    }

    public function getByEmail($email)
    {
        $sql = 'SELECT * FROM users WHERE email = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    public function getById($id)
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    public function findByGoogleId($googleId)
    {
        $sql = 'SELECT * FROM users WHERE google_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $googleId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    public function emailExists($email)
    {
        $sql = 'SELECT COUNT(*) as count FROM users WHERE email = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row && $row['count'] > 0;
    }

    public function updateProfile($id, $name, $email = null)
    {
        if ($email) {
            $sql = 'UPDATE users SET name = ?, email = ? WHERE id = ?';
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssi', $name, $email, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $sql = 'UPDATE users SET name = ? WHERE id = ?';
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $name, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        return $this->getById($id);
    }

    public function updateGoogleId($userId, $googleId, $userInfo = null)
    {
        if ($userInfo) {
            // Update with additional user info if available
            $sql = 'UPDATE users SET 
                    google_id = ?, 
                    name = COALESCE(?, name),
                    email = COALESCE(?, email),
                    avatar_url = COALESCE(?, avatar_url),
                    email_verified = COALESCE(?, email_verified)
                    WHERE id = ?';
            
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssssi', 
                $googleId,
                $userInfo->name ?? null,
                $userInfo->email ?? null,
                $userInfo->picture ?? null,
                $userInfo->email_verified ?? true,
                $userId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            // Just update the Google ID if no additional info is provided
            $sql = 'UPDATE users SET google_id = ? WHERE id = ?';
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $googleId, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        return $this->getById($userId);
    }

    public function updatePassword($id, $new_password_hash)
    {
        $sql = 'UPDATE users SET password_hash = ? WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $new_password_hash, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $this->getById($id);
    }
}
