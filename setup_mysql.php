<?php
/**
 * MySQL Database Setup Script for Notes Marketplace
 * 
 * This script will:
 * 1. Create the database if it doesn't exist
 * 2. Create all necessary tables
 * 3. Insert sample data
 * 4. Set up indexes for better performance
 */

// Database configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'root',
    'password' => '',
    'database' => 'notes_marketplace'
];

echo "Starting MySQL database setup...\n";

// Connect to MySQL server (without database)
$conn = mysqli_connect($config['host'], $config['user'], $config['password'], '', $config['port']);

if (!$conn) {
    die("Failed to connect to MySQL: " . mysqli_connect_error() . "\n");
}

echo "Connected to MySQL server successfully.\n";

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (mysqli_query($conn, $sql)) {
    echo "Database '{$config['database']}' created or already exists.\n";
} else {
    die("Error creating database: " . mysqli_error($conn) . "\n");
}

// Select the database
if (!mysqli_select_db($conn, $config['database'])) {
    die("Error selecting database: " . mysqli_error($conn) . "\n");
}

echo "Database selected successfully.\n";

// Read and execute the complete schema
$schemaFile = __DIR__ . '/database/mysql_migrations/00_complete_schema.sql';
if (file_exists($schemaFile)) {
    $schema = file_get_contents($schemaFile);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^(--|#|\/\*)/', $statement)) {
            if (mysqli_query($conn, $statement)) {
                echo "Executed: " . substr($statement, 0, 50) . "...\n";
            } else {
                echo "Error executing statement: " . mysqli_error($conn) . "\n";
                echo "Statement: " . $statement . "\n";
            }
        }
    }
} else {
    die("Schema file not found: $schemaFile\n");
}

// Insert additional sample data
echo "Inserting sample data...\n";

// Sample notes
$sampleNotes = [
    [
        'title' => 'Introduction to Computer Science',
        'description' => 'Comprehensive notes covering the fundamentals of computer science including algorithms, data structures, and programming concepts.',
        'subject' => 'Computer Science',
        'price' => 29.99,
        'file_path' => '/uploads/sample_cs_notes.pdf',
        'file_size' => 2048576,
        'user_id' => 1
    ],
    [
        'title' => 'Advanced Mathematics for Engineering',
        'description' => 'Detailed mathematical concepts and formulas essential for engineering students.',
        'subject' => 'Mathematics',
        'price' => 24.99,
        'file_path' => '/uploads/sample_math_notes.pdf',
        'file_size' => 1536000,
        'user_id' => 1
    ],
    [
        'title' => 'Business Management Principles',
        'description' => 'Complete guide to modern business management practices and strategies.',
        'subject' => 'Business',
        'price' => 19.99,
        'file_path' => '/uploads/sample_business_notes.pdf',
        'file_size' => 1024000,
        'user_id' => 1
    ]
];

foreach ($sampleNotes as $note) {
    $sql = "INSERT INTO notes (title, description, subject, price, file_path, file_size, user_id, status, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', TRUE)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sssdssi', 
        $note['title'], 
        $note['description'], 
        $note['subject'], 
        $note['price'], 
        $note['file_path'], 
        $note['file_size'], 
        $note['user_id']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Sample coupons
$sampleCoupons = [
    [
        'code' => 'WELCOME10',
        'discount_percent' => 10,
        'max_uses' => 100,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
    ],
    [
        'code' => 'STUDENT20',
        'discount_percent' => 20,
        'max_uses' => 50,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+60 days'))
    ]
];

foreach ($sampleCoupons as $coupon) {
    $sql = "INSERT INTO coupons (code, discount_percent, max_uses, expires_at) 
            VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'siis', 
        $coupon['code'], 
        $coupon['discount_percent'], 
        $coupon['max_uses'], 
        $coupon['expires_at']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

echo "Sample data inserted successfully.\n";

// Verify setup
echo "Verifying setup...\n";

$tables = ['users', 'notes', 'orders', 'order_items', 'cart_items', 'reviews', 'coupons', 'refresh_tokens', 'contacts', 'admins'];

foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' missing\n";
    }
}

// Test connection with the new configuration
echo "\nTesting connection with new configuration...\n";
$testConn = mysqli_connect($config['host'], $config['user'], $config['password'], $config['database'], $config['port']);

if ($testConn) {
    echo "✓ Database connection test successful\n";
    mysqli_close($testConn);
} else {
    echo "✗ Database connection test failed: " . mysqli_connect_error() . "\n";
}

mysqli_close($conn);

echo "\nMySQL database setup completed!\n";
echo "You can now update your config.development.php file with:\n";
echo "    'host' => 'localhost',\n";
echo "    'port' => 3306,\n";
echo "    'database' => 'notes_marketplace',\n";
echo "    'user' => 'root',\n";
echo "    'password' => '',\n";
echo "\nDefault admin credentials:\n";
echo "Username: admin\n";
echo "Password: password123\n";
echo "\nDefault user credentials:\n";
echo "Email: user@example.com\n";
echo "Password: password123\n"; 