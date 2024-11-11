<?php 
ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '../../session'));
session_start();

// Include necessary libraries and functions
require "../../includes/water-rats-db.php";
include "../../includes/header.php";

$guid = $_GET['guid'];

$query = "SELECT * FROM `Races` WHERE `Day_Id` = ? ORDER BY `Race_Number` DESC LIMIT 1";
$stmt = $pdo->prepare($query);
$stmt->execute([$guid]);
$race = $stmt->fetch(PDO::FETCH_ASSOC);

if ($race) {
    $query = "DELETE FROM `Races` WHERE `Race_Id` = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$race['Race_Id']]);

    $query = "DELETE FROM `Race Results` WHERE `Race_Id` = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$race['Race_Id']]);

    $query = "UPDATE `Days` SET `Num_Races` = `Num_Races` - 1 WHERE `Day_Id` = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$guid]);
}

header("Location:index.php");
exit();