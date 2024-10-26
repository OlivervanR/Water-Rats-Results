<?php
ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '../../session'));
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user'])) {
    // If not logged in, redirect to main page
    header('Location: index.php');
    exit();
}

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
        header("Location: add-comp.php");
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

        <button type="submit" name="submit">Add</button>
    </form>

    <h1>List of Competitors</h1>
    <table>
        <tr>
            <th>Competitor Name</th>
            <th>Sail Number</th>
            <th>Actions</th>
        <?php
        $query = "SELECT * FROM `Competitors`";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $competitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($competitors as $comp) {
            $comp_id = htmlspecialchars($comp['Comp_Id']);
            $name = htmlspecialchars($comp['Name']);
            $number = htmlspecialchars($comp['Number']);
            ?>
            <tr>
                <td><?=$name?></td> 
                <td><?=$number?></td>
                <td>
                <div id="right-links">
                    <a href="edit-comp.php?guid=<?= $comp_id ?>"><img src="images/edit.svg" alt="Edit"></a>
                    <a id="delete-comp" href="delete-comp.php?guid=<?= $comp_id ?>" onclick="confirmDeletion(event, this.href)">
                        <img src="images/trash.svg" alt="Delete">
                    </a>
                </div>
                </td>
            </tr>
        <?php } ?>
    </table>

    <script>
        function confirmDeletion(event, url) {
            // Display a confirmation dialog
            const userConfirmed = confirm("Are you sure you want to delete this Client?");
            // If the user did not confirm, prevent the navigation
            if (!userConfirmed) {
                event.preventDefault();
            } else {
                // If the user confirmed, redirect to the delete URL
                window.location.href = url;
            }
        }
    </script>
</body>
</html>

