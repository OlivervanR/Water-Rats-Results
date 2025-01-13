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

// get comp id
$comp_id = $_GET['guid'];

$query = "SELECT * FROM `Competitors` WHERE `Comp_Id` = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$comp_id]);
$competitor = $stmt->fetch(PDO::FETCH_ASSOC);

// get the name and sail number of competitor
$name = $_POST['name'] ?? htmlspecialchars($competitor['Name']);
$number = $_POST['number'] ?? htmlspecialchars($competitor['Number']);

if (isset($_POST['submit'])) {
    if (strlen($number) === 0) {
        $errors['number'] = true;
    }

    if (count($errors) === 0) {
        // Insert into database
        $query = "UPDATE `Competitors` SET `Name` = ?, `Number` = ? WHERE `Comp_Id` = ?";
        $stmt_insert = $pdo->prepare($query);
        $stmt_insert->execute([$name, $number, $comp_id]);
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
    <title>Edit Competitor</title>
</head>
<body>
    <h1>Edit Competitor</h1>
    <form id="form" method="post">
        <div>
            <label for="name">Name:</label>
            <input type="text" name="name" value="<?=$name?>"/>
        </div>
        <div>
            <label for="number">Sail #:</label>
            <input type="text" name="number" value="<?=$number?>"/>
            <span class="error <?= !isset($errors['number']) ? 'hidden' : '' ?>">Please enter a sail #.</span>
        </div>

        <button type="submit" name="submit">Update</button>
    </form>
</body>
</html>