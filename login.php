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

                <?php if ($flash): ?>
                    <p id="message" class="message <?php echo $flash['type'] === 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($flash['message']); ?></p>
                <?php endif; ?>

                <form id="authForm" class="auth-form" method="post" action="login.php">
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
                        <input id="password" name="password" type="password" placeholder="••••••••" required />
                    </div>

                    <div class="field hidden" id="confirmField">
                        <label for="confirmPassword">Подтвердите пароль</label>
                        <input id="confirmPassword" name="confirmPassword" type="password" placeholder="Повторите пароль" />
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
