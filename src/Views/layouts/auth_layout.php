<?php
use Convota\Security\Csrf;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::getToken(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= isset($title) ? htmlspecialchars($title) . ' — ' : '' ?>Convota Access</title>
    <link rel="stylesheet" href="/assets/css/app.css">
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
    <script src="/assets/js/app.js"></script>
</body>
</html>
