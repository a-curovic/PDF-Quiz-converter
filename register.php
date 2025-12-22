<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'connect.php';   // brings in mysqli $conn

if (isset($_POST['signUp'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $pwHash   = password_hash($_POST['password'], PASSWORD_DEFAULT);

    //Check that the table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'users'");
    if ($checkTable === false || $checkTable->num_rows === 0) {
        die("? Table `users` not found in database `{$db}`. Check your schema.");
    }

    //Prepare the SELECT
    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    if (! $stmt) {
        die("? Prepare failed for [$sql]: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "Email already registered.";
    } else {
        //Prepare the INSERT
        $sql = "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (! $stmt) {
            die("? Prepare failed for [$sql]: " . $conn->error);
        }
        $stmt->bind_param("sss", $username, $email, $pwHash);

        if ($stmt->execute()) {
            header("Location: signin.php");
            exit();
        } else {
            die("? Execute failed: " . $stmt->error);
        }
    }
}

