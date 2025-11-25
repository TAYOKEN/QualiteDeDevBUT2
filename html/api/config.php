<?php
// Simple DB config for local development.
// Update with your MySQL/MariaDB credentials. Place this folder under a PHP-enabled server root (e.g., XAMPP htdocs).

return [
    'host' => '127.0.0.1',
    'dbname' => 'finance',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
];

/* Example: If you use XAMPP on Windows, common credentials are:
   user: root
   pass: (empty)
   Import the provided `finance.sql` into your MySQL server first.
*/

?>
