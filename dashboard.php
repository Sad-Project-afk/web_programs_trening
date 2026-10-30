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
                                <button type="button" class="ex-collapse" aria-expanded="true" aria-label="Свернуть упражнение">
                                    <svg class="ex-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="ex-search">
                                    <label class="visually-hidden" for="exercise-name-<?php echo $exerciseIndex; ?>">Упражнение</label>
                                    <svg class="ex-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                    <input id="exercise-name-<?php echo $exerciseIndex; ?>" name="exercise_name[<?php echo $exerciseIndex; ?>]" type="text" class="ex-search-input" role="combobox" aria-expanded="false" aria-controls="ex-list-<?php echo $exerciseIndex; ?>" autocomplete="off" placeholder="Поиск упражнения…" value="<?php echo htmlspecialchars((string) $exerciseName); ?>" />
                                    <ul id="ex-list-<?php echo $exerciseIndex; ?>" class="ex-search-list" role="listbox" hidden></ul>
                                </div>
                                <span class="exercise-count"><?php echo count($exerciseSets); ?> <?php echo pluralRu(count($exerciseSets), 'подход', 'подхода', 'подходов'); ?></span>
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

                            <div class="exercise-actions">
                                <button type="button" class="add-set-btn">+ подход</button>
                                <button type="button" class="remove-exercise-btn">Удалить упражнение</button>
                            </div>
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

<script src="exercise-search.js"></script>
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

    function updateSetCount(block) {
        const count = block.querySelectorAll('.set-row').length;
        block.querySelector('.exercise-count').textContent = `${count} ${pluralRu(count, 'подход', 'подхода', 'подходов')}`;
    }

    function setCollapsed(block, collapsed) {
        block.classList.toggle('is-collapsed', collapsed);
        const button = block.querySelector('.ex-collapse');
        if (button) {
            button.setAttribute('aria-expanded', String(!collapsed));
            button.setAttribute('aria-label', collapsed ? 'Развернуть упражнение' : 'Свернуть упражнение');
        }
    }

    function expandOnly(block) {
        builder.querySelectorAll('.exercise-block').forEach((other) => {
            setCollapsed(other, other !== block);
        });
    }

    function renumber() {
        builder.querySelectorAll('.exercise-block').forEach((block, exerciseIndex) => {
            block.dataset.index = String(exerciseIndex);

            const input = block.querySelector('.ex-search-input');
            input.name = `exercise_name[${exerciseIndex}]`;
            input.id = `exercise-name-${exerciseIndex}`;
            input.setAttribute('aria-controls', `ex-list-${exerciseIndex}`);
            block.querySelector('.ex-search-list').id = `ex-list-${exerciseIndex}`;
            block.querySelector('.ex-search > label').htmlFor = input.id;

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
        expandOnly(block);
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
                <button type="button" class="ex-collapse" aria-expanded="true" aria-label="Свернуть упражнение">
                    <svg class="ex-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="ex-search">
                    <label class="visually-hidden">Упражнение</label>
                    <svg class="ex-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="text" class="ex-search-input" role="combobox" aria-expanded="false" aria-controls="" autocomplete="off" placeholder="Поиск упражнения…" />
                    <ul class="ex-search-list" role="listbox" hidden></ul>
                </div>
                <span class="exercise-count">1 подход</span>
            </div>
            <div class="set-list"></div>
            <div class="exercise-actions">
                <button type="button" class="add-set-btn">+ подход</button>
                <button type="button" class="remove-exercise-btn">Удалить упражнение</button>
            </div>
        `;

        block.querySelector('.set-list').appendChild(createSetRow());
        block.querySelector('.add-set-btn').addEventListener('click', () => addSetRow(block));
        block.querySelector('.remove-exercise-btn').addEventListener('click', () => removeExerciseBlock(block));
        block.querySelector('.ex-collapse').addEventListener('click', () => {
            if (block.classList.contains('is-collapsed')) {
                expandOnly(block);
            } else {
                setCollapsed(block, true);
            }
        });
        setupExerciseSearch(block.querySelector('.ex-search'), exerciseCatalog);

        return block;
    }

    function addExerciseBlock() {
        const block = createExerciseBlock();
        builder.appendChild(block);
        expandOnly(block);
        renumber();
        block.querySelector('.ex-search-input').focus();
        builder.scrollTop = builder.scrollHeight;
    }

    function removeExerciseBlock(block) {
        if (builder.querySelectorAll('.exercise-block').length > 1) {
            block.remove();
        } else {
            block.querySelector('.ex-search-input').value = '';
            block.querySelectorAll('.set-row').forEach((row) => {
                row.querySelectorAll('input').forEach((input) => { input.value = ''; });
            });
        }

        renumber();
    }

    builder.querySelectorAll('.exercise-block').forEach((block, index) => {
        block.querySelector('.add-set-btn').addEventListener('click', () => addSetRow(block));
        block.querySelector('.remove-exercise-btn').addEventListener('click', () => removeExerciseBlock(block));
        block.querySelector('.ex-collapse').addEventListener('click', () => {
            if (block.classList.contains('is-collapsed')) {
                expandOnly(block);
            } else {
                setCollapsed(block, true);
            }
        });
        block.querySelectorAll('.remove-set-btn').forEach((button) => {
            button.addEventListener('click', () => removeSetRow(button.closest('.set-row')));
        });
        setupExerciseSearch(block.querySelector('.ex-search'), exerciseCatalog);
        setCollapsed(block, index > 0);
    });

    function fitBuilderHeight() {
        const builderEl = document.getElementById('exerciseBuilder');

        if (!builderEl) return;

        const layout = document.querySelector('.dashboard-layout');
        const layoutPadBottom = layout ? parseFloat(getComputedStyle(layout).paddingBottom) || 0 : 0;
        const panel = builderEl.closest('.panel');
        const panelPadBottom = panel ? parseFloat(getComputedStyle(panel).paddingBottom) || 0 : 0;

        // Высота, занимаемая всем, что ниже построителя (упражнения), — она не зависит
        // от высоты самого построителя.
        const builderTop = builderEl.getBoundingClientRect().top;
        let lastBottom = builderEl.getBoundingClientRect().bottom;
        let node = builderEl.nextElementSibling;

        while (node) {
            lastBottom = node.getBoundingClientRect().bottom;
            node = node.nextElementSibling;
        }

        const belowFixed = (lastBottom - builderEl.getBoundingClientRect().bottom) + panelPadBottom;
        const maxHeight = Math.max(80, window.innerHeight - builderTop - belowFixed - layoutPadBottom);
        builderEl.style.maxHeight = `${maxHeight}px`;
    }

    window.addEventListener('resize', fitBuilderHeight);
    window.addEventListener('load', fitBuilderHeight);
    fitBuilderHeight();

    document.getElementById('addExerciseBtn').addEventListener('click', addExerciseBlock);
</script>
</body>
</html>
