<?php
require __DIR__ . '/storage.php';
require __DIR__ . '/workouts.php';

requireAuth();

$user = currentUser();
$myWorkouts = loadUserWorkouts((string) $user['email']);

$period = isset($_GET['period']) ? (int) $_GET['period'] : 0;
if (!in_array($period, [7, 30, 90, 0], true)) {
    $period = 0;
}

$cutoff = $period > 0 ? date('Y-m-d', strtotime('-' . $period . ' days')) : null;

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="fitlog-report-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

// BOM, чтобы Excel корректно открыл UTF-8.
echo "\xEF\xBB\xBF";

$csvField = function ($value) {
    $value = (string) $value;
    if (strpbrk($value, ";\n\r\"") !== false) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
};

echo $csvField('Дата') . ';'
    . $csvField('Упражнение') . ';'
    . $csvField('Подход') . ';'
    . $csvField('Вес, кг') . ';'
    . $csvField('Повторы') . "\r\n";

// Отчёт в хронологическом порядке (от старых к новым).
foreach (array_reverse($myWorkouts) as $workout) {
    $date = (string) ($workout['date'] ?? '');

    if ($cutoff !== null && $date !== '' && $date < $cutoff) {
        continue;
    }

    foreach (($workout['exercises'] ?? []) as $exercise) {
        $name = (string) ($exercise['name'] ?? '');
        $setNumber = 0;

        foreach (($exercise['sets'] ?? []) as $set) {
            $setNumber++;
            $weight = (float) ($set['weight'] ?? 0);
            $reps = (int) ($set['reps'] ?? 0);
            $weightText = str_replace('.', ',', (string) $weight);

            echo $csvField($date) . ';'
                . $csvField($name) . ';'
                . $setNumber . ';'
                . $weightText . ';'
                . $reps . "\r\n";
        }
    }
}

exit;