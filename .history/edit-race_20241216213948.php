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
$og_abb = [];

// gather the original race results
foreach ($race_results as $race_result) {
    $query = "SELECT `Number` FROM `Competitors` WHERE `Comp_Id` = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$race_result['Comp_Id']]);
    $competitor_number = $stmt->fetchColumn();

    if ($race_result['Position'] < $dnc) {
        $og_rr[] = [
            'id' => $race_result['Comp_Id'],
            'number' => $competitor_number,
        ];
    }
    else {
        $og_abb[] = [
            'id' => $race_result['Comp_Id'],
            'number' => $competitor_number,
            'notation' => $race_result['Notation']
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
        $other_competitors = [];

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

                // Logic based on the dropdown value for each competitor
                if ($_POST['status'][$i] != "0") {
                    $status = $_POST['status'][$i];
                    
                    $other_competitors[] = [
                        'id' => ($competitor == null) ? null : $competitor['Comp_Id'],
                        'sail_num' => $sail_num,
                        'notation' => $status
                    ];
                }
                else {
                    if ($sail_num != ($og_rr[$position-1]['number'] ?? null)) {
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

            // Add competitors with abbreviations
            foreach ($other_competitors as $competitor) {
                print("entered for loop");
                var_dump($competitor);
                $comp_id = $competitor['id'];
                if ($comp_id == null) {
                    addComp($pdo, $competitor['sail_num'], $comp_id);
                }

                // Add the competitor to the current race with the DNC position
                $query = "INSERT INTO `Race Results` (`Race_Id`, `Position`, `Comp_Id`, `Notation`) VALUES (?, ?, ?, ?)";
                $stmt_insert = $pdo->prepare($query);
                $stmt_insert->execute([$race_id, $position, $comp_id, $competitor['notation']]);

            }
        }

        // Testing purposes
        //var_dump($ranked_competitors);

        // Delete the old results
        $og_rr_count = count($og_rr);
        $new_rr_count = count($ranked_competitors);
        if ($og_rr_count > $new_rr_count) {
            for ($i = $new_rr_count; $i < $og_rr_count; $i++) {
                $query = "DELETE FROM `Race Results` WHERE `Race_Id` = ? AND `Position` = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$race_id, $i+1]);
            }
        }

        $included_competitors = $ranked_competitors;

        // Update the DNC value
        $query = "UPDATE `Races` SET `DNC` = ? WHERE `Race_Id` = ?";
        $stmt_insert = $pdo->prepare($query);
        $stmt_insert->execute([$position, $race_id]);
        
        if (count($included_competitors) > $day['Num_Comp']) {
            $query = "UPDATE `Days` SET `Num_Comp` = ? WHERE `Day_Id` = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([count($included_competitors), $day_id]);

            $num_comp = count($included_competitors);
        }

        $num_comp = $num_comp + count($other_competitors);

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
                        $sail_num = $_POST['sail_num'][$i] ?? $og_rr[$i]['number'] ?? $og_abb[$i]['number'] ?? '';

                        $status_value = $_POST['status'][$i] ?? ($i <= count($og_rr) ? '0' : $og_abb[$i - count($og_rr)]['number'] ?? '0');

                        // Generate the input field for each sail number
                    ?>
                        <div class="row">
                            <label for="sail_num<?=$i?>"><?=$i + 1?></label> <!-- Display the correct race number -->
                            <input type="number" max="9999" name="sail_num[]" value="<?= htmlspecialchars($sail_num) ?>">
                            <select id="status<?=$i?>" name="status[]">
                                <option value="0" <?= ($status_value == '0') ? 'selected' : '' ?>></option>
                                <option value="OCS" <?= ($status_value == 'OCS') ? 'selected' : '' ?>>OCS</option>
                                <option value="DNS" <?= ($status_value == 'DNS') ? 'selected' : '' ?>>DNS</option>
                                <option value="DNF" <?= ($status_value == 'DNF') ? 'selected' : '' ?>>DNF</option>
                                <option value="DSQ" <?= ($status_value == 'DSQ') ? 'selected' : '' ?>>DSQ</option>
                                <option value="BFD" <?= ($status_value == 'BFD') ? 'selected' : '' ?>>BFD</option>
                            </select>
                        </div>
                    <?php 
                    }
                    ?>
                </div>
        
        <div>
            <button type="button" id="add-row" class="button">Add Row</button>
            <button type="button" id="delete-row" class="button">Delete Row</button>
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
                <label for="sail_num${rowCount}">${rowCount + 1}</label>
                <input type="text" name="sail_num[]" />
                <select id="status${rowCount}" name="status[]">
                    <option value="0" selected></option>
                    <option value="OCS">OCS</option>
                    <option value="DNS">DNS</option>
                    <option value="DNF">DNF</option>
                    <option value="DSQ">DSQ</option>
                    <option value="BFD">BFD</option>
                </select>
            `;
            sailRows.appendChild(newRow);
        });

        document.getElementById('delete-row').addEventListener('click', function() {
            const sailRows = document.getElementById('sail-rows');
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
