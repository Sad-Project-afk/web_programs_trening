<?php

/**
 * Слой БД поверх PDO: поддерживает MySQL (хостинг) и SQLite (локальная разработка).
 * Драйвер выбирается в config.php. Нормализованная схема:
 * users / workouts / workout_exercises / workout_sets / registration_log.
 */

function fitlogConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $file = __DIR__ . '/config.php';
    $config = is_file($file) ? (array) require $file : ['driver' => 'sqlite'];

    return $config;
}

function dbDriver(): string
{
    $c = fitlogConfig();
    return ($c['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
}

function dbPdo(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $c = fitlogConfig();

    if (dbDriver() === 'sqlite') {
        $path = $c['sqlite_path'] ?? __DIR__ . '/data/fitlog.sqlite';
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $host = $c['host'] ?? 'localhost';
        $port = isset($c['port']) ? ';port=' . (int) $c['port'] : '';
        $dbname = (string) ($c['database'] ?? '');

        $dsn = "mysql:host={$host}{$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, (string) ($c['username'] ?? ''), (string) ($c['password'] ?? ''));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    dbEnsureSchema($pdo);

    return $pdo;
}

/**
 * Создаёт таблицы, если их ещё нет.
 */
function dbEnsureSchema(?PDO $pdo = null): void
{
    $pdo = $pdo ?? dbPdo();
    $isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    // MySQL: движок InnoDB + внешние ключи с каскадным удалением.
    $engine = $isMysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id VARCHAR(64) PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME
    ){$engine};");

    $pdo->exec("CREATE TABLE IF NOT EXISTS workouts (
        id VARCHAR(64) PRIMARY KEY,
        user_id VARCHAR(64) NOT NULL,
        date DATE NOT NULL,
        notes TEXT,
        created_at DATETIME
    ){$engine};");

    $pdo->exec("CREATE TABLE IF NOT EXISTS workout_exercises (
        id VARCHAR(64) PRIMARY KEY,
        workout_id VARCHAR(64) NOT NULL,
        name VARCHAR(255) NOT NULL,
        position INT NOT NULL DEFAULT 0,
        FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE CASCADE
    ){$engine};");

    $pdo->exec("CREATE TABLE IF NOT EXISTS workout_sets (
        id VARCHAR(64) PRIMARY KEY,
        exercise_id VARCHAR(64) NOT NULL,
        weight DECIMAL(8,2) NOT NULL DEFAULT 0,
        reps INT NOT NULL DEFAULT 0,
        position INT NOT NULL DEFAULT 0,
        FOREIGN KEY (exercise_id) REFERENCES workout_exercises(id) ON DELETE CASCADE
    ){$engine};");

    $autoId = $isMysql ? 'id INT AUTO_INCREMENT PRIMARY KEY' : 'id INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_log (
        {$autoId},
        ip VARCHAR(45) NOT NULL,
        attempt_time INT NOT NULL
    ){$engine};");

    if (!$isMysql) {
        // SQLite не поддерживает INDEX внутри CREATE TABLE.
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_workouts_user ON workouts(user_id);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_exercises_workout ON workout_exercises(workout_id);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sets_exercise ON workout_sets(exercise_id);');
    }
}

/**
 * Читает всех пользователей в формате старых JSON-данных.
 */
function dbUsers(): array
{
    $rows = dbPdo()->query('SELECT id, email, name, password, created_at FROM users')->fetchAll();
    $users = [];

    foreach ($rows as $row) {
        $users[] = [
            'id' => (string) $row['id'],
            'email' => (string) $row['email'],
            'name' => (string) $row['name'],
            'password' => (string) $row['password'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    return $users;
}

/**
 * Записывает/обновляет пользователей (upsert по id/email).
 */
function dbSaveUsers(array $users): void
{
    $pdo = dbPdo();
    $isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    $sql = $isMysql
        ? 'INSERT INTO users (id, email, name, password, created_at)
           VALUES (?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE email = VALUES(email), name = VALUES(name),
                                   password = VALUES(password), created_at = VALUES(created_at)'
        : 'INSERT OR REPLACE INTO users (id, email, name, password, created_at) VALUES (?, ?, ?, ?, ?)';

    $stmt = $pdo->prepare($sql);

    foreach ($users as $user) {
        $stmt->execute([
            (string) ($user['id'] ?? ''),
            (string) ($user['email'] ?? ''),
            (string) ($user['name'] ?? ''),
            (string) ($user['password'] ?? ''),
            (string) ($user['created_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }
}

/**
 * Все тренировки всех пользователей в формате, который ждёт бизнес-логика:
 * вложенные массивы exercises[].sets[] + user_email.
 */
function dbWorkouts(): array
{
    $rows = dbPdo()->query(
        "SELECT w.id AS w_id, u.email AS user_email, w.date AS w_date, w.notes AS w_notes,
                w.created_at AS w_created, e.id AS e_id, e.name AS e_name, e.position AS e_pos,
                s.id AS s_id, s.weight AS s_weight, s.reps AS s_reps, s.position AS s_pos
         FROM workouts w
         LEFT JOIN users u ON u.id = w.user_id
         LEFT JOIN workout_exercises e ON e.workout_id = w.id
         LEFT JOIN workout_sets s ON s.exercise_id = e.id
         ORDER BY w.created_at ASC, w.id ASC, e_pos ASC, s_pos ASC"
    )->fetchAll();

    $workouts = [];
    $byId = [];

    foreach ($rows as $row) {
        $wid = (string) $row['w_id'];

        if (!isset($byId[$wid])) {
            $byId[$wid] = count($workouts);
            $workouts[] = [
                'id' => $wid,
                'user_email' => (string) $row['user_email'],
                'date' => (string) $row['w_date'],
                'notes' => (string) $row['w_notes'],
                'created_at' => (string) $row['w_created'],
                'exercises' => [],
            ];
        }

        $eid = (string) $row['e_id'];
        if ($eid === '') {
            continue;
        }

        $wi = $byId[$wid];

        $exIndex = null;
        foreach ($workouts[$wi]['exercises'] as $i => $exercise) {
            if ($exercise['id'] === $eid) {
                $exIndex = $i;
                break;
            }
        }

        if ($exIndex === null) {
            $workouts[$wi]['exercises'][] = [
                'id' => $eid,
                'name' => (string) $row['e_name'],
                'sets' => [],
            ];
            $exIndex = count($workouts[$wi]['exercises']) - 1;
        }

        if ($row['s_id'] !== null) {
            $workouts[$wi]['exercises'][$exIndex]['sets'][] = [
                'weight' => (float) $row['s_weight'],
                'reps' => (int) $row['s_reps'],
            ];
        }
    }

    return $workouts;
}

/**
 * Полная перезапись тренировок (аналог прежней записи всего JSON-файла).
 */
function dbSaveWorkouts(array $workouts): void
{
    $pdo = dbPdo();

    // email -> id пользователя
    $userIds = [];
    foreach (dbUsers() as $user) {
        $userIds[$user['email']] = $user['id'];
    }

    $pdo->beginTransaction();

    try {
        $pdo->exec('DELETE FROM workout_sets');
        $pdo->exec('DELETE FROM workout_exercises');
        $pdo->exec('DELETE FROM workouts');

        $insWorkout = $pdo->prepare(
            'INSERT INTO workouts (id, user_id, date, notes, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $insExercise = $pdo->prepare(
            'INSERT INTO workout_exercises (id, workout_id, name, position) VALUES (?, ?, ?, ?)'
        );
        $insSet = $pdo->prepare(
            'INSERT INTO workout_sets (id, exercise_id, weight, reps, position) VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($workouts as $workout) {
            $email = (string) ($workout['user_email'] ?? '');
            $userId = $userIds[$email] ?? null;

            if ($userId === null) {
                continue;
            }

            $insWorkout->execute([
                (string) ($workout['id'] ?? ''),
                $userId,
                (string) ($workout['date'] ?? ''),
                (string) ($workout['notes'] ?? ''),
                (string) ($workout['created_at'] ?? date('Y-m-d H:i:s')),
            ]);

            $exercisePosition = 0;

            foreach (($workout['exercises'] ?? []) as $exercise) {
                $exerciseId = (string) ($exercise['id'] ?? '');
                if ($exerciseId === '') {
                    $exerciseId = uniqid('exercise_', true);
                }

                $insExercise->execute([
                    $exerciseId,
                    (string) ($workout['id'] ?? ''),
                    (string) ($exercise['name'] ?? ''),
                    $exercisePosition,
                ]);

                $setPosition = 0;

                foreach (($exercise['sets'] ?? []) as $set) {
                    $insSet->execute([
                        uniqid('set_', true),
                        $exerciseId,
                        (float) ($set['weight'] ?? 0),
                        (int) ($set['reps'] ?? 0),
                        $setPosition,
                    ]);
                    $setPosition++;
                }

                $exercisePosition++;
            }
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Журнал регистраций (антиспам).
 */
function dbRegistrationLog(): array
{
    $rows = dbPdo()->query('SELECT ip, attempt_time AS t FROM registration_log ORDER BY attempt_time ASC')->fetchAll();

    $entries = [];
    foreach ($rows as $row) {
        $entries[] = ['ip' => (string) $row['ip'], 'time' => (int) $row['t']];
    }

    return $entries;
}

function dbSaveRegistrationLog(array $entries): void
{
    $pdo = dbPdo();
    $pdo->beginTransaction();

    try {
        $pdo->exec('DELETE FROM registration_log');
        $stmt = $pdo->prepare('INSERT INTO registration_log (ip, attempt_time) VALUES (?, ?)');

        foreach ($entries as $entry) {
            $stmt->execute([
                (string) ($entry['ip'] ?? ''),
                (int) ($entry['time'] ?? 0),
            ]);
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Обновляет пароль пользователя (hash уже посчитан).
 */
function dbUpdateUserPassword(string $userId, string $passwordHash): void
{
    $stmt = dbPdo()->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$passwordHash, $userId]);
}

/**
 * Удаляет пользователя вместе с его тренировками
 * (упражнения и подходы удаляются каскадом по внешним ключам).
 */
function dbDeleteUser(string $userId): void
{
    $pdo = dbPdo();
    $pdo->beginTransaction();

    try {
        $pdo->prepare('DELETE FROM workouts WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}