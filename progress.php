<?php
require __DIR__ . '/storage.php';
require __DIR__ . '/workouts.php';
require __DIR__ . '/layout.php';

requireAuth();

$user = currentUser();
$myWorkouts = loadUserWorkouts((string) $user['email']);

// Упражнения, которые пользователь реально выполнял: имя => число тренировок с ним.
$performed = [];
foreach ($myWorkouts as $workout) {
    foreach (($workout['exercises'] ?? []) as $exercise) {
        $name = trim((string) ($exercise['name'] ?? ''));
        if ($name !== '') {
            $performed[$name] = ($performed[$name] ?? 0) + 1;
        }
    }
}

uksort($performed, function ($a, $b) use ($performed) {
    $byCount = $performed[$b] <=> $performed[$a];
    return $byCount !== 0 ? $byCount : strcmp($a, $b);
});

$requested = trim((string) ($_GET['exercise'] ?? ''));
if ($requested === '' || !isset($performed[$requested])) {
    $requested = $performed !== [] ? (string) array_key_first($performed) : '';
}

$periods = [
    7 => 'Неделя',
    30 => 'Месяц',
    90 => '3 месяца',
    0 => 'Всё время',
];
$period = isset($_GET['period']) ? (int) $_GET['period'] : 0;
if (!array_key_exists($period, $periods)) {
    $period = 0;
}

// Точки прогресса: каждая тренировка с выбранным упражнением внутри периода.
$cutoff = $period > 0 ? date('Y-m-d', strtotime('-' . $period . ' days')) : null;
$series = [];

foreach (array_reverse($myWorkouts) as $workout) {
    $workoutDate = (string) ($workout['date'] ?? '');

    if ($cutoff !== null && $workoutDate !== '' && $workoutDate < $cutoff) {
        continue;
    }

    $maxWeight = 0.0;

    foreach (($workout['exercises'] ?? []) as $exercise) {
        if (trim((string) ($exercise['name'] ?? '')) !== $requested) {
            continue;
        }

        foreach (($exercise['sets'] ?? []) as $set) {
            $maxWeight = max($maxWeight, (float) ($set['weight'] ?? 0));
        }
    }

    if ($maxWeight > 0.0) {
        $series[] = [
            'label' => $workoutDate !== '' ? date('d.m', strtotime($workoutDate)) : '',
            'max' => $maxWeight,
        ];
    }
}

function renderWeightChart(array $series): string
{
    $count = count($series);
    $width = 320;
    $height = 180;
    $padLeft = 34;
    $padRight = 12;
    $padTop = 14;
    $padBottom = 26;
    $plotW = $width - $padLeft - $padRight;
    $plotH = $height - $padTop - $padBottom;

    $values = array_column($series, 'max');
    $min = min($values);
    $max = max($values);
    if ($min === $max) {
        $max = $min + 1;
    }

    $rangePad = ($max - $min) * 0.18;
    $yMin = max(0.0, $min - $rangePad);
    $yMax = $max + $rangePad;
    $yMin = floor($yMin);
    $yMax = ceil($yMax);

    $normY = function ($value) use ($padTop, $plotH, $yMin, $yMax) {
        return $padTop + (($yMax - $value) / ($yMax - $yMin)) * $plotH;
    };
    $xPos = function ($i) use ($count, $padLeft, $plotW) {
        return $count === 1 ? $padLeft + $plotW / 2 : $padLeft + ($i / ($count - 1)) * $plotW;
    };

    $svg = '';

    $gridLines = 4;
    for ($g = 0; $g <= $gridLines; $g++) {
        $value = $yMin + ($yMax - $yMin) * ($g / $gridLines);
        $y = $padTop + $plotH - ($plotH * $g / $gridLines);
        $svg .= sprintf('<line class="grid" x1="%s" y1="%s" x2="%s" y2="%s" />', $padLeft, $y, $width - $padRight, $y) . "\n";
        $svg .= sprintf('<text class="y-l" x="%s" y="%s">%s</text>', $padLeft - 7, $y + 3.5, formatNumber(round($value))) . "\n";
    }

    $labelIndexes = $count === 1 ? [0] : array_values(array_unique([0, intdiv($count - 1, 2), $count - 1]));
    foreach ($labelIndexes as $i) {
        $svg .= sprintf('<text class="x-l" x="%s" y="%s">%s</text>', $xPos($i), $height - 8, htmlspecialchars($series[$i]['label'])) . "\n";
    }

    $points = [];
    $dotMarkup = '';
    $showValues = $count <= 8;

    foreach ($series as $i => $item) {
        $x = $xPos($i);
        $y = $normY($item['max']);
        $points[] = round($x, 1) . ',' . round($y, 1);
        $dotMarkup .= sprintf(
            '<circle class="chart-dot" cx="%s" cy="%s" r="3.4"><title>%s — %s кг</title></circle>',
            round($x, 1),
            round($y, 1),
            htmlspecialchars($item['label']),
            formatNumber($item['max'])
        ) . "\n";
        if ($showValues) {
            $dotMarkup .= sprintf('<text class="chart-val" x="%s" y="%s">%s</text>', round($x, 1), round($y - 7, 1), formatNumber($item['max'])) . "\n";
        }
    }

    if ($count > 1) {
        $svg .= sprintf('<polyline class="chart-line" points="%s" />', implode(' ', $points)) . "\n";
    }

    $svg .= $dotMarkup;

    return $svg;
}

$bestWeight = $series ? max(array_column($series, 'max')) : 0.0;
$lastWeight = $series ? $series[count($series) - 1]['max'] : 0.0;
$change = $series ? $lastWeight - $series[0]['max'] : 0.0;
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="theme-color" content="#f4f7fb" />
    <title>FitLog — Прогресс по упражнению</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body class="dashboard-page">
<div class="dashboard-layout">
    <?php renderTopbar($user); ?>
    <?php renderNav('progress'); ?>
    <?php renderFlash($flash); ?>

    <main class="page-single">
        <form method="get" action="progress.php" class="panel control-panel">
            <input type="hidden" name="period" value="<?php echo $period; ?>" />

            <div class="field">
                <label for="exercise">Упражнение</label>
                <select id="exercise" name="exercise" onchange="this.form.submit()">
                    <?php foreach (array_keys($performed) as $name): ?>
                        <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $name === $requested ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="segmented" role="group" aria-label="Период">
                <?php foreach ($periods as $periodKey => $periodLabel): ?>
                    <a href="progress.php?exercise=<?php echo rawurlencode($requested); ?>&amp;period=<?php echo $periodKey; ?>"
                       class="seg-btn<?php echo $periodKey === $period ? ' is-active' : ''; ?>"
                       <?php echo $periodKey === $period ? 'aria-current="true"' : ''; ?>>
                        <?php echo $periodLabel; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </form>

        <?php if ($performed === []): ?>
            <section class="panel">
                <div class="empty-state">
                    <p>Пока нет ни одной тренировки. Добавь первую и начни отслеживать прогресс.</p>
                    <a href="dashboard.php" class="primary-btn">Добавить тренировку</a>
                </div>
            </section>
        <?php else: ?>
            <section class="panel chart-panel">
                <div class="section-header">
                    <h2><?php echo htmlspecialchars($requested); ?></h2>
                    <span class="history-count">макс. вес</span>
                </div>

                <?php if ($series === []): ?>
                    <div class="empty-state">
                        <p>Нет тренировок с этим упражнением в выбранном периоде.</p>
                    </div>
                <?php else: ?>
                    <div class="stats-grid progress-stats">
                        <div class="stat-card">
                            <small>Лучший вес</small>
                            <strong><?php echo formatNumber($bestWeight); ?> <span class="stat-unit">кг</span></strong>
                        </div>
                        <div class="stat-card">
                            <small>Последний</small>
                            <strong><?php echo formatNumber($lastWeight); ?> <span class="stat-unit">кг</span></strong>
                        </div>
                        <div class="stat-card">
                            <small>Изменение</small>
                            <strong class="<?php echo $change < 0 ? 'is-negative' : ''; ?>">
                                <?php echo $change > 0 ? '+' : ''; ?><?php echo formatNumber($change); ?> <span class="stat-unit">кг</span>
                            </strong>
                        </div>
                        <div class="stat-card">
                            <small>Тренировок в периоде</small>
                            <strong><?php echo count($series); ?></strong>
                        </div>
                    </div>

                    <figure class="chart-box">
                        <svg class="chart-svg" viewBox="0 0 320 180" preserveAspectRatio="xMidYMid meet" role="img"
                             aria-label="График максимального веса упражнения «<?php echo htmlspecialchars($requested); ?>»">
                            <?php echo renderWeightChart($series); ?>
                        </svg>
                    </figure>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>