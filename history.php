<?php
require __DIR__ . '/storage.php';
require __DIR__ . '/workouts.php';
require __DIR__ . '/layout.php';

requireAuth();

$user = currentUser();
handleWorkoutActions((string) $user['email']);

$myWorkouts = loadUserWorkouts((string) $user['email']);
$stats = workoutStats($myWorkouts);
$flash = getFlash();
$highlight = (string) ($_GET['highlight'] ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#f4f7fb" />
    <title>FitLog — История тренировок</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body class="dashboard-page">
<div class="dashboard-layout">
    <?php renderTopbar($user); ?>
    <?php renderNav('history'); ?>
    <?php renderFlash($flash); ?>

    <div class="history-layout">
        <section class="panel stats-panel">
            <div class="section-header">
                <h2>Обзор</h2>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <small>Тренировки</small>
                    <strong><?php echo $stats['total']; ?></strong>
                </div>
                <div class="stat-card">
                    <small>Подходы</small>
                    <strong><?php echo $stats['sets']; ?></strong>
                </div>
                <div class="stat-card">
                    <small>Средний вес</small>
                    <strong><?php echo formatNumber((float) $stats['avgWeight']); ?> <span class="stat-unit">кг</span></strong>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="section-header">
                <h2>История</h2>
                <span class="history-count"><?php echo $stats['total']; ?></span>
            </div>

            <div class="workout-list">
                <?php if (empty($myWorkouts)): ?>
                    <div class="empty-state">
                        <p>Пока нет ни одной тренировки. Добавь первую и начни вести дневник.</p>
                        <a href="dashboard.php" class="primary-btn">Добавить тренировку</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($myWorkouts as $item): ?>
                        <?php
                        $itemId = (string) ($item['id'] ?? '');
                        $itemExercises = $item['exercises'] ?? [];
                        $itemSummary = workoutSummary($item);
                        $itemTimestamp = strtotime((string) ($item['date'] ?? ''));
                        $itemDate = $itemTimestamp ? date('d.m.Y', $itemTimestamp) : (string) ($item['date'] ?? '');
                        $isHighlighted = $highlight !== '' && $highlight === $itemId;
                        ?>
                        <details class="workout-item<?php echo $isHighlighted ? ' is-highlighted' : ''; ?>" id="<?php echo htmlspecialchars($itemId); ?>" <?php echo $isHighlighted ? 'open' : ''; ?>>
                            <summary class="workout-summary">
                                <span class="workout-date"><?php echo htmlspecialchars($itemDate); ?></span>
                                <span class="workout-meta"><?php echo $itemSummary['exercises']; ?> упр. · <?php echo $itemSummary['sets']; ?> <?php echo pluralRu($itemSummary['sets'], 'подход', 'подхода', 'подходов'); ?></span>
                                <span class="chevron" aria-hidden="true"></span>
                            </summary>

                            <div class="workout-body">
                                <div class="workout-stats">
                                    <div>
                                        <small>Упражнений</small>
                                        <strong><?php echo $itemSummary['exercises']; ?></strong>
                                    </div>
                                    <div>
                                        <small>Подходов</small>
                                        <strong><?php echo $itemSummary['sets']; ?></strong>
                                    </div>
                                    <div>
                                        <small>Объём</small>
                                        <strong><?php echo formatNumber((float) $itemSummary['volume']); ?> <span class="stat-unit">кг</span></strong>
                                    </div>
                                    <div>
                                        <small>Макс. вес</small>
                                        <strong><?php echo formatNumber((float) $itemSummary['maxWeight']); ?> <span class="stat-unit">кг</span></strong>
                                    </div>
                                </div>

                                <?php if (!empty($item['notes'])): ?>
                                    <p class="workout-notes"><?php echo nl2br(htmlspecialchars((string) $item['notes'])); ?></p>
                                <?php endif; ?>

                                <div class="exercise-summary">
                                    <?php foreach ($itemExercises as $exercise): ?>
                                        <div class="exercise-summary-item">
                                            <div class="exercise-summary-header">
                                                <strong><?php echo htmlspecialchars((string) ($exercise['name'] ?? 'Упражнение')); ?></strong>
                                                <span><?php echo count((array) ($exercise['sets'] ?? [])); ?> <?php echo pluralRu(count((array) ($exercise['sets'] ?? [])), 'подход', 'подхода', 'подходов'); ?></span>
                                            </div>

                                            <div class="set-summary-list">
                                                <?php foreach (($exercise['sets'] ?? []) as $set): ?>
                                                    <span><?php echo formatNumber((float) ($set['weight'] ?? 0)); ?> кг × <?php echo (int) ($set['reps'] ?? 0); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="actions">
                                    <a class="secondary-btn" href="dashboard.php?edit=<?php echo rawurlencode($itemId); ?>">Редактировать</a>
                                    <form method="post" action="history.php" class="inline-form" onsubmit="return confirm('Удалить эту тренировку?');">
                                        <input type="hidden" name="action" value="delete_workout" />
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($itemId); ?>" />
                                        <button type="submit" class="danger-btn">Удалить</button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
    const highlightedWorkout = document.querySelector('.workout-item.is-highlighted');

    if (highlightedWorkout) {
        highlightedWorkout.scrollIntoView({ block: 'center' });
    }

    // Аккордеон: при раскрытии одной тренировки закрываем остальные.
    document.querySelectorAll('details.workout-item').forEach((card) => {
        card.addEventListener('toggle', () => {
            if (!card.open) {
                return;
            }

            document.querySelectorAll('details.workout-item').forEach((other) => {
                if (other !== card) {
                    other.open = false;
                }
            });
        });
    });
</script>
</body>
</html>
