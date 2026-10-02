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
    <link rel="icon" type="image/svg+xml" href="<?= url('/favicon.svg') ?>">
    <link rel="icon" type="image/x-icon" href="<?= url('/favicon.ico') ?>">
    <link rel="apple-touch-icon" href="<?= url('/apple-touch-icon.png') ?>">
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
                    <li><a href="<?= url('/csv') ?>" class="nav-link">CSV</a></li>
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
        <p><a href="javascript:void(0)" onclick="ConvotaAbout.open()" style="font-weight: 700; color: var(--primary); text-decoration: underline; cursor: pointer;">Convota</a> — Secure General Assembly Presence & Voting System | Powered by PHP 8.4 & SQLite WAL Mode</p>
    </footer>

    <!-- Convota About Modal -->
    <div id="convota-about-modal" class="convota-modal" style="display: none;">
        <div class="convota-modal-backdrop" onclick="ConvotaAbout.close()"></div>
        <div class="convota-modal-content">
            <div class="convota-modal-header">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 2rem;">🗳️</span>
                    <div>
                        <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--primary); margin: 0;">Convota</h2>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">Voting Together — Assembly Presence & Decision Platform</p>
                    </div>
                </div>
                <button type="button" class="convota-modal-close" onclick="ConvotaAbout.close()" title="Close">&times;</button>
            </div>
            <div class="convota-modal-body">
                <div style="margin-bottom: 1.25rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-bottom: 0.35rem;">What is Convota?</h4>
                    <p style="font-size: 0.88rem; line-height: 1.5; color: #334155; margin: 0;">
                        Convota is a secure web-based information and decision-making system designed for general assembly presence verification, dynamic quorum calculation, and cryptographic voting in member-driven collectives, federations, associations, research networks, and NGOs.
                    </p>
                </div>
                <div style="margin-bottom: 1.25rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-bottom: 0.35rem;">Etymology & Name Origin</h4>
                    <p style="font-size: 0.88rem; line-height: 1.5; color: #334155; margin: 0;">
                        The name <strong>Convota</strong> originates from the Latin prefix <em>con-</em> (signifying <em>together</em> or <em>collectively</em>) and <em>vota</em> (plural of <em>votum</em>, meaning <em>votes</em>, <em>resolutions</em>, or <em>shared aspirations</em>).<br>
                        <strong>Convota = Voting Together.</strong>
                    </p>
                </div>
                <div style="background: #f8fafc; border-left: 4px solid var(--primary); padding: 0.85rem 1rem; border-radius: 4px; margin-bottom: 1.25rem;">
                    <p style="font-size: 0.88rem; font-weight: 600; color: #0f172a; margin: 0;">
                        System Architecture and Implementation by Minas Dasygenis for my friend Aris Repoulias.
                    </p>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; color: #64748b; border-top: 1px solid var(--border); padding-top: 0.85rem;">
                    <span><strong>Date:</strong> 2026-10-02</span>
                    <span style="font-family: monospace; font-weight: 600; background: #e2e8f0; padding: 2px 8px; border-radius: 4px; color: #1e293b;">Version: 20261002</span>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= url('/assets/js/app.js') ?>"></script>

</body>
</html>
