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

function pluralRu(int $count, string $one, string $few, string $many): string
{
    $n10 = $count % 10;
    $n100 = $count % 100;

    if ($n10 === 1 && $n100 !== 11) {
        return $one;
    }

    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) {
        return $few;
    }

    return $many;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#f4f7fb" />
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
            <a href="logout.php" class="logout-link" title="Выйти из аккаунта">Выйти</a>
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
                    <strong><?php echo $avgWeight; ?> <span class="stat-unit">кг</span></strong>
                </div>
            </div>

            <div class="panel panel-inner">
                <div class="section-header">
                    <h3><?php echo $editing ? 'Редактировать тренировку' : 'Новая тренировка'; ?></h3>
                </div>

                <form method="post" action="dashboard.php" class="workout-form" id="workoutForm">
                    <input type="hidden" name="action" value="save_workout" />
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($editing['id'] ?? '')); ?>" />

                    <div class="field">
                        <label for="date">Дата</label>
                        <input id="date" name="date" type="date" value="<?php echo htmlspecialchars((string) ($editing['date'] ?? date('Y-m-d'))); ?>" required />
                    </div>

                    <div class="exercise-builder" id="exerciseBuilder">
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
                            <article class="exercise-block" data-index="<?php echo $exerciseIndex; ?>">
                                <div class="exercise-top">
                                    <span class="exercise-num"><?php echo $exerciseIndex + 1; ?></span>
                                    <label class="visually-hidden" for="exercise-name-<?php echo $exerciseIndex; ?>">Упражнение</label>
                                    <select id="exercise-name-<?php echo $exerciseIndex; ?>" name="exercise_name[<?php echo $exerciseIndex; ?>]" class="exercise-name-select">
                                        <option value="">Выберите упражнение</option>
                                        <?php foreach ($exerciseCatalog as $item): ?>
                                            <option value="<?php echo htmlspecialchars((string) $item); ?>" <?php echo $item === $exerciseName ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $item); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="exercise-count"><?php echo count($exerciseSets); ?> <?php echo pluralRu(count($exerciseSets), 'подход', 'подхода', 'подходов'); ?></span>
                                    <button type="button" class="icon-btn remove-exercise-btn" aria-label="Удалить упражнение">✕</button>
                                </div>

                                <div class="set-list">
                                    <?php foreach ($exerciseSets as $setIndex => $set): ?>
                                        <div class="set-row" data-set-index="<?php echo $setIndex; ?>">
                                            <span class="set-num"><?php echo $setIndex + 1; ?></span>
                                            <label class="set-field">
                                                <span class="visually-hidden">Вес, кг</span>
                                                <input type="number" step="0.5" inputmode="decimal" placeholder="0" class="set-weight" name="exercise_sets[<?php echo $exerciseIndex; ?>][<?php echo $setIndex; ?>][weight]" value="<?php echo htmlspecialchars((string) ($set['weight'] ?? '')); ?>" />
                                                <span class="set-unit">кг</span>
                                            </label>
                                            <span class="set-times">×</span>
                                            <label class="set-field">
                                                <span class="visually-hidden">Повторы</span>
                                                <input type="number" min="1" inputmode="numeric" placeholder="0" class="set-reps" name="exercise_sets[<?php echo $exerciseIndex; ?>][<?php echo $setIndex; ?>][reps]" value="<?php echo htmlspecialchars((string) ($set['reps'] ?? '')); ?>" />
                                                <span class="set-unit">раз</span>
                                            </label>
                                            <button type="button" class="icon-btn remove-set-btn" aria-label="Удалить подход">✕</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="button" class="add-set-btn">+ подход</button>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" id="addExerciseBtn" class="add-exercise-btn">+ Добавить упражнение</button>

                    <details class="notes-details" <?php echo !empty($editing['notes']) ? 'open' : ''; ?>>
                        <summary>Заметка к тренировке</summary>
                        <textarea id="notes" name="notes" placeholder="Что важно запомнить после тренировки?"><?php echo htmlspecialchars((string) ($editing['notes'] ?? '')); ?></textarea>
                    </details>

                    <div class="actions form-actions">
                        <button type="submit" class="primary-btn"><?php echo $editing ? 'Сохранить изменения' : 'Сохранить тренировку'; ?></button>
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
                <span class="history-count"><?php echo $summaryTotal; ?></span>
            </div>

            <div class="workout-list">
                <?php if (empty($myWorkouts)): ?>
                    <p class="empty-state">Пока нет ни одной тренировки. Добавь первую и начни вести дневник.</p>
                <?php else: ?>
                    <?php foreach ($myWorkouts as $item): ?>
                        <?php
                        $itemExercises = $item['exercises'] ?? [];
                        $itemSets = 0;
                        foreach ($itemExercises as $exercise) {
                            $itemSets += count((array) ($exercise['sets'] ?? []));
                        }
                        $itemTimestamp = strtotime((string) ($item['date'] ?? ''));
                        $itemDate = $itemTimestamp ? date('d.m.Y', $itemTimestamp) : (string) ($item['date'] ?? '');
                        ?>
                        <details class="workout-item">
                            <summary class="workout-summary">
                                <span class="workout-date"><?php echo htmlspecialchars($itemDate); ?></span>
                                <span class="workout-meta"><?php echo count($itemExercises); ?> упр. · <?php echo $itemSets; ?> <?php echo pluralRu($itemSets, 'подход', 'подхода', 'подходов'); ?></span>
                                <span class="chevron" aria-hidden="true"></span>
                            </summary>

                            <div class="workout-body">
                                <?php if (!empty($item['notes'])): ?>
                                    <p class="workout-notes"><?php echo nl2br(htmlspecialchars((string) $item['notes'])); ?></p>
                                <?php endif; ?>

                                <div class="exercise-summary">
                                    <?php foreach ($itemExercises as $exercise): ?>
                                        <div class="exercise-summary-item">
                                            <div class="exercise-summary-header">
                                                <strong><?php echo htmlspecialchars((string) ($exercise['name'] ?? 'Упражнение')); ?></strong>
                                                <span><?php echo count((array) ($exercise['sets'] ?? [])); ?> подх.</span>
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
                                    <form method="post" action="dashboard.php" class="inline-form">
                                        <input type="hidden" name="action" value="delete_workout" />
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($item['id'] ?? '')); ?>" />
                                        <button type="submit" class="danger-btn">Удалить</button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<script>
    const exerciseCatalog = <?php echo json_encode($exerciseCatalog, JSON_UNESCAPED_UNICODE); ?>;
    const builder = document.getElementById('exerciseBuilder');

    function pluralRu(count, one, few, many) {
        const n10 = count % 10;
        const n100 = count % 100;

        if (n10 === 1 && n100 !== 11) return one;
        if (n10 >= 2 && n10 <= 4 && (n100 < 10 || n100 >= 20)) return few;

        return many;
    }

    function createExerciseOptionMarkup() {
        return ['<option value="">Выберите упражнение</option>']
            .concat(exerciseCatalog.map((exercise) => `<option value="${exercise}">${exercise}</option>`))
            .join('');
    }

    function updateSetCount(block) {
        const count = block.querySelectorAll('.set-row').length;
        block.querySelector('.exercise-count').textContent = `${count} ${pluralRu(count, 'подход', 'подхода', 'подходов')}`;
    }

    function renumber() {
        builder.querySelectorAll('.exercise-block').forEach((block, exerciseIndex) => {
            block.dataset.index = String(exerciseIndex);
            block.querySelector('.exercise-num').textContent = String(exerciseIndex + 1);
            block.querySelector('.exercise-name-select').name = `exercise_name[${exerciseIndex}]`;

            block.querySelectorAll('.set-row').forEach((row, setIndex) => {
                row.dataset.setIndex = String(setIndex);
                row.querySelector('.set-num').textContent = String(setIndex + 1);
                row.querySelector('.set-weight').name = `exercise_sets[${exerciseIndex}][${setIndex}][weight]`;
                row.querySelector('.set-reps').name = `exercise_sets[${exerciseIndex}][${setIndex}][reps]`;
            });

            updateSetCount(block);
        });
    }

    function createSetRow() {
        const row = document.createElement('div');
        row.className = 'set-row';
        row.innerHTML = `
            <span class="set-num">1</span>
            <label class="set-field">
                <span class="visually-hidden">Вес, кг</span>
                <input type="number" step="0.5" inputmode="decimal" placeholder="0" class="set-weight" />
                <span class="set-unit">кг</span>
            </label>
            <span class="set-times">×</span>
            <label class="set-field">
                <span class="visually-hidden">Повторы</span>
                <input type="number" min="1" inputmode="numeric" placeholder="0" class="set-reps" />
                <span class="set-unit">раз</span>
            </label>
            <button type="button" class="icon-btn remove-set-btn" aria-label="Удалить подход">✕</button>
        `;

        row.querySelector('.remove-set-btn').addEventListener('click', () => removeSetRow(row));

        return row;
    }

    function addSetRow(block) {
        block.querySelector('.set-list').appendChild(createSetRow());
        renumber();
        block.querySelector('.set-row:last-child input')?.focus();
    }

    function removeSetRow(row) {
        const block = row.closest('.exercise-block');

        if (block.querySelectorAll('.set-row').length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach((input) => { input.value = ''; });
        }

        renumber();
    }

    function createExerciseBlock() {
        const block = document.createElement('article');
        block.className = 'exercise-block';
        block.innerHTML = `
            <div class="exercise-top">
                <span class="exercise-num">1</span>
                <label class="visually-hidden">Упражнение</label>
                <select class="exercise-name-select">
                    ${createExerciseOptionMarkup()}
                </select>
                <span class="exercise-count">1 подход</span>
                <button type="button" class="icon-btn remove-exercise-btn" aria-label="Удалить упражнение">✕</button>
            </div>
            <div class="set-list"></div>
            <button type="button" class="add-set-btn">+ подход</button>
        `;

        block.querySelector('.set-list').appendChild(createSetRow());
        block.querySelector('.add-set-btn').addEventListener('click', () => addSetRow(block));
        block.querySelector('.remove-exercise-btn').addEventListener('click', () => removeExerciseBlock(block));

        return block;
    }

    function addExerciseBlock() {
        const block = createExerciseBlock();
        builder.appendChild(block);
        renumber();
        block.querySelector('.exercise-name-select').focus();
    }

    function removeExerciseBlock(block) {
        if (builder.querySelectorAll('.exercise-block').length > 1) {
            block.remove();
        } else {
            block.querySelector('.exercise-name-select').value = '';
            block.querySelectorAll('.set-row').forEach((row) => {
                row.querySelectorAll('input').forEach((input) => { input.value = ''; });
            });
        }

        renumber();
    }

    builder.querySelectorAll('.exercise-block').forEach((block) => {
        block.querySelector('.add-set-btn').addEventListener('click', () => addSetRow(block));
        block.querySelector('.remove-exercise-btn').addEventListener('click', () => removeExerciseBlock(block));
        block.querySelectorAll('.remove-set-btn').forEach((button) => {
            button.addEventListener('click', () => removeSetRow(button.closest('.set-row')));
        });
    });

    document.getElementById('addExerciseBtn').addEventListener('click', addExerciseBlock);
</script>
</body>
</html>
