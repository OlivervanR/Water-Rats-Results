<?php
ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '../../session'));
session_start();

function addComp($pdo, $sail_num, &$competitor_id) {
    // Insert the new competitor into the Competitors table
    $query = "INSERT INTO `Competitors` (`Name`, `Number`) VALUES (?, ?)";
    $stmt_insert = $pdo->prepare($query);
    $stmt_insert->execute(["", $sail_num]);
    $competitor_id = $pdo->lastInsertId();
}

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

$race_id = $_GET['guid'];

// Get the race from the guid
$query = "SELECT * FROM `Races` WHERE `Race_Id` = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$race_id]);
$race = $stmt->fetch(PDO::FETCH_ASSOC);

$race_num = $race['Race_Number'];
$day_id = $race['Day_Id'];
$dnc = $race['DNC'];

// Get all the race results from the race 
$query = "SELECT * FROM `Race Results` WHERE `Race_Id` = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$race_id]);
$race_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$og_rr = [];
$og_ocs = [];
$position = 1;

// gather the original race results
foreach ($race_results as $race_result) {
    $query = "SELECT `Number` FROM `Competitors` WHERE `Comp_Id` = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$race_result['Comp_Id']]);
    $competitor_number = $stmt->fetchColumn();

    if ($race_result['Position'] < $dnc) {
        $og_rr[$position] = [
            'id' => $race_result['Comp_Id'],
            'number' => $competitor_number,
        ];
        $position++; 
    }

    if ($race_result['Notation'] == 'OCS') {
        $og_ocs[] = [
            'id' => $race_result['Comp_Id'],
            'number' => $competitor_number,
        ];
    }
}

// Testing purposes
//var_dump($og_rr);
//var_dump($og_ocs);

// Get the day from the race
$query = "SELECT * FROM `Days` WHERE `Day_Id` = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$day_id]);
$day = $stmt->fetch(PDO::FETCH_ASSOC);

$num_comp = $day['Num_Comp'];

if (isset($_POST['submit'])) {
    // Filter out empty values
    $sail_nums = array_filter($_POST['sail_num'], function ($value) {
        return $value !== ''; // Keep only non-empty values
    });

    // Count occurrences of each value
    $counts = array_count_values($sail_nums);

    // Find duplicates
    $duplicates = array_filter($counts, function ($count) {
        return $count > 1;
    });

    // Check for errors
    if (!empty($duplicates)) {
        $errors['duplicate'] = true;
    }
    else {
        $position = 1;
        $ranked_competitors = [];

        // Check to see if position needs to be updated
        foreach ($_POST['sail_num'] as $i => $sail_num) {
            if ($sail_num != '') {
                // Find the competitor
                $query = "SELECT * FROM `Competitors` WHERE `Number` = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$sail_num]);
                $competitor = $stmt->fetch(PDO::FETCH_ASSOC);

                $competitor_id = $competitor['Comp_Id'];    
                $competitor_number = $competitor['Number'];
                
                if ($sail_num != ($og_rr[$position]['number'] ?? null)) {
                    if ($competitor == null) {
                        addComp($pdo, $sail_num, $competitor_id);
                    } 
                    elseif (!in_array($competitor_id, array_column($og_rr, 'id'))) {
                        // Delete the old race result
                        $query = "DELETE FROM `Race Results` WHERE `Race_Id` = ? AND `Comp_Id` = ?";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([$race_id, $competitor_id]);
                    }

                    if (count($ranked_competitors) < count($og_rr)) {
                        // Update the result with a different competitor
                        $query = "UPDATE `Race Results` SET `Comp_Id` = ? WHERE `Race_Id` = ? AND `Position` = ?";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([$competitor_id, $race_id, $position]);
                    }
                    else {
                        // Add the new race result
                        $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`) VALUES (?, ?, ?)";
                        $stmt_insert = $pdo->prepare($query);
                        $stmt_insert->execute([$race_id, $position, $competitor_id]);
                    }
                }
                
                // Add the competitor to the list of included competitors
                $ranked_competitors[] = $competitor_id;

                // Increment position for the next competitor
                $position++;
            } 
        }
        
        $ocs_competitors = [];

        // Add each OCS resuls
        foreach ($_POST['ocs_num'] as $i => $sail_num) {
            if ($sail_num != '') {
                // Find the competitor
                $query = "SELECT * FROM `Competitors` WHERE `Number` = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$sail_num]);
                $competitor = $stmt->fetch(PDO::FETCH_ASSOC);

                $competitor_id = $competitor['Comp_Id'];

                if (!in_array($competitor_id, array_column($og_ocs, 'id'))) {
                    if ($competitor == null) {
                        addComp($pdo, $sail_num, $race_id, $competitor_id);
                    }
                    
                    if (in_array($competitor_id, $ranked_competitors)) {
                        // Error exit 
                    }
                    // Delete the old race result
                    $query = "DELETE FROM `Race Results` WHERE `Race_Id` = ? AND `Comp_Id` = ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$race_id, $competitor_id]);
                    
                    // Add the competitor to the current race with the correct position
                    $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`, `Notation`) VALUES (?, ?, ?, ?)";
                    $stmt_insert = $pdo->prepare($query);
                    $stmt_insert->execute([$race_id, $position, $competitor_id, "OCS"]);
                }

                // Add the competitor to the list of included competitors
                $ocs_competitors[] = $competitor_id;
            }
        }

        $included_competitors = $ranked_competitors + $ocs_competitors;

        // Update the DNC value
        $query = "UPDATE `Races` SET `DNC` = ? WHERE `Race_Id` = ?";
        $stmt_insert = $pdo->prepare($query);
        $stmt_insert->execute([$position, $race_id]);
        
        if (count($included_competitors) > $day['Num_Comp']) {
            $query = "UPDATE `Days` SET `Num_Comp` = ? WHERE `Day_Id` = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([count($included_competitors), $day_id]);
        }

        // Redirect after processing
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
    <title>Edit Race</title>
</head>
<body>
    <?php include 'nav.php'; ?> 

    <main>
    <h1>Edit Race</h1>
    <form id="race-form" method="post">
        <p>Please enter the last 4 digits of the competitors' sail numbers</p>
        <div id="sail-rows">
            <?php
            // Iterate over the competitors, either from user input ($_POST) or existing race results ($og_rr)
            foreach (range(0, $num_comp - 1) as $i) { // Use 0-based indexing for $og_rr
                // Check if user input exists for this row or fall back to race result data
                $sail_num = isset($_POST['sail_num'][$i]) ? $_POST['sail_num'][$i] : (isset($og_rr[$i+1]) ? $og_rr[$i+1]['number'] : '');

                // Generate the input field for each sail number
            ?>
                <div class="row">
                    <label for="sail_num<?=$i?>"><?=$i + 1?></label> <!-- Display the correct race number -->
                    <input type="number" max="9999" name="sail_num[]" value="<?= htmlspecialchars($sail_num) ?>">
                </div>
            <?php 
            }
            ?>
        </div>
        
        <div>
            <button type="button" id="add-row" class="button">Add Row</button>
            <button type="button" id="delete-row" class="button">Delete Row</button>
        </div>
        
        <p>If any boats were over early (OCS), enter them here</p>
        <div id="ocs">
            <div class="row">
                <input type="number" max="9999" name="ocs_num[]">
            </div>
        </div>
        
        <div>
            <button type="button" id="add-row-2" class="button">Add Row</button>
            <button type="button" id="delete-row-2" class="button">Delete Row</button>
        </div>

        <button type="submit" name="submit" class="button">Done</button>
    </form>
    </main>

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

        document.getElementById('add-row-2').addEventListener('click', function() {
            const sailRows = document.getElementById('ocs');
            const newRow = document.createElement('div');
            newRow.className = 'row';
            newRow.innerHTML = `
                <input type="text" name="ocs_num[]" />
            `;
            sailRows.appendChild(newRow);
        });

        document.getElementById('delete-row').addEventListener('click', function() {
            const sailRows = document.getElementById('sail-rows');
            if (sailRows.children.length > 0) {
                sailRows.removeChild(sailRows.lastElementChild);
            }
        });

        document.getElementById('delete-row-2').addEventListener('click', function() {
            const sailRows = document.getElementById('ocs');
            if (sailRows.children.length > 0) {
                sailRows.removeChild(sailRows.lastElementChild);
            }
        });

        // Select all input fields inside the form
        const inputs = document.querySelectorAll('#race-form input');

        // Add keydown event to each input field
        inputs.forEach((input, index) => {
            input.addEventListener('keydown', function(event) {
                // Check if the key is 'Enter'
                if (event.key === 'Enter') {
                    event.preventDefault();  // Prevent form submission

                    // Focus on the next input, if available
                    const nextInput = inputs[index + 1];
                    if (nextInput) {
                        nextInput.focus();
                    }
                }
            });
        });
    </script>
</body>
</html>
