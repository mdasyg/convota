<?php
use Convota\Security\Csrf;
?>
<div class="auth-tabs">
    <button type="button" class="auth-tab-btn <?= ($activeTab ?? 'member') === 'member' ? 'active' : '' ?>" data-tab-target="tab-member">
        👤 Member Login
    </button>
    <button type="button" class="auth-tab-btn <?= ($activeTab ?? '') === 'hash' ? 'active' : '' ?>" data-tab-target="tab-hash">
        🔑 Shared Key
    </button>
</div>

<!-- Tab 1: Member Email & Password -->
<div id="tab-member" class="tab-content" style="<?= ($activeTab ?? 'member') === 'member' ? 'display: block;' : 'display: none;' ?>">
    <form action="/auth/login" method="POST">
        <?= Csrf::field() ?>

        <div class="form-group">
            <label class="form-label" for="email">Delegate / Member Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="name@organization.org" required autofocus>
        </div>

        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <label class="form-label" for="password">Password</label>
                <a href="/auth/forgot-password" style="font-size: 0.8rem;">Forgot password?</a>
            </div>
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
            Sign In with Email
        </button>
    </form>
</div>

<!-- Tab 2: Organization Common Access Hash -->
<div id="tab-hash" class="tab-content" style="<?= ($activeTab ?? '') === 'hash' ? 'display: block;' : 'display: none;' ?>">
    <form action="/auth/hash-login" method="POST">
        <?= Csrf::field() ?>

        <div class="form-group">
            <label class="form-label" for="hash">Organization Access Key</label>
            <input type="password" id="hash" name="hash" class="form-control" placeholder="convota_..." required>
            <p class="form-text">
                Enter your organization's unique shared key to access your delegation portal and designate your voter.
            </p>
        </div>

        <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 1rem;">
            Access Delegation Portal
        </button>
    </form>
</div>
