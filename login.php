<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'connect.php';

if (isset($_POST['signIn'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // 1) Fetch user by email
    $stmt = $conn->prepare("SELECT id, password_hash, is_admin FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($userId, $pwHash,$isAdmin);
        $stmt->fetch();

        // 2) Verify password
        if (password_verify($password, $pwHash)) {
            $_SESSION['user_id'] = $userId;
	    $_SESSION['is_admin'] = (bool)$isAdmin;

	    if($isAdmin){ header("Location: admin.php");}
            else {  header("Location: homePage.php");}
            exit();
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "No user found with that email.";
    }
}
