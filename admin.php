<?php
require __DIR__ . '/storage.php';
require __DIR__ . '/workouts.php';
require __DIR__ . '/layout.php';

requireAuth();

if (!isAdmin()) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8" /><title>Доступ запрещён</title>'
        . '<link rel="stylesheet" href="styles.css" /></head><body class="dashboard-page">'
        . '<div class="dashboard-layout"><p class="message error" style="margin-top:20px">У вас нет прав администратора.</p>'
        . '<p><a href="dashboard.php" class="secondary-btn">На главную</a></p></div></body></html>';
    exit;
}

$user = currentUser();
$meEmail = strtolower(trim((string) ($user['email'] ?? '')));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrfVerify()) {
        setFlash('Неверный токен безопасности. Обновите страницу и повторите.', 'error');
        header('Location: admin.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_user') {
        $targetId = (string) ($_POST['id'] ?? '');

        foreach (readJson(USERS_FILE) as $target) {
            if ((string) ($target['id'] ?? '') === $targetId) {
                if (strtolower(trim((string) ($target['email'] ?? ''))) === $meEmail) {
                    setFlash('Нельзя удалить собственную учётную запись.', 'error');
                    header('Location: admin.php');
                    exit;
                }

                dbDeleteUser($targetId);
                setFlash('Пользователь и его тренировки удалены.', 'success');
                header('Location: admin.php');
                exit;
            }
        }

        setFlash('Пользователь не найден.', 'error');
        header('Location: admin.php');
        exit;
    }

    if ($action === 'reset_password') {
        $targetId = (string) ($_POST['id'] ?? '');
        $newPassword = (string) ($_POST['password'] ?? '');

        if (!passwordMeetsPolicy($newPassword)) {
            setFlash('Пароль должен быть не короче 6 символов и содержать заглавную букву и цифру.', 'error');
            header('Location: admin.php');
            exit;
        }

        dbUpdateUserPassword($targetId, password_hash($newPassword, PASSWORD_DEFAULT));
        setFlash('Пароль обновлён.', 'success');
        header('Location: admin.php');
        exit;
    }

    if ($action === 'add_exercise') {
        $name = trim((string) ($_POST['name'] ?? ''));

        if (catalogAdd($name)) {
            setFlash('Упражнение добавлено в каталог.', 'success');
        } else {
            setFlash('Упражнение не добавлено (пустое имя или уже есть в каталоге).', 'error');
        }

        header('Location: admin.php');
        exit;
    }

    if ($action === 'delete_exercise') {
        $name = (string) ($_POST['name'] ?? '');

        if (catalogRemove($name)) {
            setFlash('Упражнение удалено из каталога.', 'success');
        } else {
            setFlash('Упражнение в каталоге не найдено.', 'error');
        }

        header('Location: admin.php');
        exit;
    }
}

$users = readJson(USERS_FILE);
$catalog = readJson(EXERCISES_FILE);
$flash = getFlash();
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#f4f7fb" />
    <title>FitLog — Админ</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body class="dashboard-page">
<div class="dashboard-layout">
    <?php renderTopbar($user); ?>
    <nav class="main-nav" aria-label="Меню">
        <a href="dashboard.php" class="nav-link">Тренировка</a>
        <a href="history.php" class="nav-link">История</a>
        <a href="progress.php" class="nav-link">Прогресс</a>
    </nav>
    <?php renderFlash($flash); ?>

    <main class="page-single">
        <section class="panel">
            <div class="section-header">
                <h2>Пользователи</h2>
                <span class="history-count"><?php echo count($users); ?></span>
            </div>

            <div class="admin-list">
                <?php foreach ($users as $item): ?>
                    <div class="admin-user">
                        <div class="admin-user-main">
                            <strong><?php echo htmlspecialchars((string) ($item['name'] ?? '')); ?></strong>
                            <span><?php echo htmlspecialchars((string) ($item['email'] ?? '')); ?></span>
                            <small>с <?php echo htmlspecialchars((string) ($item['created_at'] ?? '')); ?></small>
                        </div>

                        <details class="admin-actions">
                            <summary>Действия</summary>
                            <form method="post" action="admin.php" class="inline-form">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                                <input type="hidden" name="action" value="reset_password" />
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($item['id'] ?? '')); ?>" />
                                <label>Новый пароль
                                    <input type="password" name="password" placeholder="минимум 6, заглавная + цифра" />
                                </label>
                                <button type="submit" class="secondary-btn">Сбросить пароль</button>
                            </form>
                            <form method="post" action="admin.php" class="inline-form"
                                  onsubmit="return confirm('Удалить пользователя и ВСЕ его тренировки?');">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                                <input type="hidden" name="action" value="delete_user" />
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($item['id'] ?? '')); ?>" />
                                <button type="submit" class="danger-btn">Удалить</button>
                            </form>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <div class="section-header">
                <h2>Каталог упражнений</h2>
                <span class="history-count"><?php echo count($catalog); ?></span>
            </div>

            <form method="post" action="admin.php" class="admin-add-form">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                <input type="hidden" name="action" value="add_exercise" />
                <input type="text" name="name" placeholder="Название нового упражнения" />
                <button type="submit" class="primary-btn">Добавить</button>
            </form>

            <div class="admin-list compact">
                <?php foreach ($catalog as $name): ?>
                    <div class="admin-exercise">
                        <span><?php echo htmlspecialchars((string) $name); ?></span>
                        <form method="post" action="admin.php" class="inline-form"
                              onsubmit="return confirm('Убрать «<?php echo htmlspecialchars((string) $name, ENT_QUOTES); ?>» из каталога?');">
                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>" />
                            <input type="hidden" name="action" value="delete_exercise" />
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars((string) $name); ?>" />
                            <button type="submit" class="remove-exercise-btn">Убрать</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>