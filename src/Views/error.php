<div class="card" style="max-width: 600px; margin: 3rem auto; text-align: center; padding: 2rem;">
    <h1 style="font-size: 3rem; color: var(--danger); margin-bottom: 0.5rem;"><?= htmlspecialchars((string)($code ?? 500)) ?></h1>
    <h2 style="font-size: 1.25rem; margin-bottom: 1rem; color: var(--text-main);">Error Occurred</h2>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
        <?= nl2br(htmlspecialchars($message ?? 'An unexpected error occurred.')) ?>
    </p>
    <div>
        <a href="/dashboard" class="btn btn-primary">Return to Dashboard</a>
        <a href="/login" class="btn btn-secondary">Sign In</a>
    </div>
</div>
