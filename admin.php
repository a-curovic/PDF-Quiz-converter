<?php
session_start();
include 'connect.php';

//Ensure only admins can see this page
if (
  !isset($_SESSION['user_id']) ||
  empty($_SESSION['is_admin'])
) {
    header("Location: signin.php");
    exit();
}

//Handle delete requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delId = intval($_POST['delete_user_id']);
    // Because of ON DELETE CASCADE on quizzes.user_id, deleting the user alone will remove quizzes
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();
    $message = "User (ID {$delId}) and all their quizzes have been deleted.";
}

//Handle search
$search = "";
if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $like   = "%{$search}%";
    $stmt   = $conn->prepare(
      "SELECT id, username, email
       FROM users
       WHERE username LIKE ? OR email LIKE ?"
    );
    $stmt->bind_param("ss", $like, $like);
} else {
    $stmt = $conn->prepare(
      "SELECT id, username, email
       FROM users
       ORDER BY id DESC"
    );
}
$stmt->execute();
$res = $stmt->get_result();
$users = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="style2.css"/>
</head>
<body class="admin-page">
  <h1>Admin Dashboard</h1>
  <p><a href="logout.php" class="signout-button">Sign out</a></p>

  <?php if (!empty($message)): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <!-- Search form -->
  <form method="get" class="search">
    <input
      type="text"
      name="search"
      placeholder="Search by username or email"
      value="<?= htmlspecialchars($search) ?>"
    />
    <button type="submit">Search</button>
    <button type="button" onclick="window.location='admin.php'">Show All</button>
  </form>

  <!-- User list -->
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Username</th><th>Email</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($users) === 0): ?>
        <tr><td colspan="4">No users found.</td></tr>
      <?php else: ?>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <form
                method="post"
                style="display:inline"
                onsubmit="return confirm('Delete user <?= addslashes($u['username']) ?> and all their quizzes?');"
              >
                <input
                  type="hidden"
                  name="delete_user_id"
                  value="<?= $u['id'] ?>"
                />
                <button type="submit">🗑️ Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>

