<?php
session_start();

const USERS_FILE = __DIR__ . '/data/users.json';
const WORKOUTS_FILE = __DIR__ . '/data/workouts.json';

function ensureStorage(): void
{
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }

    foreach ([USERS_FILE, WORKOUTS_FILE] as $path) {
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
