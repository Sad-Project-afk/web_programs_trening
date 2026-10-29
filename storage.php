<?php
session_start();

const USERS_FILE = __DIR__ . '/data/users.json';
const WORKOUTS_FILE = __DIR__ . '/data/workouts.json';
const EXERCISES_FILE = __DIR__ . '/data/exercises.json';
const REGISTRATION_LOG_FILE = __DIR__ . '/data/registration_log.json';

/** Регистрационный порог: не больше этого числа попыток за окно с одного IP. */
const REGISTRATION_MAX_ATTEMPTS = 3;
/** Длина окна антиспама в секундах. */
const REGISTRATION_WINDOW = 120;

function ensureStorage(): void
{
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }

    foreach ([USERS_FILE, WORKOUTS_FILE, EXERCISES_FILE] as $path) {
        if (!file_exists($path)) {
            file_put_contents($path, "[]");
        }
    }
}

function readJson(string $path): array
{
    ensureStorage();

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

function writeJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($path, $json);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireAuth(): void
{
    if (!currentUser()) {
        header('Location: login.php');
        exit;
    }
}

function setFlash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Антиспам: регистрация с одного IP разрешена, только пока число попыток
 * за последние REGISTRATION_WINDOW секунд не превысило порог.
 */
function registrationAllowed(string $ip): bool
{
    if ($ip === '') {
        return true;
    }

    $cutoff = time() - REGISTRATION_WINDOW;
    $count = 0;

    foreach (readJson(REGISTRATION_LOG_FILE) as $entry) {
        if (($entry['ip'] ?? '') === $ip && (int) ($entry['time'] ?? 0) >= $cutoff) {
            $count++;
        }
    }

    return $count < REGISTRATION_MAX_ATTEMPTS;
}

/**
 * Записывает попытку регистрации с IP и подчищает устаревшие записи.
 */
function recordRegistrationAttempt(string $ip): void
{
    $now = time();
    $cutoff = $now - REGISTRATION_WINDOW * 2;
    $entries = [];

    foreach (readJson(REGISTRATION_LOG_FILE) as $entry) {
        if ((int) ($entry['time'] ?? 0) >= $cutoff) {
            $entries[] = $entry;
        }
    }

    $entries[] = ['ip' => $ip, 'time' => $now];
    writeJson(REGISTRATION_LOG_FILE, $entries);
}
