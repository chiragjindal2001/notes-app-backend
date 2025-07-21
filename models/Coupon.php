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
        $result = pg_query_params($this->conn, 'SELECT * FROM coupons WHERE code = $1', [strtoupper($code)]);
        $coupon = pg_fetch_assoc($result);
        if (!$coupon) return null;
        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) return null;
        if ($coupon['max_uses'] && $coupon['used_count'] >= $coupon['max_uses']) return null;
        if ($order_amount !== null && $coupon['min_amount'] && $order_amount < $coupon['min_amount']) return null;
        return $coupon;
    }

    public function create($data)
    {
        $sql = 'INSERT INTO coupons (code, type, value, min_amount, max_uses, expires_at) VALUES (:code, :type, :value, :min_amount, :max_uses, :expires_at) RETURNING *';
        $stmt = pg_query_params($sql);
        $stmt->execute([
            ':code' => strtoupper($data['code']),
            ':type' => $data['type'],
            ':value' => $data['value'],
            ':min_amount' => $data['min_amount'],
            ':max_uses' => $data['max_uses'],
            ':expires_at' => $data['expires_at']
        ]);
        return $stmt->fetch();
    }

    public function list()
    {
        $stmt = pg_query('SELECT * FROM coupons ORDER BY expires_at DESC');
        return $stmt->fetchAll();
    }
}
