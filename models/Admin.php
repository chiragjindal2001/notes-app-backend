<?php
class Admin
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getByUsername($username)
    {
        $sql = 'SELECT id, username, password_hash FROM admins WHERE username = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    // Optionally: create, update, delete admin users, list admins, etc.
}
