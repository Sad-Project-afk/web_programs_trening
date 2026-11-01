<?php

require __DIR__ . '/db.php';

/** Регистрационный порог: не больше этого числа попыток за окно с одного IP. */
const REGISTRATION_MAX_ATTEMPTS = 3;
/** Длина окна антиспама в секундах. */
const REGISTRATION_WINDOW = 120;

// «Запомнить меня»: если стоит маркер, сессия живёт 30 дней,
// а не до закрытия браузера.
$rememberSession = isset($_COOKIE['fitlog_remember']) && $_COOKIE['fitlog_remember'] === '1';

if ($rememberSession) {
    ini_set('session.gc_maxlifetime', 30 * 24 * 3600);
    session_set_cookie_params([
        'lifetime' => 30 * 24 * 3600,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

session_start();

const USERS_FILE = __DIR__ . '/data/users.json';
const WORKOUTS_FILE = __DIR__ . '/data/workouts.json';
const EXERCISES_FILE = __DIR__ . '/data/exercises.json';
const REGISTRATION_LOG_FILE = __DIR__ . '/data/registration_log.json';

/**
 * Гарантирует наличие папки data/, справочника упражнений и таблиц БД.
 */
function ensureStorage(): void
{
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }

    if (!file_exists(EXERCISES_FILE)) {
        file_put_contents(EXERCISES_FILE, '[]');
    }
}

/**
 * Чтение данных. Совместимо со старым API: массив в том же формате.
 * Справочник упражнений остаётся файловым, остальное — в БД.
 */
function readJson(string $path): array
{
    if ($path === EXERCISES_FILE) {
        return readCatalogFile();
    }

    ensureStorage();

    if ($path === USERS_FILE) {
        return dbUsers();
    }

    if ($path === WORKOUTS_FILE) {
        return dbWorkouts();
    }

    if ($path === REGISTRATION_LOG_FILE) {
        return dbRegistrationLog();
    }

    return [];
}

/**
 * Чтение файлового справочника упражнений.
 */
function readCatalogFile(): array
{
    if (!file_exists(EXERCISES_FILE)) {
        return [];
    }

    $content = @file_get_contents(EXERCISES_FILE);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

/**
 * Запись данных. Совместимо со старым API: принимает тот же массив целиком.
 */
function writeJson(string $path, array $data): void
{
    ensureStorage();

    if ($path === USERS_FILE) {
        dbSaveUsers($data);
        return;
    }

    if ($path === WORKOUTS_FILE) {
        dbSaveWorkouts($data);
        return;
    }

    if ($path === REGISTRATION_LOG_FILE) {
        dbSaveRegistrationLog($data);
        return;
    }
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
 * Администраторы: список email из конфига admin_emails.
 * Если список пуст — админ — первый зарегистрированный пользователь.
 */
function isAdmin(): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }

    $email = strtolower(trim((string) ($user['email'] ?? '')));
    $configured = fitlogConfig()['admin_emails'] ?? [];

    if (is_array($configured) && $configured !== []) {
        foreach ($configured as $adminEmail) {
            if (strtolower(trim((string) $adminEmail)) === $email) {
                return true;
            }
        }

        return false;
    }

    $users = readJson(USERS_FILE);
    $firstEmail = isset($users[0]['email']) ? strtolower(trim((string) $users[0]['email'])) : '';
    return $firstEmail !== '' && $firstEmail === $email;
}

/**
 * Простая CSRF-защита для админских форм.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function csrfVerify(): bool
{
    $token = (string) ($_POST['csrf'] ?? '');
    return $token !== '' && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/**
 * Требования к паролю (одинаковы при регистрации и в админке).
 */
function passwordMeetsPolicy(string $password): bool
{
    $length = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);

    return $length >= 6
        && preg_match('/[A-ZА-ЯЁ]/u', $password)
        && preg_match('/[0-9]/', $password);
}

/**
 * Нижний регистр: mb_strtolower если есть, иначе strtolower (ASCII).
 */
function mbLower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

/**
 * Работа с файловым каталогом упражнений.
 */
function catalogAdd(string $name): bool
{
    $name = trim($name);
    if ($name === '') {
        return false;
    }

    $catalog = readJson(EXERCISES_FILE);

    foreach ($catalog as $item) {
        if (mbLower((string) $item) === mbLower($name)) {
            return false;
        }
    }

    $catalog[] = $name;
    writeCatalogFile($catalog);
    return true;
}

function catalogRemove(string $name): bool
{
    $catalog = readJson(EXERCISES_FILE);
    $updated = [];

    foreach ($catalog as $item) {
        if ((string) $item === $name) {
            continue;
        }
        $updated[] = $item;
    }

    if (count($updated) === count($catalog)) {
        return false;
    }

    writeCatalogFile($updated);
    return true;
}

function writeCatalogFile(array $catalog): void
{
    $json = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents(EXERCISES_FILE, $json);
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