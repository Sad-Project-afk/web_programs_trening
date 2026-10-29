<?php
require __DIR__ . '/storage.php';
requireAuth();

$user = currentUser();
$workouts = readJson(WORKOUTS_FILE);
$myWorkouts = [];

foreach ($workouts as $workout) {
    if (($workout['user_email'] ?? '') === $user['email']) {
        $myWorkouts[] = $workout;
    }
}

usort($myWorkouts, function ($a, $b) {
    return strtotime((string) ($b['date'] ?? '1970-01-01')) <=> strtotime((string) ($a['date'] ?? '1970-01-01'));
});

$editId = $_GET['edit'] ?? null;
$editing = null;
if ($editId) {
    foreach ($myWorkouts as $item) {
        if (($item['id'] ?? '') === (string) $editId) {
            $editing = $item;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_workout') {
        $deleteId = (string) ($_POST['id'] ?? '');
        $updated = [];

        foreach ($workouts as $item) {
            if (($item['id'] ?? '') !== $deleteId || (($item['user_email'] ?? '') !== $user['email'])) {
                $updated[] = $item;
            }
        }

        writeJson(WORKOUTS_FILE, $updated);
        setFlash('Тренировка удалена.', 'success');
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'save_workout') {
        $id = (string) ($_POST['id'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        $date = trim((string) ($_POST['date'] ?? ''));
        $muscle = trim((string) ($_POST['muscle'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $sets = (int) ($_POST['sets'] ?? 0);
        $reps = (int) ($_POST['reps'] ?? 0);
        $weight = (float) ($_POST['weight'] ?? 0);

        if ($title === '' || $date === '') {
            setFlash('Название тренировки и дата обязательны.', 'error');
            header('Location: dashboard.php');
            exit;
        }

        $entry = [
            'id' => $id !== '' ? $id : uniqid('workout_', true),
            'user_email' => $user['email'],
            'title' => $title,
            'date' => $date,
            'muscle' => $muscle,
            'sets' => $sets,
            'reps' => $reps,
            'weight' => $weight,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $updated = [];
        $hasFound = false;

        foreach ($workouts as $item) {
            if (($item['id'] ?? '') === $id && (($item['user_email'] ?? '') === $user['email'])) {
                $updated[] = $entry;
                $hasFound = true;
            } else {
                $updated[] = $item;
            }
        }

        if (!$hasFound && $id !== '') {
            $updated[] = $entry;
        }

        if ($id === '') {
            $updated[] = $entry;
        }

        writeJson(WORKOUTS_FILE, $updated);
        setFlash($id === '' ? 'Тренировка добавлена.' : 'Тренировка обновлена.', 'success');
        header('Location: dashboard.php');
        exit;
    }
}

$flash = getFlash();
$summarySets = 0;
$summaryTotal = count($myWorkouts);
foreach ($myWorkouts as $item) {
    $summarySets += (int) ($item['sets'] ?? 0);
}
$avgWeight = 0;
if ($summaryTotal > 0) {
    $weights = array_map(fn ($item) => (float) ($item['weight'] ?? 0), $myWorkouts);
    $avgWeight = round(array_sum($weights) / count($weights), 1);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FitLog — Личный кабинет</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        body {
            margin: 0;
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f4f7fb 0%, #eef7f5 100%);
            color: #1f2937;
        }

        .dashboard-layout {
            max-width: 1280px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 22px;
            border-radius: 22px;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.06);
        }

        .brand-mini {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
        }

        .brand-mini .badge {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(135deg, #ff7b4d, #ffb067);
            color: white;
        }

        .user-block {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff7b4d, #ffb067);
            color: white;
            display: grid;
            place-items: center;
            font-weight: 700;
        }

        .user-meta { line-height: 1.3; }
        .user-meta small { color: #6b7280; }

        .logout-link {
            background: #111827;
            color: white;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 12px;
            font-weight: 600;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            margin-top: 24px;
        }

        .panel {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.05);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .stat-card {
            border-radius: 20px;
            padding: 18px 16px;
            background: linear-gradient(135deg, #fff7f4, #f5f9ff);
            border: 1px solid rgba(15, 23, 42, 0.04);
        }

        .stat-card small { display: block; color: #6b7280; margin-bottom: 8px; }
        .stat-card strong {
            font-size: clamp(1.6rem, 2vw, 2.3rem);
            display: block;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-header h2,
        .section-header h3 {
            margin: 0;
        }

        .workout-form {
            display: grid;
            gap: 16px;
            margin-top: 18px;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
        }

        .field input,
        .field textarea {
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 14px;
            font: inherit;
            color: #111827;
        }

        .field textarea {
            resize: vertical;
            min-height: 84px;
        }

        .primary-btn,
        .secondary-btn,
        .danger-btn {
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .primary-btn {
            background: linear-gradient(135deg, #ff7b4d, #ea582d);
            color: white;
            box-shadow: 0 12px 20px rgba(255, 110, 61, 0.25);
        }

        .secondary-btn {
            background: #f3f4f6;
            color: #111827;
        }

        .danger-btn {
            background: #fff1f2;
            color: #be123c;
        }

        .workout-list {
            display: grid;
            gap: 14px;
            margin-top: 14px;
        }

        .workout-item {
            background: #f8fafc;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 18px;
            padding: 16px;
        }

        .workout-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .workout-item-header strong {
            font-size: 1.05rem;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #fff4ee;
            color: #d75b2d;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.9rem;
            color: #4b5563;
            margin: 10px 0;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 12px;
        }

        .message {
            margin: 14px 0 0;
            min-height: 24px;
            font-weight: 700;
        }

        .message.success { color: #0f766e; }
        .message.error { color: #b91c1c; }

        .empty-state {
            color: #6b7280;
            background: #f8fafc;
            border: 1px dashed rgba(15, 23, 42, 0.12);
            border-radius: 14px;
            padding: 18px;
            margin: 0;
        }

        @media (max-width: 920px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .topbar,
            .section-header,
            .workout-item-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid,
            .field-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <header class="topbar">
        <div class="brand-mini">
            <div class="badge">F</div>
            <span>FitLog</span>
        </div>

        <div class="user-block">
            <div class="avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
            <div class="user-meta">
                <div><strong><?php echo htmlspecialchars($user['name']); ?></strong></div>
                <small><?php echo htmlspecialchars($user['email']); ?></small>
            </div>
            <a href="logout.php" class="logout-link">Выйти</a>
        </div>
    </header>

    <?php if ($flash): ?>
        <p class="message <?php echo $flash['type'] === 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($flash['message']); ?></p>
    <?php endif; ?>

    <div class="dashboard-grid">
        <section class="panel">
            <div class="section-header">
                <h2>Обзор</h2>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <small>Тренировки</small>
                    <strong><?php echo $summaryTotal; ?></strong>
                </div>
                <div class="stat-card">
                    <small>Подходы</small>
                    <strong><?php echo $summarySets; ?></strong>
                </div>
                <div class="stat-card">
                    <small>Средний вес</small>
                    <strong><?php echo $avgWeight; ?> кг</strong>
                </div>
            </div>

            <div class="panel" style="margin-top: 24px; padding: 20px;">
                <div class="section-header">
                    <h3><?php echo $editing ? 'Редактировать тренировку' : 'Добавить тренировку'; ?></h3>
                </div>

                <form method="post" action="dashboard.php" class="workout-form">
                    <input type="hidden" name="action" value="save_workout" />
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($editing['id'] ?? '')); ?>" />

                    <div class="field-grid">
                        <div class="field">
                            <label for="title">Название</label>
                            <input id="title" name="title" type="text" value="<?php echo htmlspecialchars((string) ($editing['title'] ?? '')); ?>" placeholder="Жим лёжа" required />
                        </div>
                        <div class="field">
                            <label for="date">Дата</label>
                            <input id="date" name="date" type="date" value="<?php echo htmlspecialchars((string) ($editing['date'] ?? '')); ?>" required />
                        </div>
                    </div>

                    <div class="field-grid">
                        <div class="field">
                            <label for="muscle">Группа мышц</label>
                            <input id="muscle" name="muscle" type="text" value="<?php echo htmlspecialchars((string) ($editing['muscle'] ?? '')); ?>" placeholder="Грудь / Ноги / Спина" />
                        </div>
                        <div class="field">
                            <label for="weight">Вес, кг</label>
                            <input id="weight" name="weight" type="number" step="0.5" value="<?php echo htmlspecialchars((string) ($editing['weight'] ?? 0)); ?>" />
                        </div>
                    </div>

                    <div class="field-grid">
                        <div class="field">
                            <label for="sets">Подходы</label>
                            <input id="sets" name="sets" type="number" min="0" value="<?php echo htmlspecialchars((string) ($editing['sets'] ?? 0)); ?>" />
                        </div>
                        <div class="field">
                            <label for="reps">Повторения</label>
                            <input id="reps" name="reps" type="number" min="0" value="<?php echo htmlspecialchars((string) ($editing['reps'] ?? 0)); ?>" />
                        </div>
                    </div>

                    <div class="field">
                        <label for="notes">Заметки</label>
                        <textarea id="notes" name="notes" placeholder="Что важно запомнить после тренировки?"><?php echo htmlspecialchars((string) ($editing['notes'] ?? '')); ?></textarea>
                    </div>

                    <div class="actions">
                        <button class="primary-btn" type="submit"><?php echo $editing ? 'Сохранить изменения' : 'Добавить тренировку'; ?></button>
                        <?php if ($editing): ?>
                            <a class="secondary-btn" href="dashboard.php">Отмена</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <aside class="panel">
            <div class="section-header">
                <h2>История</h2>
            </div>

            <div class="workout-list">
                <?php if (empty($myWorkouts)): ?>
                    <p class="empty-state">Пока нет ни одной записи о тренировке. Добавь первую сессию.</p>
                <?php else: ?>
                    <?php foreach ($myWorkouts as $item): ?>
                        <article class="workout-item">
                            <div class="workout-item-header">
                                <strong><?php echo htmlspecialchars((string) ($item['title'] ?? 'Без названия')); ?></strong>
                                <span class="badge"><?php echo htmlspecialchars((string) ($item['muscle'] ?: 'Общая')); ?></span>
                            </div>

                            <div style="color:#6b7280; font-size: 0.9rem;">
                                <?php echo htmlspecialchars((string) ($item['date'] ?? '')); ?>
                            </div>

                            <div class="meta-row">
                                <span>Подходы: <?php echo (int) ($item['sets'] ?? 0); ?></span>
                                <span>Повторения: <?php echo (int) ($item['reps'] ?? 0); ?></span>
                                <span>Вес: <?php echo (float) ($item['weight'] ?? 0); ?> кг</span>
                            </div>

                            <?php if (!empty($item['notes'])): ?>
                                <p style="margin: 0; color: #374151; line-height: 1.5;">
                                    <?php echo nl2br(htmlspecialchars((string) $item['notes'])); ?>
                                </p>
                            <?php endif; ?>

                            <div class="actions">
                                <a class="secondary-btn" href="dashboard.php?edit=<?php echo rawurlencode((string) ($item['id'] ?? '')); ?>">Редактировать</a>
                                <form method="post" action="dashboard.php" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_workout" />
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($item['id'] ?? '')); ?>" />
                                    <button type="submit" class="danger-btn">Удалить</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
</body>
</html>
