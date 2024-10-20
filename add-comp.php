<?php
session_start();

// Create an empty array for the list of errors
$errors = array();

// Include necessary libraries and functions
require "../../includes/water-rats-db.php";
include "../../includes/header.php";

// Set the values from form
$name = $_POST['name'] ?? "";
$number = $_POST['number'] ?? "";

if (isset($_POST['submit'])) {
    if (strlen($number) === 0) {
        $errors['number'] = true;
    }

    if (count($errors) === 0) {
        // Insert the new competitor into the Competitors table
        $query = "INSERT INTO `Competitors` (`Name`, `Number`) VALUES (?, ?)";
        $stmt_insert = $pdo->prepare($query);
        $stmt_insert->execute([$name, $number]);
        $competitor_id = $pdo->lastInsertId();

        // Retrieve all previous races
        $query = "SELECT * FROM `Races`";
        $stmt = $pdo->query($query);
        $races = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each race, add the new competitor with the maximum position
        foreach ($races as $race) {
            $race_id = htmlspecialchars($race['Race_Id']);
            $dnc = htmlspecialchars($race['DNC']);

            // Insert the new competitor into the race results with the max position
            $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`) VALUES (?, ?, ?)";
            $stmt_insert_race = $pdo->prepare($query);
            $stmt_insert_race->execute([$race_id, $dnc, $competitor_id]);
        }

        // Redirect to the index page after processing
        header("Location: index.php");
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
    <title>Add Competitor</title>
</head>
<body>
    <?php include 'nav.php' ?> 

    <h1>Add Competitor</h1>
    <form id="form" method="post">
        <div>
            <label for="name">Name:</label>
            <input type="text" name="name"/>
        </div>
        <div>
            <label for="number">Sail #:</label>
            <input type="text" name="number"/>
            <span class="error <?= !isset($errors['number']) ? 'hidden' : '' ?>">Please enter a sail #.</span>
        </div>

        <button type="submit" name="submit">Add Competitor</button>
    </form>
</body>
</html>

