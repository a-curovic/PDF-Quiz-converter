<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}
include 'connect.php';  // your $conn :contentReference[oaicite:6]{index=6}&#8203;:contentReference[oaicite:7]{index=7}
?>
<?php

set_time_limit(300);

$quiz = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdfInput'])) {
    // 1. Validate & store upload
    $fileInfo   = pathinfo($_FILES['pdfInput']['name']);
    $ext        = strtolower($fileInfo['extension']);
    if ($ext !== 'pdf') {
        $errors[] = "Please upload a PDF file.";
    } else {
        $uploadsDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        $storedName = uniqid('pdf_', true) . '.pdf';
        $targetPath = $uploadsDir . $storedName;
        if (move_uploaded_file($_FILES['pdfInput']['tmp_name'], $targetPath)) {
            // 2. Call Python script
            $escaped = escapeshellarg($targetPath);
            // 1. Point to the Python interpreter on Windows—just "python" if it's on your PATH
            $python = 'C:\\Users\\AlenC\\OneDrive\\Skrivbord\\Learning\\MachineLearning\\WebPdf2Quiz\\venvQuiz\\Scripts\\python.exe';  
            // 2. Build the path to your script
            $script = __DIR__ . DIRECTORY_SEPARATOR . 'pdf2quiz.py';
            // 3. Quote everything properly
            $cmd = "\"{$python}\" \"{$script}\" " . escapeshellarg($targetPath);

            // 4. Run and capture both stdout & stderr
            exec($cmd . " 2>&1", $outLines, $returnVar);

            $output = implode("\n", $outLines);

            // 5. Dump for debugging
            file_put_contents(__DIR__ . "/debug.txt",
                "CMD: $cmd\n" .
                "RC: $returnVar\n" .
                "OUT:\n" . $output
            );

            // 3. Decode JSON output
            $data = json_decode(trim($output), true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['quiz'])) {
                $quiz = $data['quiz'];
                $stmt = $conn->prepare(
                    "INSERT INTO quizzes (user_id, title, questions) VALUES (?, ?, ?)"
                  );
                  $userId   = $_SESSION['user_id'];
                  $title    = $_FILES['pdfInput']['name'];      // or whatever you prefer
                  $jsonQs   = json_encode($quiz);
                  $stmt->bind_param("iss", $userId, $title, $jsonQs);
                  $stmt->execute();
            } else {
                $errors[] = "Quiz generation failed: " . ($data['error'] ?? 'Invalid JSON from Python.');
            }
        } else {
            $errors[] = "Failed to save uploaded file.";
        }
    }
}

$stmt = $conn->prepare(
    "SELECT id, title
       FROM quizzes
      WHERE user_id = ?
   ORDER BY created_at DESC"
  );
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $quizList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  
  $displayQuiz = [];
  $pageTitle   = ""; 
  // decide which quiz to display
  if (!empty($_GET['quiz_id'])) {
      $displayId = (int)$_GET['quiz_id'];
  } 
  else {
      // no quizzes yet
      $displayId = null;
  }
  
  // fetch that quiz’s data
  if ($displayId) {
      $stmt = $conn->prepare(
        "SELECT title, questions
           FROM quizzes
          WHERE id = ?
            AND user_id = ?"
      );
      $stmt->bind_param("ii", $displayId, $_SESSION['user_id']);
      $stmt->execute();
      if($row = $stmt->get_result()->fetch_assoc()){
        $displayQuiz = json_decode($row['questions'], true) ?: [];
        $pageTitle   = $row['title'];}
      
  }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>PDF to Quiz</title>
    <meta name="description" content="This page is to create a pdf to quiz converter">
    <link rel="stylesheet" href="style2.css">
</head>
<body>
    <h1>Welcome to the PDF2Quiz Converter</h1> 
    <a href="logout.php" class="signout-button">Sign Out</a>
    <section class="container">
    <section class="sidebar">
        <ul class="sidebar-list">
            <li>
            <form id="quizSelector" method="get">
                <label for="quiz_id">My Quizzes:</label>
                <select name="quiz_id" id="quiz_id"
                        onchange="document.getElementById('quizSelector').submit()">
                <option value="">— Select a Quiz —</option>
                <?php foreach ($quizList as $q): ?>
                    <option value="<?= $q['id'] ?>"
                    <?= (isset($displayId) && $displayId == $q['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($q['title']) ?>
                    </option>
                <?php endforeach; ?>
                </select>
            </form>
            </li>
        </ul>
        </section>
        <section class="rectangle">
        <form method="post" enctype="multipart/form-data">
        <label>Upload PDF:</label>
        <input type="file" name="pdfInput" accept="application/pdf">
        <button type="submit">Generate Quiz</button>
      </form>       
        <div class="quiz-box">
            <!-- Quiz results -->
            <main class="content">
            <?php if (!empty($displayQuiz)): ?>
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <?php foreach ($displayQuiz as $i => $qa): ?>
                <article class="question-block">
                    <h2>Q<?= $i+1 ?>:</h2>
                    <p><?= htmlspecialchars($qa['question']) ?></p>
                    <details><summary>Show Answer</summary>
                    <p><?= htmlspecialchars($qa['answer']) ?></p>
                    </details>
                </article>
                <?php endforeach; ?>
            <?php elseif (!empty($quizList)): ?>
                <p class="notice">Please select a quiz above to view it.</p>
            <?php endif; ?>
            </main>
        </div>
    </section>
     <!-- Error display -->
     <?php if ($errors): ?>
        <div class="errors">
          <?php foreach ($errors as $e): ?>
            <p><?= htmlspecialchars($e) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      
</body>
</html>