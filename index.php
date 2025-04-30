<?php
ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '../../session'));
session_start();

// Include necessary libraries and functions
require "../../includes/water-rats-db.php";
include "../../includes/header.php";

// Fetch all competitors, days, and race results in fewer queries
$competitorsQuery = "SELECT * FROM `Competitors`";
$stmt = $pdo->prepare($competitorsQuery);
$stmt->execute();
$competitors = $stmt->fetchAll(PDO::FETCH_ASSOC); 

// Get the selected year (default to current year if not set)
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Modify your queries to filter by year
$daysQuery = "SELECT * FROM `Days` WHERE `Year` = ? ORDER BY `Day_Number` ASC";
$stmt = $pdo->prepare($daysQuery);
$stmt->execute([$selectedYear]);
$days = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available years for the dropdown
$yearsQuery = "SELECT DISTINCT `Year` FROM `Days` ORDER BY `Year` DESC";
$stmt = $pdo->prepare($yearsQuery);
$stmt->execute();
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

// If no years found, add current year
if (empty($availableYears)) {
    $availableYears = [date('Y')];
}

$racesQuery = "SELECT * FROM `Races`";
$stmt = $pdo->prepare($racesQuery);
$stmt->execute();
$races = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Modify this query to include the Notation field
$raceResultsQuery = "
    SELECT rr.Comp_Id, rr.Race_Id, rr.Position, rr.Notation, r.Day_Id 
    FROM `Race Results` rr 
    JOIN `Races` r ON rr.Race_Id = r.Race_Id";
$stmt = $pdo->prepare($raceResultsQuery);
$stmt->execute();
$raceResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Then modify how you structure the results to include notations
$resultsPerCompetitor = [];
$notationsPerCompetitor = []; // Add this array
foreach ($raceResults as $result) {
    $resultsPerCompetitor[$result['Comp_Id']][$result['Day_Id']][$result['Race_Id']] = $result['Position'];
    // Store notations separately
    if (!empty($result['Notation'])) {
        $notationsPerCompetitor[$result['Comp_Id']][$result['Race_Id']] = $result['Notation'];
    }
}

// Structure the race results in an associative array for easy lookup
$resultsPerCompetitor = [];
foreach ($raceResults as $result) {
    $resultsPerCompetitor[$result['Comp_Id']][$result['Day_Id']][$result['Race_Id']] = $result['Position'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="main1.css">
    <title>Laser Results</title>
</head>
<body>
    <?php include 'nav.php'; ?> 
    
    <main>
    <div class="year-navigation" style="text-align: center;">
        <?php if (in_array($selectedYear - 1, $availableYears)) { ?>
            <a href="?year=<?= $selectedYear - 1 ?>" class="button">← <?= $selectedYear - 1 ?></a>
        <?php } ?>
        
        <span class="current-year"><?= $selectedYear ?></span>
        
        <?php if (in_array($selectedYear + 1, $availableYears)) { ?>
            <a href="?year=<?= $selectedYear + 1 ?>" class="button"><?= $selectedYear + 1 ?> →</a>
        <?php } ?>
    </div>
    <h1>Final Results <?= $selectedYear ?></h1>
    <p style="text-align: center;">For the Water Rats Laser club racing</p>

    <?php
    $competitorData = [];

    // Process each competitor's results
    foreach ($competitors as $comp) {
        $comp_id = htmlspecialchars($comp['Comp_Id']);
        $name = htmlspecialchars($comp['Name']);
        $number = htmlspecialchars($comp['Number']);
        
        $final_total = 0;
        $totals_per_day = [];
        $max_totals_per_day = [];
        $dropped_days = []; // To track dropped days

        foreach ($days as $day) {
            $day_num = htmlspecialchars($day['Day_Id']);
            $day_total = 0;
            
            $racesQuery = "SELECT * FROM `Races` WHERE `Day_Id` = ?";
            $stmt = $pdo->prepare($racesQuery);
            $stmt->execute([$day_num]);
            $races = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $max_day_total = 0;
            foreach ($races as $race) {
                $race_id = htmlspecialchars($race['Race_Id']);
                $race_dnc = htmlspecialchars($race['DNC']);
                $comp_result = isset($resultsPerCompetitor[$comp_id][$day_num][$race_id]) 
                ? $resultsPerCompetitor[$comp_id][$day_num][$race_id] 
                : null;

                if (isset($comp_result)) {
                    $day_total += $comp_result;
                }
                else {
                    $day_total += $race_dnc;
                }

                $max_day_total += $race_dnc;
            }
            $final_total += $day_total;
            $totals_per_day[$day_num] = $day_total;
            $max_totals_per_day[$day_num] = $max_day_total;
        }

        // Determine the number of drops based on the total number of days
        $num_drops = floor(count($totals_per_day) / 5);

        // Sort the daily totals in descending order and drop the highest ones
        arsort($totals_per_day);
        $drops_made = 0;

        foreach ($totals_per_day as $day_id => $points) {
            if ($drops_made < $num_drops) {
                $final_total -= $points; // Drop the points
                $dropped_days[$day_id] = true; // Mark this day as dropped
                $drops_made++;
            }
        }

        // Store competitor data for final display
        $competitorData[] = [
            'name' => $name,
            'number' => $number,
            'final_total' => $final_total,
            'totals_per_day' => $totals_per_day,
            'max_totals_per_day' => $max_totals_per_day,
            'dropped_days' => $dropped_days
        ];
    }

   // Sort competitors by their total points for this day (ascending order)
    usort($competitorData, function($a, $b) {
        // First, compare by final total points (ascending order)
        $totalComparison = $a['final_total'] <=> $b['final_total'];

        // If the totals are not equal, return the comparison result
        if ($totalComparison !== 0) {
            return $totalComparison;
        }

        // Get filtered positions for both competitors
        $positionsA = array_filter($a['totals_per_day'], 'is_numeric');
        $positionsB = array_filter($b['totals_per_day'], 'is_numeric');
        
        // Check if either array is empty before using min()
        if (empty($positionsA) && empty($positionsB)) {
            return 0; // Both have no positions, consider them equal
        } else if (empty($positionsA)) {
            return 1; // A has no positions, B comes first
        } else if (empty($positionsB)) {
            return -1; // B has no positions, A comes first
        }

        // If both have positions, compare by the lowest race position
        $minA = min($positionsA); // Get the lowest numeric race position for competitor A
        $minB = min($positionsB); // Get the lowest numeric race position for competitor B

        $minPositionComparison = $minA <=> $minB;

        // If the lowest race positions are not equal, return the comparison result
        if ($minPositionComparison !== 0) {
            return $minPositionComparison;
        }

        // If the lowest race positions are equal, compare by the count of the lowest position
        $countMinA = count(array_filter($a['totals_per_day'], fn($pos) => $pos === $minA));
        $countMinB = count(array_filter($b['totals_per_day'], fn($pos) => $pos === $minB));

        $countComparison = $countMinB <=> $countMinA;

        // If the counts are not equal, return the comparison result
        if ($countComparison !== 0) {
            return $countComparison;
        }

        // Sort positions for sequential comparison
        sort($positionsA);
        sort($positionsB);

        // Compare each position in order until a difference is found
        for ($i = 0; $i < min(count($positionsA), count($positionsB)); $i++) {
            $positionComparison = $positionsA[$i] <=> $positionsB[$i];
            if ($positionComparison !== 0) {
                return $positionComparison;
            }
        }

        // If all positions compared so far are the same, the competitor with fewer races goes first
        return count($positionsA) <=> count($positionsB);
    });

    ?>
    <div class="table-container">
        <table>
            <tr>
                <th>Rank</th>
                <th>Competitor</th>
                <th>Sail #</th>
                <?php foreach ($days as $day) { ?>
                    <th>Day <?=htmlspecialchars($day['Day_Id'])?></th>
                <?php } ?>
                <th>Total</th>
            </tr>

            <?php foreach ($competitorData as $index => $comp) { ?>
            <tr>
                <td><b><?=$index + 1?></b></td>
                <td><b><?=$comp['name']?></b></td>
                <td><b><?=$comp['number']?></b></td>
                <?php foreach ($days as $day) {
                    $day_id = htmlspecialchars($day['Day_Id']);
                    $day_total = $comp['totals_per_day'][$day_id] ?? 0;
                    $max_day_total = $comp['max_totals_per_day'][$day_id];
                ?>
                    <td>
                        <?php if ($day_total == $max_day_total) { 
                            $day_total = 'DNC ' . $day_total;
                        } ?>
                        <?php if (isset($comp['dropped_days'][$day_id])) { ?>
                            (<?=$day_total?>)
                        <?php } else { ?>
                            <?=$day_total?>
                        <?php } ?>
                    </td>
                <?php } ?>
                <td><b><?=$comp['final_total']?></b></td>
            </tr>
            <?php } ?>
        </table>
    </div>
    
    <?php if (isset($_SESSION['user'])) { ?>
        <div style="text-align: center; "><a href="add-day.php" class="button" style="font-size: 20px; padding: 10px 20px; display: inline-block; text-decoration: none; background-color: blue;">Add Day</a></div>
    <?php } ?>
    
    <div id="target-section">
        <?php 
        // Sort the days array by date in descending order
        usort($days, function($a, $b) {
            return strtotime($b['Date']) - strtotime($a['Date']);
        });
        
        foreach ($days as $day) {
            $day_num = htmlspecialchars($day['Day_Id']);
            $date = htmlspecialchars($day['Date']);
            
            if ($day['Num_Races'] > 0) {
                // Select all the races on that day
                $query = "SELECT * FROM `Races` WHERE `Day_Id` = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$day_num]);
                $races = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $competitorData = [];

                // Loop through competitors to calculate daily totals and race positions
                foreach ($competitors as $comp) { 
                    $comp_id = htmlspecialchars($comp['Comp_Id']);
                    $name = htmlspecialchars($comp['Name']);
                    $number = htmlspecialchars($comp['Number']);
                    
                    $total = 0;
                    $race_positions = []; // Array to hold positions for each race
                    $notations = [];
                    $dnc_total = 0;

                    foreach ($races as $race) {
                        $race_id = htmlspecialchars($race['Race_Id']);
                        $race_dnc = htmlspecialchars($race['DNC']);
                        $dnc_total += $race_dnc;
                    
                        $query = "SELECT * FROM `Race Results` WHERE `Race_Id` = ? AND `Comp_Id` = ?";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([$race_id, $comp_id]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                        if ($result && isset($result['Position'])) {
                            $position = (int)htmlspecialchars($result['Position']);
                            $race_positions[$race_id] = $position;  // Key race positions by Race_Id
                    
                            $notation = $result['Notation'];
                            $notations[$race_id] = $notation;    
                    
                            $total += $position;  // Sum positions to calculate the total
                        } else {
                            $race_positions[$race_id] = $race_dnc; // Use DNC if no position
                            
                            $notation = 'DNC';
                            $notations[$race_id] = $notation;
                    
                            $total += $race_dnc;
                        }
                    }
                    
                    // Store competitor data with positions for each race and total points
                    $competitorData[] = [
                        'name' => $name,
                        'number' => $number,
                        'notations' => $notations,  // Make sure this is included
                        'race_positions' => $race_positions,
                        'total' => $total,
                        'dnc' => $dnc_total
                    ];
                }

                // Sort competitors by their total points for this day (ascending order)
                usort($competitorData, function($a, $b) {
                    // First, compare by total points
                    $totalComparison = $a['total'] <=> $b['total'];
                    
                    // If the totals are not equal, return the comparison result
                    if ($totalComparison !== 0) {
                        return $totalComparison;
                    }
                    
                    // Get filtered positions for both competitors
                    $positionsA = array_filter($a['race_positions'], 'is_numeric');
                    $positionsB = array_filter($b['race_positions'], 'is_numeric');
                    
                    // Check if either array is empty before using min()
                    if (empty($positionsA) && empty($positionsB)) {
                        return 0; // Both have no positions, consider them equal
                    } else if (empty($positionsA)) {
                        return 1; // A has no positions, B comes first
                    } else if (empty($positionsB)) {
                        return -1; // B has no positions, A comes first
                    }
                    
                    // If both have positions, compare by the lowest race position
                    $minA = min($positionsA); // Get the lowest numeric race position for competitor A
                    $minB = min($positionsB); // Get the lowest numeric race position for competitor B
                    
                    $minPositionComparison = $minA <=> $minB;
                
                    // If the lowest race positions are not equal, return the comparison result
                    if ($minPositionComparison !== 0) {
                        return $minPositionComparison;
                    }
                
                    // If the lowest race positions are equal, compare by the count of the lowest position
                    $countMinA = count(array_filter($a['race_positions'], fn($pos) => $pos === $minA));
                    $countMinB = count(array_filter($b['race_positions'], fn($pos) => $pos === $minB));
                    
                    $countComparison = $countMinB <=> $countMinA;
                    
                    // If the counts are not equal, return the comparison result
                    if ($countComparison !== 0) {
                        return $countComparison;
                    }
                
                    // If the counts are equal, compare the next lowest positions sequentially
                    $positionsA = array_filter($a['race_positions'], 'is_numeric');
                    $positionsB = array_filter($b['race_positions'], 'is_numeric');
                    
                    sort($positionsA); // Sort positions for competitor A
                    sort($positionsB); // Sort positions for competitor B
                    
                    // Compare each position in order until a difference is found
                    for ($i = 0; $i < min(count($positionsA), count($positionsB)); $i++) {
                        $positionComparison = $positionsA[$i] <=> $positionsB[$i];
                        if ($positionComparison !== 0) {
                            return $positionComparison;
                        }
                    }
                    
                    // If all positions compared so far are the same, the competitor with fewer races goes first
                    return count($positionsA) <=> count($positionsB);
                });
                ?>
                <div class="race-day-container">
                    <h2>Day <?=htmlspecialchars($day['Day_Number'])?></h2>
                    <div class="date"><?=$date?></div>
                </div>
                <div class="table-container">
                    <table>
                        <?php if (isset($_SESSION['user'])) { ?>
                            <th></th>
                            <th></th>
                            <th></th>
                            <?php foreach ($races as $race) { ?>
                                <th><a href="edit-race.php?guid=<?= $race['Race_Id'] ?>"><img src="images/edit.svg" alt="Edit"></a></th>
                            <?php } ?>
                            <th>
                                <a id="add-race" class="button" href="add-race.php?guid=<?= $day_num ?>" style="display: block; margin-bottom: 5px;">
                                    Add Race
                                </a>
                                <a id="delete-race" class="button" href="delete-race.php?guid=<?= $day_num ?>" onclick="confirmDeletion(event, this.href)">
                                    Delete Last Race
                                </a>
                            </th>
                        <?php } ?>
                        <tr>
                            <th>Rank</th>
                            <th>Competitor</th>
                            <th>Sail #</th>
                            <?php foreach ($races as $race) { ?>
                                <th>Race <?=htmlspecialchars($race['Race_Number'])?></th>
                            <?php } ?>
                            <th>Total</th>
                        </tr>

                        <?php foreach ($competitorData as $index => $comp) {
                            // Check if the competitor has any actual race results (not just DNCs)
                            $hasRealResults = false;
                            foreach ($races as $race) {
                                $race_id = htmlspecialchars($race['Race_Id']);
                                if (isset($comp['race_positions'][$race_id]) && 
                                    $comp['race_positions'][$race_id] != $race['DNC']) {
                                    $hasRealResults = true;
                                    break;
                                }
                            }
                            
                            if ($hasRealResults) { // Only show competitors who actually raced
                            ?>
                            <tr>
                                <td><b><?=$index + 1?></b></td>
                                <td><b><?=$comp['name']?></b></td>
                                <td><b><?=$comp['number']?></b></td>
                                <?php foreach ($races as $race) { 
                                    $race_id = htmlspecialchars($race['Race_Id']); ?>
                                    <td>
                                        <?php if (empty($comp['notations'][$race_id])) { 
                                            echo $comp['race_positions'][$race_id]; // Output just the position if no notation
                                        } else { 
                                            echo $comp['notations'][$race_id] . " " . $comp['race_positions'][$race_id]; // Output notation and position
                                        } ?>
                                    </td>
                                <?php } ?>
                                <td><b><?=$comp['total']?></b></td> <!-- Display total points for the day -->
                            </tr>
                            <?php } ?>
                        <?php } ?>
                    </table>
                </div>
            <?php } 
            else { ?>
                <?php if (isset($_SESSION['user'])) { ?>
                    <div class="race-day-container">
                        <h2>Day <?=htmlspecialchars($day['Day_Number'])?></h2>
                        <div class="date"><?=$date?></div>
                        <a id="add-race" class="button" href="add-race.php?guid=<?= $day_num ?>">
                            Add Race
                        </a>
                        <a id="delete-day" class="button" href="delete-day.php?guid=<?= $day_num ?>" onclick="confirmDeletion(event, this.href)">
                            Delete Day
                        </a>
                    </div>
                <?php } ?>
            <?php } ?>
        <?php } ?>
    </div>

    <script>
        function confirmDeletion(event, url) {
            // Display a confirmation dialog
            const userConfirmed = confirm("Are you sure you want to delete?");
            // If the user did not confirm, prevent the navigation
            if (!userConfirmed) {
                event.preventDefault();
            } else {
                // If the user confirmed, redirect to the delete URL
                window.location.href = url;
            }
        }
    </script>
    <footer style="text-align: center;">
        <p>&copy; <span id="year"></span> Oliver van Rossem. All rights reserved.</p>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('year').textContent = new Date().getFullYear();
        });
    </script>
    </main>
</body>
</html>


