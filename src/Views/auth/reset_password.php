<?php
use Convota\Security\Csrf;
?>
<h2 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 0.5rem;">Choose a New Password</h2>
<p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
    Please enter and confirm your new secure password below (minimum 8 characters).
</p>

<form action="/auth/reset-password" method="POST">
    <?= Csrf::field() ?>
    <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
        <label class="form-label" for="password">New Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" minlength="8" required autofocus>
    </div>

    <div class="form-group">
        <label class="form-label" for="password_confirm">Confirm New Password</label>
        <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="••••••••" minlength="8" required>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
        Update Password
    </button>
</form>
