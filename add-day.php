<?php
ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '../../session'));
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user'])) {
    // If not logged in, redirect to main page
    header('Location: index.php');
    exit();
}

$errors = array();

// Include necessary libraries and functions
require "../../includes/water-rats-db.php";
include "../../includes/header.php";

// Set the values for the form
$date = $_POST['date'] ?? "";
$num_comp = $_POST['num_comp'] ?? "";
$year = $_POST['year'] ?? date('Y');

// Select all the race days
$query = "SELECT * FROM `Days`";
$stmt = $pdo->prepare($query);
$stmt->execute();
$days = $stmt->fetchAll(PDO::FETCH_ASSOC); 

if (isset($_POST['submit'])) {
    // Validate user input
    if (strlen($date) === 0) {
        $errors['date'] = true;
    }
    if (strlen($num_comp) === 0) {
        $errors['num_comp'] = true;
    }
    
    if (count($errors) === 0) {
        $year = $_POST['year'] ?? date('Y');
        
        // Get the next Day_Number for this year
        $query = "SELECT MAX(Day_Number) as max_day FROM `Days` WHERE `Year` = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextDayNumber = ($result['max_day'] ?? 0) + 1;
        
        // Get the next available Day_Id
        $query = "SELECT MAX(Day_Id) as max_id FROM `Days`";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextDayId = ($result['max_id'] ?? 0) + 1;
        
        // Insert with explicit Day_Id
        $query = "INSERT INTO `Days` (`Day_Id`, `Date`, `Num_Comp`, `Num_Races`, `Year`, `Day_Number`) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert = $pdo->prepare($query);
        $stmt_insert->execute([$nextDayId, $date, $num_comp, 0, $year, $nextDayNumber]);
        
        // Redirect to index page after successful insertion
        header("Location: index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="main1.css">
    <title>Add Day</title>
</head>
<body>
    <?php include 'nav.php' ?> 

    <main>
    <h1>Add Day</h1>
    <form id="form" method="post">
        <div>
            <label for="date">Date:</label>
            <input type="date" name="date" value="<?=$date?>"/>
            <span class="error <?= !isset($errors['date']) ? 'hidden' : '' ?>">Please enter the date.</span>
        </div> 
        <div>
            <label for="num_comp">Number of Competitors: </label>
            <input type="number" name="num_comp" value="<?=$num_comp?>"/>
            <span class="error <?= !isset($errors['num_comp']) ? 'hidden' : '' ?>">Please enter the number of competitors.</span>
        </div>
        <div>
            <label for="year">Year:</label>
            <input type="number" id="year" name="year" value="<?= date('Y') ?>" min="2000" max="2100" required>
        </div>

        <button type="submit" name="submit" class="button">Add Day</button>
    </form>
    </main>
</body>
</html>