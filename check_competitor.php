<?php
header('Content-Type: application/json');

require "./includes/library.php";
require "../header.php";

$sail_num = $_GET['sail_num'] ?? '';

// Validate input
if (empty($sail_num)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    // Prepare and execute the query
    $query = "SELECT * FROM `Competitors` WHERE `Number` = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sail_num]);

    // Check if any rows are returned
    $exists = $stmt->rowCount() > 0;

    // Prepare the response
    $response = ['exists' => $exists];
} catch (Exception $e) {
    // Handle errors and return a default response
    $response = ['exists' => false];
}

echo json_encode($response);

?>





