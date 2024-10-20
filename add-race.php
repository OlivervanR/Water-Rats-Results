<?php
$errors = array();

// Include necessary libraries and functions
require "../../includes/water-rats-db.php";
include "../../includes/header.php";

// Get day id
$day_id = $_GET['guid'];

// Get the day from the guid
$query = "SELECT * FROM `Days` WHERE `Day_Id` = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$day_id]);
$day = $stmt->fetch(PDO::FETCH_ASSOC);

$num_comp = $day['Num_Comp'];

// Get all the races from the day
$query = "SELECT * FROM `Races` WHERE `Day_Id` = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$day_id]);
$races = $stmt->fetchAll(PDO::FETCH_ASSOC); 

$race_num = count($races) + 1;

// Add race to database
if (isset($_POST['submit'])) {
    // Add the race to the day
    $query = "INSERT INTO `Races` (`Day_Id`, `Race_Number`) VALUES (?, ?)";
    $stmt_insert = $pdo->prepare($query);
    $stmt_insert->execute([$day_id, $race_num]);
    $race_id = $pdo->lastInsertId();

    // Update the race count for the day
    $query = "UPDATE `Days` SET `Num_Races` = ? WHERE `Day_Id` = ?";
    $stmt_insert = $pdo->prepare($query);
    $stmt_insert->execute([$race_num, $day_id]);

    $position = 1;
    $included_competitors = [];

    // Add each race result
    foreach ($_POST['sail_num'] as $i => $sail_num) {
        if ($sail_num != '') {
            // Find the competitor
            $query = "SELECT * FROM `Competitors` WHERE `Number` = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$sail_num]);
            $competitor = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($competitor == null) {
                // Insert the new competitor into the Competitors table
                $query = "INSERT INTO `Competitors` (`Name`, `Number`) VALUES (?, ?)";
                $stmt_insert = $pdo->prepare($query);
                $stmt_insert->execute(["", $sail_num]);
                $competitor_id = $pdo->lastInsertId();

                // Add the competitor to the current race with the correct position
                $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`) VALUES (?, ?, ?)";
                $stmt_insert_race = $pdo->prepare($query);
                $stmt_insert_race->execute([$race_id, $position, $competitor_id]);

                // Set position for other races
                $query = "SELECT * FROM `Races` WHERE `Race_Id` != ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$race_id]);
                $other_races = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($other_races as $other_race) {
                    $dnc = $other_race['DNC'];
                    $other_race_id = $other_race['Race_Id'];

                    $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`) VALUES (?, ?, ?)";
                    $stmt_insert_race = $pdo->prepare($query);
                    $stmt_insert_race->execute([$other_race_id, $dnc, $competitor_id]);
                }

            } else {
                $competitor_id = $competitor['Comp_Id'];

                // Add the competitor to the current race with the correct position
                $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`) VALUES (?, ?, ?)";
                $stmt_insert = $pdo->prepare($query);
                $stmt_insert->execute([$race_id, $position, $competitor_id]);
            }
            
            // Add the competitor to the list of included competitors
            $included_competitors[] = $competitor_id;

            // Increment position for the next competitor
            $position++;
        }
    }

    // Update the DNC
    $query = "UPDATE `Races` SET `DNC` = ? WHERE `Race_Id` = ?";
    $stmt_insert = $pdo->prepare($query);
    $stmt_insert->execute([$position, $race_id]);

    // Get all competitors
    $query = "SELECT `Comp_Id` FROM `Competitors`";
    $stmt = $pdo->query($query);
    $all_competitors = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Calculate the maximum position
    $max_position = $position; // Since $position was incremented after the last competitor

    // Find competitors not included in the form and assign them the maximum position
    foreach ($all_competitors as $comp_id) {
        if (!in_array($comp_id, $included_competitors)) {
            $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`) VALUES (?, ?, ?)";
            $stmt_insert = $pdo->prepare($query);
            $stmt_insert->execute([$race_id, $max_position, $comp_id]);
        }
    }

    // Redirect after processing
    header("Location: add-day.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="main1.css">
    <title>Add Race</title>
</head>
<body>
    <?php include 'nav.php'; ?> 

    <h1>Add Race</h1>
    <p>Please enter the last 4 digits of the competitors' sail numbers</p>
    <form method="post">
        <div id="sail-rows">
            <?php foreach (range(1, $num_comp) as $i) { ?>
                <div class="row">
                    <label for="sail_num<?=$i?>"><?=$i?></label>
                    <input type="text" name="sail_num[]"?>
                </div>
            <?php } ?>
        </div>
        <div>
            <button type="button" id="add-row">Add Row</button>
            <button type="button" id="delete-row">Delete Row</button>
        </div>
        <button type="submit" name="submit">Add Race</button>
    </form>

    <script>
        document.getElementById('add-row').addEventListener('click', function() {
            const sailRows = document.getElementById('sail-rows');
            const rowCount = sailRows.children.length + 1;
            const newRow = document.createElement('div');
            newRow.className = 'row';
            newRow.innerHTML = `
                <label for="sail_num${rowCount}">${rowCount}</label>
                <input type="text" name="sail_num[]" />
            `;
            sailRows.appendChild(newRow);
        });

        document.getElementById('delete-row').addEventListener('click', function() {
            const sailRows = document.getElementById('sail-rows');
            if (sailRows.children.length > 0) {
                sailRows.removeChild(sailRows.lastElementChild);
            }
        });
    </script>
</body>
</html>

