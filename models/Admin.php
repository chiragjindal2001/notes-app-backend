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
        $result = pg_query_params($this->conn, 'SELECT id, username, password_hash, role FROM admins WHERE username = $1', [$username]);
        return pg_fetch_assoc($result);
    }

    // Optionally: create, update, delete admin users, list admins, etc.
}
