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
    <title><?= isset($title) ? htmlspecialchars($title) . ' — ' : '' ?>Convota Access</title>
    <link rel="icon" type="image/svg+xml" href="<?= url('/favicon.svg') ?>">
    <link rel="icon" type="image/x-icon" href="<?= url('/favicon.ico') ?>">
    <link rel="apple-touch-icon" href="<?= url('/apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            min-height: 100vh;
            padding: 1rem;
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        .auth-header {
            background: #f8fafc;
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        .auth-body {
            padding: 1.75rem;
        }
        .auth-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }
        .auth-tab-btn {
            flex: 1;
            padding: 0.75rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            text-align: center;
        }
        .auth-tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: rgba(30, 58, 138, 0.04);
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <span style="font-size: 2.25rem;">🗳️</span>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-top: 0.25rem;">Convota</h1>
            <p style="font-size: 0.85rem; color: var(--text-muted);">Voting Together — General Assembly Portal</p>
        </div>
        <div class="auth-body">
            <?php if (!empty($flash)): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </div>

    <div style="text-align: center; margin-top: 1rem;">
        <a href="javascript:void(0)" onclick="ConvotaAbout.open()" style="color: #cbd5e1; font-size: 0.85rem; text-decoration: underline; cursor: pointer;">About Convota</a>
    </div>

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

    <script nonce="<?= \Convota\Security\SecurityHeaders::getNonce() ?>" src="<?= url('/assets/js/app.js') ?>"></script>
</body>

</html>
