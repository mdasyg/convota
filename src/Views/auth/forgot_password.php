<?php
use Convota\Security\Csrf;
?>
<h2 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 0.5rem;">Reset Your Password</h2>
<p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
    Enter your registered member email and we will send you a secure link to reset your password.
</p>

<form action="<?= url('/auth/forgot-password') ?>" method="POST">
    <?= Csrf::field() ?>

    <div class="form-group">
        <label class="form-label" for="email">Account Email</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="delegate@email.vlsi.gr" required autofocus>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
        Send Password Reset Link
    </button>

    <div style="text-align: center; margin-top: 1.25rem;">
        <a href="<?= url('/login') ?>" style="font-size: 0.85rem;">← Back to Sign In</a>
    </div>
</form>

