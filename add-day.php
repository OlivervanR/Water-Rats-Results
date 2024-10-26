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

// Select all the race days
$query = "SELECT * FROM `Days`";
$stmt = $pdo->prepare($query);
$stmt->execute();
$days = $stmt->fetchAll(PDO::FETCH_ASSOC); 

$day_id = count($days) + 1;

if (isset($_POST['submit'])) {
    // Validate user input
    if (strlen($date) === 0) {
        $errors['date'] = true;
    }
    if (strlen($num_comp) === 0) {
        $errors['num_comp'] = true;
    }

    if (count($errors) === 0) {
        // Insert into database
        $query = "INSERT INTO `Days` (`Day_Id`, `Date`, `Num_Comp`) VALUES (?, ?, ?)";
        $stmt_insert = $pdo->prepare($query);
        $stmt_insert->execute([$day_id, $date, $num_comp]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
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

        <button type="submit" name="submit">Add Day</button>
    </form>

    <h2>All Days</h2>
    <div class="horizontal">
        <?php foreach ($days as $day) {
            $d_day_id = htmlspecialchars($day['Day_Id']);
            $d_date = htmlspecialchars($day['Date']);
            $d_num_comp = htmlspecialchars($day['Num_Comp']);
            $d_num_races = htmlspecialchars($day['Num_Races']);
            ?>
            <div class="item">
                <h3>Day <?=$d_day_id?></h3>
                <div><?=$d_date?></div>
                <div><?=$d_num_comp?> competitors</div>
                <?php if ($d_num_races == 1) : ?>
                    <div><?=$d_num_races?> race</div>
                <?php else :?>
                    <div><?=$d_num_races?> races</div>
                <?php endif; ?>
                <div>
                    <a href="add-race.php?guid=<?=$d_day_id?>">Add Race</a>
                </div>
            </div>
        <?php } ?>
    </div>
</body>
</html>