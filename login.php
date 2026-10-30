<?php
require __DIR__ . '/storage.php';

if (currentUser()) {
    header('Location: dashboard.php');
    exit;
}

$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    if ($action === 'register') {
        $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        if (!registrationAllowed($clientIp)) {
            setFlash('Слишком много попыток регистрации с этого устройства. Подождите пару минут и попробуйте снова.', 'error');
            header('Location: login.php');
            exit;
        }

        recordRegistrationAttempt($clientIp);

        if ($name === '' || $email === '' || $password === '' || $confirmPassword === '') {
            setFlash('Заполните все поля.', 'error');
            header('Location: login.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('Введите корректный email.', 'error');
            header('Location: login.php');
            exit;
        }

        if ($password !== $confirmPassword) {
            setFlash('Пароли не совпадают.', 'error');
            header('Location: login.php');
            exit;
        }

        $passwordLength = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);
        if (
            $passwordLength < 6
            || !preg_match('/[A-ZА-ЯЁ]/u', $password)
            || !preg_match('/[0-9]/', $password)
        ) {
            setFlash('Пароль должен быть не короче 6 символов и содержать заглавную букву и цифру.', 'error');
            header('Location: login.php');
            exit;
        }

        $users = readJson(USERS_FILE);
        foreach ($users as $user) {
            if (strtolower((string) ($user['email'] ?? '')) === $email) {
                setFlash('Пользователь с таким email уже существует.', 'error');
                header('Location: login.php');
                exit;
            }
        }

        $userId = uniqid('user_', true);
        $users[] = [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        writeJson(USERS_FILE, $users);
        $_SESSION['user'] = ['id' => $userId, 'name' => $name, 'email' => $email];
        header('Location: dashboard.php');
        exit;
    }

    if ($email === '' || $password === '') {
        setFlash('Введите email и пароль.', 'error');
        header('Location: login.php');
        exit;
    }

    $users = readJson(USERS_FILE);
    foreach ($users as $user) {
        if (strtolower((string) ($user['email'] ?? '')) === $email && password_verify($password, (string) ($user['password'] ?? ''))) {
            $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']];
            header('Location: dashboard.php');
            exit;
        }
    }

    setFlash('Неверный email или пароль.', 'error');
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FitLog — Авторизация</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <div class="page-shell">
        <section class="brand-panel">
            <div class="brand-header">
                <div class="brand-badge">F</div>
                <span>FitLog</span>
            </div>

            <div class="brand-copy">
                <p class="eyebrow">Личный блокнот спортсмена</p>
                <h1>Тренировки, прогресс и результаты в одном месте.</h1>
            </div>

            <ul class="feature-list">
                <li><span class="icon">✓</span><span>Записывай подходы и веса</span></li>
                <li><span class="icon">✓</span><span>Отслеживай прогресс по дням</span></li>
                <li><span class="icon">✓</span><span>Храни заметки и планы тренировок</span></li>
            </ul>
        </section>

        <main class="auth-panel">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="toggle" aria-label="Переключатель режима входа">
                        <button class="toggle-btn active" type="button" data-mode="login">Вход</button>
                        <button class="toggle-btn" type="button" data-mode="register">Регистрация</button>
                    </div>
                </div>

                <p id="message" class="message<?php echo $flash ? ' ' . ($flash['type'] === 'error' ? 'error' : 'success') : ''; ?>"><?php echo $flash ? htmlspecialchars($flash['message']) : ''; ?></p>

                <form id="authForm" class="auth-form" method="post" action="login.php" novalidate>
                    <input type="hidden" name="action" id="formAction" value="login" />

                    <div class="field hidden" id="nameField">
                        <label for="name">Имя</label>
                        <input id="name" name="name" type="text" placeholder="Ваше имя" />
                    </div>

                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" placeholder="you@example.com" required />
                    </div>

                    <div class="field">
                        <label for="password">Пароль</label>
                        <div class="password-wrap">
                            <input id="password" name="password" type="password" placeholder="••••••••" required />
                            <button type="button" class="pw-toggle" data-target="password" aria-label="Показать пароль" aria-pressed="false">
                                <svg class="pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="field hidden" id="confirmField">
                        <label for="confirmPassword">Подтвердите пароль</label>
                        <div class="password-wrap">
                            <input id="confirmPassword" name="confirmPassword" type="password" placeholder="Повторите пароль" />
                            <button type="button" class="pw-toggle" data-target="confirmPassword" aria-label="Показать пароль" aria-pressed="false">
                                <svg class="pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="checkbox-wrap">
                            <input type="checkbox" id="remember" />
                            <span>Запомнить меня</span>
                        </label>
                        <a href="#" class="link">Забыли пароль?</a>
                    </div>

                    <button type="submit" class="primary-btn" id="submitBtn">Войти</button>
                </form>
            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>
