<?php
require_once __DIR__ . '/storage.php';

/**
 * Склонение русских существительных: 1 подход, 2 подхода, 5 подходов.
 */
function pluralRu(int $count, string $one, string $few, string $many): string
{
    $n10 = $count % 10;
    $n100 = $count % 100;

    if ($n10 === 1 && $n100 !== 11) {
        return $one;
    }

    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) {
        return $few;
    }

    return $many;
}

/**
 * Число в русском формате: 42,5 или 1 234.
 */
function formatNumber(float $value): string
{
    if (floor($value) === $value) {
        return number_format($value, 0, ',', ' ');
    }

    return number_format($value, 1, ',', ' ');
}

/**
 * Тренировки пользователя, новые сверху.
 */
function loadUserWorkouts(string $email): array
{
    $myWorkouts = [];

    foreach (readJson(WORKOUTS_FILE) as $workout) {
        if (($workout['user_email'] ?? '') === $email) {
            $myWorkouts[] = $workout;
        }
    }

    usort($myWorkouts, function ($a, $b) {
        $byDate = strtotime((string) ($b['date'] ?? '1970-01-01')) <=> strtotime((string) ($a['date'] ?? '1970-01-01'));

        if ($byDate !== 0) {
            return $byDate;
        }

        return strtotime((string) ($b['created_at'] ?? '1970-01-01')) <=> strtotime((string) ($a['created_at'] ?? '1970-01-01'));
    });

    return $myWorkouts;
}

function findWorkout(array $workouts, string $id): ?array
{
    foreach ($workouts as $workout) {
        if ((string) ($workout['id'] ?? '') === $id) {
            return $workout;
        }
    }

    return null;
}

/**
 * Итоги по одной тренировке: упражнения, подходы, тоннаж, максимальный вес.
 */
function workoutSummary(array $workout): array
{
    $exerciseCount = 0;
    $setCount = 0;
    $volume = 0.0;
    $maxWeight = 0.0;

    foreach (($workout['exercises'] ?? []) as $exercise) {
        $exerciseCount++;

        foreach (($exercise['sets'] ?? []) as $set) {
            $weight = (float) ($set['weight'] ?? 0);
            $reps = (int) ($set['reps'] ?? 0);

            $setCount++;
            $volume += $weight * $reps;
            $maxWeight = max($maxWeight, $weight);
        }
    }

    return [
        'exercises' => $exerciseCount,
        'sets' => $setCount,
        'volume' => $volume,
        'maxWeight' => $maxWeight,
    ];
}

/**
 * Общая статистика по всем тренировкам пользователя.
 */
function workoutStats(array $workouts): array
{
    $setCount = 0;
    $weights = [];

    foreach ($workouts as $workout) {
        foreach (($workout['exercises'] ?? []) as $exercise) {
            foreach (($exercise['sets'] ?? []) as $set) {
                $setCount++;
                $weights[] = (float) ($set['weight'] ?? 0);
            }
        }
    }

    return [
        'total' => count($workouts),
        'sets' => $setCount,
        'avgWeight' => $weights ? round(array_sum($weights) / count($weights), 1) : 0,
    ];
}

/**
 * Обработка POST-запросов сохранения и удаления тренировки.
 * Вызывается на страницах до вывода HTML.
 */
function handleWorkoutActions(string $email): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_workout') {
        $deleteId = (string) ($_POST['id'] ?? '');
        $updated = [];

        foreach (readJson(WORKOUTS_FILE) as $item) {
            if ((string) ($item['id'] ?? '') === $deleteId && ($item['user_email'] ?? '') === $email) {
                continue;
            }

            $updated[] = $item;
        }

        writeJson(WORKOUTS_FILE, $updated);
        setFlash('Тренировка удалена.', 'success');
        header('Location: history.php');
        exit;
    }

    if ($action !== 'save_workout') {
        return;
    }

    $workouts = readJson(WORKOUTS_FILE);
    $id = (string) ($_POST['id'] ?? '');
    $date = trim((string) ($_POST['date'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $exerciseNames = $_POST['exercise_name'] ?? [];
    $exerciseSets = $_POST['exercise_sets'] ?? [];
    $backUrl = $id !== '' ? 'dashboard.php?edit=' . rawurlencode($id) : 'dashboard.php';

    if ($date === '') {
        setFlash('Укажите дату тренировки.', 'error');
        header('Location: ' . $backUrl);
        exit;
    }

    $exercises = [];

    foreach ($exerciseNames as $exerciseIndex => $exerciseName) {
        $name = trim((string) $exerciseName);
        if ($name === '') {
            continue;
        }

        $sets = [];
        $rawSets = $exerciseSets[$exerciseIndex] ?? [];

        if (is_array($rawSets)) {
            foreach ($rawSets as $setData) {
                $weight = isset($setData['weight']) ? (float) $setData['weight'] : 0;
                $reps = isset($setData['reps']) ? (int) $setData['reps'] : 0;

                if ($weight === 0.0 && $reps === 0) {
                    continue;
                }

                $sets[] = ['weight' => $weight, 'reps' => $reps];
            }
        }

        if (empty($sets)) {
            continue;
        }

        $exercises[] = [
            'id' => uniqid('exercise_', true),
            'name' => $name,
            'sets' => $sets,
        ];
    }

    if (empty($exercises)) {
        setFlash('Добавьте хотя бы одно упражнение с подходами.', 'error');
        header('Location: ' . $backUrl);
        exit;
    }

    $createdAt = date('Y-m-d H:i:s');

    if ($id !== '') {
        foreach ($workouts as $item) {
            if ((string) ($item['id'] ?? '') === $id && ($item['user_email'] ?? '') === $email) {
                $createdAt = (string) ($item['created_at'] ?? $createdAt);
                break;
            }
        }
    }

    $entry = [
        'id' => $id !== '' ? $id : uniqid('workout_', true),
        'user_email' => $email,
        'date' => $date,
        'notes' => $notes,
        'exercises' => $exercises,
        'created_at' => $createdAt,
    ];

    $updated = [];
    $replaced = false;

    foreach ($workouts as $item) {
        if ($id !== '' && (string) ($item['id'] ?? '') === $id && ($item['user_email'] ?? '') === $email) {
            $updated[] = $entry;
            $replaced = true;
            continue;
        }

        $updated[] = $item;
    }

    if (!$replaced) {
        $updated[] = $entry;
    }

    writeJson(WORKOUTS_FILE, $updated);
    setFlash($id === '' ? 'Тренировка добавлена.' : 'Тренировка обновлена.', 'success');
    header('Location: history.php?highlight=' . rawurlencode($entry['id']));
    exit;
}
