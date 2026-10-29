<?php

/**
 * Общие элементы интерфейса личного кабинета.
 */

function renderTopbar(array $user): void
{
    ?>
    <header class="topbar">
        <div class="brand-mini">
            <div class="badge">F</div>
            <span>FitLog</span>
        </div>

        <div class="user-block">
            <div class="avatar"><?php echo strtoupper(substr((string) $user['name'], 0, 1)); ?></div>
            <div class="user-meta">
                <div><strong><?php echo htmlspecialchars((string) $user['name']); ?></strong></div>
                <small><?php echo htmlspecialchars((string) $user['email']); ?></small>
            </div>
            <a href="logout.php" class="logout-link" title="Выйти из аккаунта">Выйти</a>
        </div>
    </header>
    <?php
}

function renderNav(string $active): void
{
    $items = [
        'dashboard' => [
            'href' => 'dashboard.php',
            'label' => 'Тренировка',
            'icon' => '<path d="M4 9v6M7 7v10M17 7v10M20 9v6M7 12h10"/>',
        ],
        'history' => [
            'href' => 'history.php',
            'label' => 'История',
            'icon' => '<path d="M4 19v-6M10 19V5M16 19v-7M22 19H2"/>',
        ],
    ];
    ?>
    <nav class="main-nav" aria-label="Разделы дневника">
        <?php foreach ($items as $key => $item): ?>
            <a href="<?php echo $item['href']; ?>"
               class="nav-link<?php echo $key === $active ? ' is-active' : ''; ?>"
               <?php echo $key === $active ? 'aria-current="page"' : ''; ?>>
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <?php echo $item['icon']; ?>
                </svg>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function renderFlash(?array $flash): void
{
    if (!$flash) {
        return;
    }
    ?>
    <p class="message <?php echo $flash['type'] === 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars((string) $flash['message']); ?></p>
    <?php
}
