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
        $total_revenue = pg_query_params($this->conn, "SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status = 'completed'", array())->fetchColumn();
        $total_notes = pg_query_params($this->conn, 'SELECT COUNT(*) FROM notes', array())->fetchColumn();
        $total_downloads = pg_query_params($this->conn, 'SELECT COALESCE(SUM(downloads),0) FROM notes', array())->fetchColumn();
        $total_downloads = pg_query('SELECT COALESCE(SUM(downloads),0) FROM notes')->fetchColumn();
        $active_users = 1234; // Placeholder, implement logic if needed
        $recent_orders = pg_query("SELECT COUNT(*) FROM orders WHERE created_at >= NOW() - INTERVAL '7 days'")->fetchColumn();
        $popular_subjects = pg_query("SELECT subject, COUNT(*) as note_count, COALESCE(SUM(total_amount),0) as revenue FROM notes LEFT JOIN orders o ON notes.id = ANY((SELECT ARRAY_AGG(oi.note_id) FROM order_items oi WHERE oi.order_id = o.id)) GROUP BY subject ORDER BY note_count DESC LIMIT 5")->fetchAll();
        $monthly_revenue = pg_query("SELECT to_char(created_at, 'YYYY-MM') as month, COALESCE(SUM(total_amount),0) as revenue FROM orders WHERE status = 'completed' GROUP BY month ORDER BY month DESC LIMIT 6")->fetchAll();
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
