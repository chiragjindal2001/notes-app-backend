<?php
class Dashboard
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getStats()
    {
        // Total revenue
        $sql = "SELECT COALESCE(SUM(total_amount),0) as total FROM orders WHERE status = 'completed'";
        $result = mysqli_query($this->conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $total_revenue = $row['total'];

        // Total notes
        $sql = 'SELECT COUNT(*) as total FROM notes';
        $result = mysqli_query($this->conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $total_notes = $row['total'];

        // Total downloads
        $sql = 'SELECT COALESCE(SUM(downloads),0) as total FROM notes';
        $result = mysqli_query($this->conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $total_downloads = $row['total'];

        $active_users = 1234; // Placeholder, implement logic if needed

        // Recent orders
        $sql = "SELECT COUNT(*) as total FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $result = mysqli_query($this->conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $recent_orders = $row['total'];

        // Popular subjects
        $sql = "SELECT subject, COUNT(*) as note_count, COALESCE(SUM(oi.price),0) as revenue 
                FROM notes n 
                LEFT JOIN order_items oi ON n.id = oi.note_id 
                LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
                GROUP BY subject 
                ORDER BY note_count DESC 
                LIMIT 5";
        $result = mysqli_query($this->conn, $sql);
        $popular_subjects = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $popular_subjects[] = $row;
        }

        // Monthly revenue
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(total_amount),0) as revenue 
                FROM orders 
                WHERE status = 'completed' 
                GROUP BY month 
                ORDER BY month DESC 
                LIMIT 6";
        $result = mysqli_query($this->conn, $sql);
        $monthly_revenue = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $monthly_revenue[] = $row;
        }

        return [
            'total_revenue' => (float)$total_revenue,
            'total_notes' => (int)$total_notes,
            'total_downloads' => (int)$total_downloads,
            'active_users' => (int)$active_users,
            'recent_orders' => (int)$recent_orders,
            'popular_subjects' => $popular_subjects,
            'monthly_revenue' => $monthly_revenue
        ];
    }
}
