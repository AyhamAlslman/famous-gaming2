<?php
/**
 * Password Migration Script
 * This script updates all plain-text passwords to hashed versions
 * Run this ONCE after applying database_migration.sql
 */

require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Password Migration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .warning { color: orange; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Password Migration Tool</h1>
    <p>This script will hash all plain-text passwords in the admins table.</p>
";

// Check if migration already done
$check_query = "SELECT id, username, password FROM admins LIMIT 1";
$check_result = mysqli_query($conn, $check_query);

if ($check_result && mysqli_num_rows($check_result) > 0) {
    $sample = mysqli_fetch_assoc($check_result);

    // Check if password is already hashed (bcrypt starts with $2y$)
    if (substr($sample['password'], 0, 4) === '$2y$') {
        echo "<div class='warning'><strong>Warning:</strong> Passwords appear to already be hashed. Migration may have already been run.</div>";
        echo "<p>Sample password format: " . substr($sample['password'], 0, 20) . "...</p>";
        echo "<p>If you want to re-run migration, please ensure this is intentional.</p>";
    }
}

echo "<h2>Migration Progress:</h2>";

// Fetch all admins
$query = "SELECT id, username, password FROM admins";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo "<div class='error'>Database Error: " . mysqli_error($conn) . "</div>";
    exit;
}

$total_count = mysqli_num_rows($result);
$updated_count = 0;
$error_count = 0;

echo "<p>Found <strong>$total_count</strong> admin accounts to process.</p>";
echo "<pre>";

while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
    $username = $row['username'];
    $old_password = $row['password'];

    // Check if already hashed
    if (substr($old_password, 0, 4) === '$2y$') {
        echo "⏭️  Skipping '$username' - Already hashed\n";
        continue;
    }

    // Hash the password
    $hashed_password = password_hash($old_password, PASSWORD_DEFAULT);

    // Update the database using prepared statement
    $stmt = mysqli_prepare($conn, "UPDATE admins SET password = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $id);

    if (mysqli_stmt_execute($stmt)) {
        echo "✅ Updated '$username' - Old: '$old_password' → New: Hashed\n";
        $updated_count++;
    } else {
        echo "❌ Failed to update '$username' - Error: " . mysqli_stmt_error($stmt) . "\n";
        $error_count++;
    }

    mysqli_stmt_close($stmt);
}

echo "</pre>";

echo "<h2>Migration Summary:</h2>";
echo "<ul>";
echo "<li>Total Accounts: <strong>$total_count</strong></li>";
echo "<li>Successfully Updated: <strong style='color: green;'>$updated_count</strong></li>";
echo "<li>Errors: <strong style='color: red;'>$error_count</strong></li>";
echo "<li>Skipped (Already Hashed): <strong>" . ($total_count - $updated_count - $error_count) . "</strong></li>";
echo "</ul>";

if ($updated_count > 0) {
    echo "<div class='success'><strong>✅ Migration Completed Successfully!</strong></div>";
    echo "<p><strong>IMPORTANT:</strong> Please note the following default passwords for login:</p>";
    echo "<pre>";
    echo "Admin Account:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n\n";
    echo "Employee Accounts:\n";
    echo "  Username: employee1, employee2, employee3\n";
    echo "  Password: emp123\n";
    echo "</pre>";
    echo "<p><strong style='color: red;'>⚠️ SECURITY WARNING:</strong> Change these default passwords immediately after first login!</p>";
}

if ($error_count > 0) {
    echo "<div class='error'><strong>⚠️ Migration completed with errors!</strong> Please check the log above.</div>";
}

echo "<hr>";
echo "<p><a href='admin/login.php'>→ Go to Admin Login</a></p>";
echo "<p><strong>Note:</strong> You can safely delete this file after successful migration.</p>";

mysqli_close($conn);

echo "</body></html>";
?>
