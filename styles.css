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

$exerciseCatalog = readJson(EXERCISES_FILE);
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
        $date = trim((string) ($_POST['date'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $exerciseNames = $_POST['exercise_name'] ?? [];
        $exerciseSets = $_POST['exercise_sets'] ?? [];

        if ($date === '') {
            setFlash('Укажите дату тренировки.', 'error');
            header('Location: dashboard.php');
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

                    if ($weight === 0 && $reps === 0) {
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
            header('Location: dashboard.php');
            exit;
        }

        $entry = [
            'id' => $id !== '' ? $id : uniqid('workout_', true),
            'user_email' => $user['email'],
            'date' => $date,
            'notes' => $notes,
            'exercises' => $exercises,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $updated = [];
        $found = false;

        foreach ($workouts as $item) {
            if (($item['id'] ?? '') === $id && (($item['user_email'] ?? '') === $user['email'])) {
                $updated[] = $entry;
                $found = true;
            } else {
                $updated[] = $item;
            }
        }

        if (!$found) {
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
$avgWeight = 0;

foreach ($myWorkouts as $item) {
    foreach (($item['exercises'] ?? []) as $exercise) {
        $summarySets += count((array) ($exercise['sets'] ?? []));
    }
}

if ($summaryTotal > 0) {
    $weights = [];
    foreach ($myWorkouts as $item) {
        foreach (($item['exercises'] ?? []) as $exercise) {
            foreach (($exercise['sets'] ?? []) as $set) {
                $weights[] = (float) ($set['weight'] ?? 0);
            }
        }
    }

    if (!empty($weights)) {
        $avgWeight = round(array_sum($weights) / count($weights), 1);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FitLog — Личный кабинет</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body class="dashboard-page">
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

            <div class="panel panel-inner">
                <div class="section-header">
                    <h3><?php echo $editing ? 'Редактировать тренировку' : 'Добавить тренировку'; ?></h3>
                </div>

                <form method="post" action="dashboard.php" class="workout-form" id="workoutForm">
                    <input type="hidden" name="action" value="save_workout" />
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($editing['id'] ?? '')); ?>" />

                    <div class="field-grid">
                        <div class="field">
                            <label for="date">Дата</label>
                            <input id="date" name="date" type="date" value="<?php echo htmlspecialchars((string) ($editing['date'] ?? date('Y-m-d'))); ?>" required />
                        </div>
                    </div>

                    <div class="field">
                        <label for="notes">Заметки</label>
                        <textarea id="notes" name="notes" placeholder="Что важно запомнить после тренировки?"><?php echo htmlspecialchars((string) ($editing['notes'] ?? '')); ?></textarea>
                    </div>

                    <div class="exercise-builder" id="exerciseBuilder" data-next-index="0">
                        <?php
                        $exerciseRows = $editing['exercises'] ?? [];
                        if (empty($exerciseRows)) {
                            $exerciseRows = [[ 'name' => '', 'sets' => [[ 'weight' => '', 'reps' => '' ]] ]];
                        }

                        foreach ($exerciseRows as $exerciseIndex => $exercise):
                            $exerciseName = $exercise['name'] ?? '';
                            $exerciseSets = $exercise['sets'] ?? [[ 'weight' => '', 'reps' => '' ]];
                            if (empty($exerciseSets)) {
                                $exerciseSets = [[ 'weight' => '', 'reps' => '' ]];
                            }
                        ?>
                            <div class="exercise-block" data-index="<?php echo $exerciseIndex; ?>">
                                <div class="exercise-header">
                                    <div class="field exercise-name-field">
                                        <label>Упражнение</label>
                                        <select name="exercise_name[<?php echo $exerciseIndex; ?>]" class="exercise-name-select">
                                            <option value="">Выберите упражнение</option>
                                            <?php foreach ($exerciseCatalog as $item): ?>
                                                <option value="<?php echo htmlspecialchars((string) $item); ?>" <?php echo $item === $exerciseName ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $item); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="button" class="remove-exercise-btn danger-btn">Удалить</button>
                                </div>

                                <div class="set-list">
                                    <?php foreach ($exerciseSets as $setIndex => $set): ?>
                                        <div class="set-row" data-set-index="<?php echo $setIndex; ?>">
                                            <div class="field">
                                                <label>Вес, кг</label>
                                                <input type="number" step="0.5" name="exercise_sets[<?php echo $exerciseIndex; ?>][<?php echo $setIndex; ?>][weight]" value="<?php echo htmlspecialchars((string) ($set['weight'] ?? '')); ?>" />
                                            </div>
                                            <div class="field">
                                                <label>Повторы</label>
                                                <input type="number" min="1" name="exercise_sets[<?php echo $exerciseIndex; ?>][<?php echo $setIndex; ?>][reps]" value="<?php echo htmlspecialchars((string) ($set['reps'] ?? '')); ?>" />
                                            </div>
                                            <button type="button" class="remove-set-btn danger-btn">×</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="button" class="secondary-btn add-set-btn">Добавить подход</button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="actions">
                        <button type="button" id="addExerciseBtn" class="secondary-btn">Добавить упражнение</button>
                        <button type="submit" class="primary-btn"><?php echo $editing ? 'Сохранить изменения' : 'Добавить тренировку'; ?></button>
                        <?php if ($editing): ?>
                            <a href="dashboard.php" class="secondary-btn">Отмена</a>
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
                    <p class="empty-state">Пока нет ни одной тренировки. Добавь первую и начни вести дневник.</p>
                <?php else: ?>
                    <?php foreach ($myWorkouts as $item): ?>
                        <article class="workout-item">
                            <div class="workout-item-header">
                                <strong><?php echo htmlspecialchars((string) ($item['date'] ?? '')); ?></strong>
                            </div>

                            <?php if (!empty($item['notes'])): ?>
                                <p class="workout-notes"><?php echo nl2br(htmlspecialchars((string) $item['notes'])); ?></p>
                            <?php endif; ?>

                            <div class="exercise-summary">
                                <?php foreach (($item['exercises'] ?? []) as $exercise): ?>
                                    <div class="exercise-summary-item">
                                        <div class="exercise-summary-header">
                                            <strong><?php echo htmlspecialchars((string) ($exercise['name'] ?? 'Упражнение')); ?></strong>
                                            <span><?php echo count((array) ($exercise['sets'] ?? [])); ?> подходов</span>
                                        </div>

                                        <div class="set-summary-list">
                                            <?php foreach (($exercise['sets'] ?? []) as $set): ?>
                                                <span><?php echo (float) ($set['weight'] ?? 0); ?> кг × <?php echo (int) ($set['reps'] ?? 0); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

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

<script>
    const exerciseCatalog = <?php echo json_encode($exerciseCatalog, JSON_UNESCAPED_UNICODE); ?>;
    const builder = document.getElementById('exerciseBuilder');

    function createExerciseOptionMarkup() {
        return ['<option value="">Выберите упражнение</option>']
            .concat(exerciseCatalog.map((exercise) => `<option value="${exercise}">${exercise}</option>`))
            .join('');
    }

    function addSetRow(exerciseBlock, setIndex = null) {
        const setList = exerciseBlock.querySelector('.set-list');
        const exerciseIndex = exerciseBlock.dataset.index;
        const nextIndex = setIndex ?? setList.children.length;

        const row = document.createElement('div');
        row.className = 'set-row';
        row.dataset.setIndex = String(nextIndex);
        row.innerHTML = `
            <div class="field">
                <label>Вес, кг</label>
                <input type="number" step="0.5" name="exercise_sets[${exerciseIndex}][${nextIndex}][weight]" />
            </div>
            <div class="field">
                <label>Повторы</label>
                <input type="number" min="1" name="exercise_sets[${exerciseIndex}][${nextIndex}][reps]" />
            </div>
            <button type="button" class="remove-set-btn danger-btn">×</button>
        `;

        setList.appendChild(row);

        row.querySelector('.remove-set-btn').addEventListener('click', () => {
            row.remove();
        });
    }

    function addExerciseBlock() {
        const index = builder.querySelectorAll('.exercise-block').length;
        const block = document.createElement('div');
        block.className = 'exercise-block';
        block.dataset.index = String(index);
        block.innerHTML = `
            <div class="exercise-header">
                <div class="field exercise-name-field">
                    <label>Упражнение</label>
                    <select name="exercise_name[${index}]" class="exercise-name-select">
                        ${createExerciseOptionMarkup()}
                    </select>
                </div>
                <button type="button" class="remove-exercise-btn danger-btn">Удалить</button>
            </div>
            <div class="set-list">
                <div class="set-row" data-set-index="0">
                    <div class="field">
                        <label>Вес, кг</label>
                        <input type="number" step="0.5" name="exercise_sets[${index}][0][weight]" />
                    </div>
                    <div class="field">
                        <label>Повторы</label>
                        <input type="number" min="1" name="exercise_sets[${index}][0][reps]" />
                    </div>
                    <button type="button" class="remove-set-btn danger-btn">×</button>
                </div>
            </div>
            <button type="button" class="secondary-btn add-set-btn">Добавить подход</button>
        `;

        block.querySelector('.add-set-btn').addEventListener('click', () => addSetRow(block));
        block.querySelector('.remove-exercise-btn').addEventListener('click', () => block.remove());
        block.querySelector('.remove-set-btn').addEventListener('click', () => {
            const setRow = block.querySelector('.set-row');
            if (block.querySelectorAll('.set-row').length > 1) {
                setRow.remove();
            }
        });

        builder.appendChild(block);
    }

    document.getElementById('addExerciseBtn').addEventListener('click', addExerciseBlock);

    document.querySelectorAll('.add-set-btn').forEach((button) => {
        button.addEventListener('click', () => addSetRow(button.closest('.exercise-block')));
    });

    document.querySelectorAll('.remove-exercise-btn').forEach((button) => {
        button.addEventListener('click', () => button.closest('.exercise-block').remove());
    });

    document.querySelectorAll('.remove-set-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const block = button.closest('.exercise-block');
            const row = button.closest('.set-row');
            if (block.querySelectorAll('.set-row').length > 1) {
                row.remove();
            }
        });
    });
</script>
</body>
</html>
