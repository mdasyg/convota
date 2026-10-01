<?php
use Convota\Security\Csrf;
?>
<div style="max-width: 760px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <span class="badge badge-success" style="font-size: 0.8rem; margin-bottom: 0.5rem;">VOTING ACTIVE</span>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);"><?= htmlspecialchars($proposal['title']) ?></h1>
        <?php if ($proposal['description']): ?>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem; line-height: 1.6;">
                <?= nl2br(htmlspecialchars($proposal['description'])) ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Voter Designation & Authentication Banner -->
    <div class="card" style="border-left: 4px solid var(--accent); background: #f8fafc;">
        <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Authorized Delegation Voter</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-main);">
                    <?= htmlspecialchars($org['name']) ?> — <?= htmlspecialchars((string)$org['voting_weight']) ?> <?= $org['voting_weight'] == 1 ? 'Vote' : 'Votes' ?> — <?= htmlspecialchars($userName) ?>
                </div>
            </div>
            <div>
                <span class="badge <?= $proposal['is_secret'] ? 'badge-primary' : 'badge-info' ?>">
                    <?= $proposal['is_secret'] ? '🔒 Secret Decoupled Ballot' : '📢 Open Roll-Call Ballot' ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($alreadyVoted): ?>
        <!-- Already Voted Banner -->
        <div class="card" style="border: 2px solid var(--success); background: #f0fdf4;">
            <div class="card-body" style="text-align: center; padding: 2.5rem 1.5rem;">
                <span style="font-size: 3rem;">✅</span>
                <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--success); margin: 0.75rem 0 0.5rem 0;">
                    Your organization has already cast its vote!
                </h2>
                <p style="color: var(--text-main); font-size: 1rem;">
                    Vote successfully submitted by representative <strong><?= htmlspecialchars($participation['voter_name_snapshot'] ?? $userName) ?></strong>
                </p>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Recorded on <?= htmlspecialchars($participation['voted_at'] ?? 'now') ?> UTC
                </p>
                <div style="margin-top: 1.5rem;">
                    <a href="/proposals/monitor?id=<?= $proposal['id'] ?>" class="btn btn-secondary">View Live Monitor</a>
                    <a href="/dashboard" class="btn btn-primary">Return to Dashboard</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Ballot Casting Form -->
        <form action="/proposals/cast-vote" method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="proposal_id" value="<?= $proposal['id'] ?>">
            <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
            <input type="hidden" name="user_id" value="<?= $userId ?>">
            <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars($idempotencyKey) ?>">

            <div class="card">
                <div class="card-header">
                    <h3>Official Ballot Selections</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                        Please choose one option. Once submitted, your vote is recorded atomically and an automated notification will be emailed to all members of <?= htmlspecialchars($org['name']) ?>.
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($proposal['options'] as $idx => $opt): ?>
                            <label style="display: flex; align-items: center; gap: 1rem; padding: 1rem; border: 2px solid var(--border); border-radius: var(--radius); cursor: pointer; transition: all 0.15s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="if(!this.querySelector('input').checked) this.style.borderColor='var(--border)'">
                                <input type="radio" name="option_id" value="<?= $opt['id'] ?>" required style="transform: scale(1.3); cursor: pointer;">
                                <span style="font-size: 1.1rem; font-weight: 600; color: var(--text-main);">
                                    <?= htmlspecialchars($opt['option_text']) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                <a href="/dashboard" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Confirm vote submission for <?= htmlspecialchars($org['name']) ?>?');">
                    🔒 Cast Official Vote
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
