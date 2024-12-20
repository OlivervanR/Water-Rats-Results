<?php 
ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '../../session'));
session_start();

// Include necessary libraries and functions
require "../../includes/water-rats-db.php";
include "../../includes/header.php";

$guid = $_GET['guid'];

$query = "DELETE FROM `Days` WHERE `Day_Id` = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$guid]);

$query = "DELETE FROM `Races` WHERE `Day_Id` = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$guid]);

header("Location:index.php");
exit();