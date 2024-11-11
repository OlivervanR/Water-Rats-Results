<?php
ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '../../session'));
session_start();

function addComp($pdo, $sail_num, $race_id, &$competitor_id) {
    // Insert the new competitor into the Competitors table
    $query = "INSERT INTO `Competitors` (`Name`, `Number`) VALUES (?, ?)";
    $stmt_insert = $pdo->prepare($query);
    $stmt_insert->execute(["", $sail_num]);
    $competitor_id = $pdo->lastInsertId();

    // Set position for other races
    $query = "SELECT * FROM `Races` WHERE `Race_Id` != ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$race_id]);
    $other_races = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add DNC positions for all the other races
    foreach ($other_races as $other_race) {
        $dnc = $other_race['DNC'];
        $other_race_id = $other_race['Race_Id'];

        $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`, `Notation`) VALUES (?, ?, ?, ?)";
        $stmt_insert_race = $pdo->prepare($query);
        $stmt_insert_race->execute([$other_race_id, $dnc, $competitor_id, "DNC"]);
    }
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

var_dump($og_rr);
var_dump($og_ocs);

// Get the day from the race
$query = "SELECT * FROM `Days` WHERE `Day_Id` = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$day_id]);
$day = $stmt->fetch(PDO::FETCH_ASSOC);

$num_comp = $day['Num_Comp'];

if (isset($_POST['submit'])) {
    $position = 1;
    $included_competitors = [];

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
                    addComp($pdo, $sail_num, $race_id, $competitor_id);
                } 
                elseif (!in_array($competitor_id, array_column($og_rr, 'id'))) {
                    // Delete the old race result
                    $query = "DELETE FROM `Race Results` WHERE `Race_Id` = ? AND `Comp_Id` = ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$race_id, $competitor_id]);
                }

                if (count($included_competitors) < count($og_rr)) {
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
            $included_competitors[] = $competitor_id;

            // Increment position for the next competitor
            $position++;
        } 
    }

    if (count($included_competitors) != count($og_rr)) {
        // If the number of competitors is less than original, update their position to DNC
        if (count($included_competitors) < count($og_rr)){ 
            $dif = count($og_rr) - count($included_competitors);

            foreach (range(1, $dif) as $i) {
                $pos = $i + count($included_competitors);
                
                // Update the race results to show DNC
                $query = "UPDATE `Race Results` SET `Notation` = ? WHERE `Race_Id` = ? AND `Position` = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute(['DNC', $race_id, $pos]);
            }
        }
    }
    
    // Add each OCS resuls
    foreach ($_POST['ocs_num'] as $i => $sail_num) {
        if ($sail_num != '') {
            // Find the competitor
            $query = "SELECT * FROM `Competitors` WHERE `Number` = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$sail_num]);
            $competitor = $stmt->fetch(PDO::FETCH_ASSOC);

            $competitor_id = $competitor['Comp_Id'];

            if ($competitor == null) {
                addComp($pdo, $sail_num, $race_id, $competitor_id);
            }

            // Add the competitor to the current race with the correct position
            $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`, `Notation`) VALUES (?, ?, ?, ?)";
            $stmt_insert = $pdo->prepare($query);
            $stmt_insert->execute([$race_id, $position, $competitor_id, "OCS"]);

            // Add the competitor to the list of included competitors
            $included_competitors[] = $competitor_id;
        }
    }

    // Update the DNC value
    $query = "UPDATE `Races` SET `DNC` = ? WHERE `Race_Id` = ?";
    $stmt_insert = $pdo->prepare($query);
    $stmt_insert->execute([$position, $race_id]);

    // Get all competitors
    $query = "SELECT `Comp_Id` FROM `Competitors`";
    $stmt = $pdo->query($query);
    $all_competitors = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Find competitors not included in the form and assign them the maximum position
    foreach ($all_competitors as $comp_id) {
        if (!in_array($comp_id, $included_competitors)) {
            $query = "UPDATE `Race Results` SET `Position` = ? WHERE `Race_Id` = ? AND `Comp_Id` = ?";
            $stmt_insert = $pdo->prepare($query);
            $stmt_insert->execute([$position, $race_id, $comp_id]);
        }
    }
    
    if (count($included_competitors) > $day['Num_Comp']) {
        $query = "UPDATE `Days` SET `Num_Comp` = ? WHERE `Day_Id` = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([count($included_competitors), $day_id]);
    }

    // If an original competitor isn't included in the race anymore, give them the DNC position 
    if (!empty($og_rr) && $difs = array_diff(array_column($og_rr, 'Comp_Id'), $included_competitors)) {
        foreach ($difs as $dif) {
            $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`, `Notation`) VALUES (?, ?, ?, ?)";
            $stmt_insert = $pdo->prepare($query);
            $stmt_insert->execute([$race_id, $position, $dif, 'DNC']);
        }
    }

    // Redirect after processing
    header("Location: index.php");
    exit;
    
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

    <h1>Edit Race</h1>
    <form id="race-form" method="post">
        <p>Please enter the last 4 digits of the competitors' sail numbers</p>
        <div id="sail-rows">
            <?php 
            // Generate input fields, pre-filling values with existing data if available
            foreach (range(1, $num_comp) as $i) { 
                $sail_num = isset($og_rr[$i]) ? $og_rr[$i]['number'] : ""; // Get existing data or leave blank
            ?>
                <div class="row">
                    <label for="sail_num<?=$i?>"><?=$i?></label>
                    <input type="number" max="9999" name="sail_num[]" value="<?= htmlspecialchars($sail_num) ?>">
                </div>
            <?php } ?>
        </div>
        
        <div>
            <button type="button" id="add-row">Add Row</button>
            <button type="button" id="delete-row">Delete Row</button>
        </div>
        
        <p>If any boats were over early (OCS), enter them here</p>
        <div id="ocs">
            <div class="row">
                <input type="number" max="9999" name="ocs_num[]">
            </div>
        </div>
        
        <div>
            <button type="button" id="add-row-2">Add Row</button>
            <button type="button" id="delete-row-2">Delete Row</button>
        </div>

        <button type="submit" name="submit">Done</button>
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
