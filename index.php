<?php
// Include necessary libraries and functions
require "./includes/library.php";
include "../header.php";

// Fetch all competitors, days, and race results in fewer queries
$competitorsQuery = "SELECT * FROM `Competitors`";
$stmt = $pdo->prepare($competitorsQuery);
$stmt->execute();
$competitors = $stmt->fetchAll(PDO::FETCH_ASSOC); 

$daysQuery = "SELECT * FROM `Days`";
$stmt = $pdo->prepare($daysQuery);
$stmt->execute();
$days = $stmt->fetchAll(PDO::FETCH_ASSOC);

$raceResultsQuery = "
    SELECT rr.Comp_Id, rr.Race_Id, rr.Position, r.Day_Id 
    FROM `Race Results` rr 
    JOIN `Races` r ON rr.Race_Id = r.Race_Id";
$stmt = $pdo->prepare($raceResultsQuery);
$stmt->execute();
$raceResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Structure the race results in an associative array for easy lookup
$resultsPerCompetitor = [];
foreach ($raceResults as $result) {
    $resultsPerCompetitor[$result['Comp_Id']][$result['Day_Id']][] = $result['Position'];
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
    <h1>Final Results</h1>
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
        $dropped_days = []; // To track dropped days

        foreach ($days as $day) {
            $day_num = htmlspecialchars($day['Day_Id']);
            
            // Calculate total for this day
            $day_total = 0;
            if (isset($resultsPerCompetitor[$comp_id][$day_num])) {
                foreach ($resultsPerCompetitor[$comp_id][$day_num] as $position) {
                    $day_total += (int)$position;
                }
            }
            $final_total += $day_total;
            $totals_per_day[$day_num] = $day_total;
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

        // If the totals are equal, compare by the lowest race position (ascending order)
        $minA = min(array_filter($a['totals_per_day'], 'is_numeric')); // Get the lowest numeric race position for competitor A
        $minB = min(array_filter($b['totals_per_day'], 'is_numeric')); // Get the lowest numeric race position for competitor B

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

        // If the counts are equal, compare the next lowest positions sequentially
        $positionsA = array_filter($a['totals_per_day'], 'is_numeric');
        $positionsB = array_filter($b['totals_per_day'], 'is_numeric');

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
            ?>
                <td>
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

    <?php 
    // Sort the days array by date in descending order
    usort($days, function($a, $b) {
        return strtotime($b['Date']) - strtotime($a['Date']);
    });
    
    foreach ($days as $day) {
        $day_num = htmlspecialchars($day['Day_Id']);
        $date = htmlspecialchars($day['Date']);

        // Select all the races on that day
        $query = "SELECT * FROM `Races` WHERE `Day_Id` = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$day_num]);
        $races = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $competitorData = [];
        $list_dnc = [];

        // Gather all the DNC's for each race using Race_Id as the key
        foreach ($races as $race) {
            $list_dnc[$race['Race_Id']] = $race['DNC'];
        }

        // Loop through competitors to calculate daily totals and race positions
        foreach ($competitors as $comp) { 
            $comp_id = htmlspecialchars($comp['Comp_Id']);
            $name = htmlspecialchars($comp['Name']);
            $number = htmlspecialchars($comp['Number']);
            
            $total = 0;
            $race_positions = []; // Array to hold positions for each race
            $dnc_total = 0;

            foreach ($races as $race) {
                $race_id = htmlspecialchars($race['Race_Id']);
                $race_dnc = htmlspecialchars($race['DNC']);

                $query = "SELECT `Position` FROM `Race Results` WHERE `Race_Id` = ? AND `Comp_Id` = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$race_id, $comp_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result && isset($result['Position'])) {
                    $position = (int)htmlspecialchars($result['Position']);
                    $race_positions[$race_id] = $position;  // Key race positions by Race_Id
                    $dnc_total += $race_dnc;
                    $total += $position;  // Sum positions to calculate the total
                } else {
                    $race_positions[$race_id] = $list_dnc[$race_id]; // Use DNC if no position
                }
            }

            // Store competitor data with positions for each race and total points
            $competitorData[] = [
                'name' => $name,
                'number' => $number,
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
            
            // If the totals are equal, compare by the lowest race position
            $minA = min(array_filter($a['race_positions'], 'is_numeric')); // Get the lowest numeric race position for competitor A
            $minB = min(array_filter($b['race_positions'], 'is_numeric')); // Get the lowest numeric race position for competitor B
            
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
            <h2>Day <?=$day_num?></h2>
            <div class="date"><?=$date?></div>
        </div>
        <table>
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
                if ($comp['total'] != $comp['dnc']) { // Filter out competitors who only had DNCs
                ?>
                <tr>
                    <td><b><?=$index + 1?></b></td>
                    <td><b><?=$comp['name']?></b></td>
                    <td><b><?=$comp['number']?></b></td>
                    <?php foreach ($races as $race) { 
                        $race_id = htmlspecialchars($race['Race_Id']); ?>
                        <td>
                            <?php if ($comp['race_positions'][$race_id] != $list_dnc[$race_id]) { 
                                echo $comp['race_positions'][$race_id]; // Output the position if not DNC
                            } else { ?>
                                DNC (<?=$comp['race_positions'][$race_id]?>)
                            <?php } ?>
                        </td>
                    <?php } ?>
                    <td><b><?=$comp['total']?></b></td> <!-- Display total points for the day -->
                </tr>
                <?php } ?>
            <?php } ?>
        </table>

    <?php } ?>

</body>
</html>


