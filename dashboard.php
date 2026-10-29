<?php
require __DIR__ . '/storage.php';
require __DIR__ . '/workouts.php';
require __DIR__ . '/layout.php';

requireAuth();

$user = currentUser();
handleWorkoutActions((string) $user['email']);

$myWorkouts = loadUserWorkouts((string) $user['email']);
$exerciseCatalog = readJson(EXERCISES_FILE);
$editId = (string) ($_GET['edit'] ?? '');
$editing = $editId !== '' ? findWorkout($myWorkouts, $editId) : null;
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#f4f7fb" />
    <title>FitLog — <?php echo $editing ? 'Редактирование тренировки' : 'Новая тренировка'; ?></title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body class="dashboard-page">
<div class="dashboard-layout">
    <?php renderTopbar($user); ?>
    <?php renderNav('dashboard'); ?>
    <?php renderFlash($flash); ?>

    <main class="page-single">
        <section class="panel">
            <div class="section-header">
                <h2><?php echo $editing ? 'Редактировать тренировку' : 'Новая тренировка'; ?></h2>
            </div>

            <p class="page-hint">
                <?php if ($editing): ?>
                    Измени упражнения или подходы и сохрани. Дата создания тренировки останется прежней.
                <?php else: ?>
                    Выбери упражнение, укажи вес и повторы. Новый подход добавляется кнопкой «+ подход».
                <?php endif; ?>
            </p>

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
                    <a href="history.php" class="secondary-btn"><?php echo $editing ? 'Отмена' : 'К истории'; ?></a>
                </div>
            </form>
        </section>
    </main>
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
