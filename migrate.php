<?php
require __DIR__ . '/storage.php';

/**
 * Миграция: переносит данные из старых файлов data/*.json в БД.
 *
 * Запустить один раз на сервере:
 *   1) загрузить проект + (по желанию) свои файлы users.json / workouts.json;
 *   2) настроить config.php на MySQL;
 *   3) открыть http://site/migrate.php
 *
 * Повторный запуск со старой базы возможен с параметром ?force=1
 * (очистит и пересоздаст данные заново из JSON).
 *
 * После успешной миграции скрипт лучше удалить с сервера.
 */

$force = isset($_GET['force']);

ensureStorage();
dbPdo();

$existingUsers = count(dbUsers());

if ($existingUsers > 0 && !$force) {
    echo "В БД уже есть данные (пользователей: {$existingUsers}). "
        . 'Для повторного импорта из JSON откройте migrate.php?force=1';
    exit;
}

/**
 * Прямое чтение старого JSON-файла (минуя readJson — он уже читает БД).
 */
function readLegacyJsonFile(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $content = @file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

header('Content-Type: text/plain; charset=UTF-8');

$counts = [];

// 1. Пользователи.
$users = readLegacyJsonFile(USERS_FILE);
if ($users !== []) {
    dbSaveUsers($users);
    $counts['users'] = count($users);
}

// 2. Тренировки (email -> id пользователя определится уже из вставленных пользователей).
$workouts = readLegacyJsonFile(WORKOUTS_FILE);
if ($workouts !== []) {
    dbSaveWorkouts($workouts);
    $counts['workouts'] = count($workouts);
}

// 3. Журнал регистраций (антиспам).
$regLog = readLegacyJsonFile(REGISTRATION_LOG_FILE);
if ($regLog !== []) {
    dbSaveRegistrationLog($regLog);
    $counts['registration_log'] = count($regLog);
}

if ($counts === []) {
    echo "Не найдено JSON-файлов с данными. База пуста и готова к работе; "
        . 'зарегистрируйте пользователя.';
    exit;
}

$summary = [];
foreach ($counts as $key => $value) {
    $summary[] = "{$key}: {$value}";
}

echo 'Миграция завершена: ' . implode(', ', $summary) . ".\n";
echo 'Удалите migrate.php с сервера (или ограничьте к нему доступ).';