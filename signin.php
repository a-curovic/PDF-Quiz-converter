<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <link rel="stylesheet" href="style2.css"/>
</head>
<body>
    <div class="fcontainer">
        <h1 class="form-title">Sign In</h1>
    <form method="post" action="login.php">
      <div class="input-group">
        <i class="fas fa-envelope"></i>
        <input type="email" name="email" placeholder="Email" required />
      </div>
      <div class="input-group">
        <i class="fas fa-lock"></i>
        <input type="password" name="password" placeholder="Password" required />
      </div>
      <button type="submit" name="signIn" class="btn">Sign In</button>
    </form>
    <p class="or">— or —</p>
    <p>Don’t have an account? <a href="index.php">Sign Up</a></p>
   </div>
  
</body>
</html>
