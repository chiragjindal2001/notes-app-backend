<?php
class Coupon
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function validate($code, $order_amount = null)
    {
        $sql = 'SELECT * FROM coupons WHERE code = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', strtoupper($code));
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $coupon = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$coupon) return null;
        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) return null;
        if ($coupon['max_uses'] && $coupon['used_count'] >= $coupon['max_uses']) return null;
        if ($order_amount !== null && isset($coupon['min_amount']) && $coupon['min_amount'] && $order_amount < $coupon['min_amount']) return null;
        return $coupon;
    }

    public function create($data)
    {
        $sql = 'INSERT INTO coupons (code, discount_percent, max_uses, expires_at) VALUES (?, ?, ?, ?)';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'siis', 
            strtoupper($data['code']),
            $data['discount_percent'],
            $data['max_uses'],
            $data['expires_at']
        );
        mysqli_stmt_execute($stmt);
        $insertId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        
        return $this->getById($insertId);
    }

    public function getById($id)
    {
        $sql = 'SELECT * FROM coupons WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    public function list()
    {
        $sql = 'SELECT * FROM coupons ORDER BY expires_at DESC';
        $result = mysqli_query($this->conn, $sql);
        $coupons = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $coupons[] = $row;
        }
        return $coupons;
    }
}
