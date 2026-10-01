<?php
use Convota\Security\Csrf;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::getToken(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="base-url" content="<?= htmlspecialchars(url('/'), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= isset($title) ? htmlspecialchars($title) . ' — ' : '' ?>Convota</title>
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
</head>
<body>
    <header class="navbar">
        <a href="<?= url('/dashboard') ?>" class="navbar-brand">
            <span style="font-size: 1.5rem;">🗳️</span>
            <span>Convota</span>
        </a>

        <nav>
            <ul class="navbar-nav">
                <li><a href="<?= url('/dashboard') ?>" class="nav-link">Dashboard</a></li>
                
                <?php if (!empty($currentUser) && in_array($currentUser['system_role'], ['admin', 'president'], true)): ?>
                    <li><a href="<?= url('/organizations') ?>" class="nav-link">Organizations</a></li>
                    <li><a href="<?= url('/meetings') ?>" class="nav-link">Meetings</a></li>
                    <li><a href="<?= url('/proposals') ?>" class="nav-link">Proposals</a></li>
                    <li><a href="<?= url('/csv/import') ?>" class="nav-link">CSV Import</a></li>
                    <?php if ($currentUser['system_role'] === 'admin'): ?>
                        <li><a href="<?= url('/audit') ?>" class="nav-link">Audit Log</a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($currentUser['organization_id']) || !empty($orgAuth['organization_id'])): ?>
                    <li><a href="<?= url('/organizations/portal') ?>" class="nav-link">Delegation Portal</a></li>
                <?php endif; ?>

                <li><a href="<?= url('/proposals/verify-receipt') ?>" class="nav-link">Verify Ballot</a></li>

                <?php if (!empty($currentUser)): ?>
                    <li class="nav-user">
                        <span>👤 <?= htmlspecialchars($currentUser['name']) ?></span>
                        <span class="badge badge-primary"><?= htmlspecialchars(strtoupper($currentUser['system_role'])) ?></span>
                    </li>
                    <li><a href="<?= url('/logout') ?>" class="btn btn-secondary btn-sm">Sign Out</a></li>
                <?php elseif (!empty($orgAuth)): ?>
                    <li class="nav-user">
                        <span>🔑 <?= htmlspecialchars($orgAuth['organization_name']) ?></span>
                        <span class="badge badge-info">SHARED KEY</span>
                    </li>
                    <li><a href="<?= url('/logout') ?>" class="btn btn-secondary btn-sm">Exit Key</a></li>
                <?php else: ?>
                    <li><a href="<?= url('/login') ?>" class="btn btn-primary btn-sm">Sign In</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="container">
        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="footer">
        <p><strong>Convota</strong> — Secure General Assembly Presence & Voting System | Powered by PHP 8.4 & SQLite WAL Mode</p>
    </footer>

    <script src="<?= url('/assets/js/app.js') ?>"></script>

</body>
</html>
