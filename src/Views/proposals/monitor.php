<?php
use Convota\Security\Csrf;
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <h1 style="font-size: 1.5rem; font-weight: 700;"><?= htmlspecialchars($proposal['title']) ?></h1>
            <span class="badge badge-<?= $proposal['status'] === 'open' ? 'success' : ($proposal['status'] === 'closed' ? 'danger' : 'secondary') ?>" style="font-size: 0.85rem;">
                <?= htmlspecialchars(strtoupper($proposal['status'])) ?>
            </span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            <?= $proposal['is_secret'] ? '🔒 Secret Ballot (Decoupled)' : '📢 Open Roll-Call Ballot' ?> | Format: <?= htmlspecialchars(str_replace('_', ' ', $proposal['voting_type'])) ?>
        </p>
    </div>

    <!-- State Machine Transition Controls -->
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a href="/proposals/projector?id=<?= $proposal['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">📽️ Open Projector</a>

        <form action="/proposals/status" method="POST" style="display: inline;">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $proposal['id'] ?>">

            <?php if ($proposal['status'] === 'draft'): ?>
                <button type="submit" name="status" value="open" class="btn btn-success btn-sm">▶ Open Voting</button>
            <?php elseif ($proposal['status'] === 'open'): ?>
                <button type="submit" name="status" value="paused" class="btn btn-warning btn-sm">⏸ Pause</button>
                <button type="submit" name="status" value="closed" class="btn btn-danger btn-sm" onclick="return confirm('Close voting and reveal tallies? Ballots cannot be reopened.');">⏹ Close Vote & Seal</button>
            <?php elseif ($proposal['status'] === 'paused'): ?>
                <button type="submit" name="status" value="open" class="btn btn-success btn-sm">▶ Resume Voting</button>
                <button type="submit" name="status" value="closed" class="btn btn-danger btn-sm" onclick="return confirm('Close voting?');">⏹ Close Vote & Seal</button>
            <?php elseif ($proposal['status'] === 'closed'): ?>
                <a href="/proposals/results?id=<?= $proposal['id'] ?>" class="btn btn-primary btn-sm">📊 View Certified Results</a>
                <button type="submit" name="status" value="archived" class="btn btn-secondary btn-sm" onclick="return confirm('Archive permanently?');">📁 Archive</button>
            <?php elseif ($proposal['status'] === 'archived'): ?>
                <a href="/proposals/results?id=<?= $proposal['id'] ?>" class="btn btn-primary btn-sm">📊 View Certified Results</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Asynchronous Real-Time Monitor Box -->
<div id="live-vote-monitor" data-proposal-id="<?= $proposal['id'] ?>" class="card">
    <div class="card-header">
        <h3>Live Participation Monitor (Real-Time Polling)</h3>
        <span class="badge badge-info">Results Sealed During Voting</span>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; text-align: center; margin-bottom: 1.5rem;">
            <div class="stat-box">
                <div class="title">Votes Cast</div>
                <div class="value" id="monitor-voted-count"><?= $monitor['total_voted'] ?></div>
            </div>
            <div class="stat-box">
                <div class="title">Eligible Organizations</div>
                <div class="value" id="monitor-eligible-count"><?= $monitor['total_eligible'] ?></div>
            </div>
            <div class="stat-box">
                <div class="title">Turnout Percentage</div>
                <div class="value" id="monitor-percentage"><?= $monitor['participation_percentage'] ?>%</div>
            </div>
        </div>

        <div class="progress-bar-container" style="height: 18px;">
            <div id="monitor-progress-bar" class="progress-bar success" style="width: <?= $monitor['participation_percentage'] ?>%;"></div>
        </div>

        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 1rem; text-align: center;">
            🔒 In accordance with democratic integrity standards, vote selections are cryptographically sealed and will only be tabulated after voting closes.
        </p>
    </div>
</div>

<!-- Organization Status Grid (Who voted vs Pending) -->
<div class="card">
    <div class="card-header">
        <h3>Delegation Participation Status</h3>
        <span class="badge badge-secondary"><?= count($monitor['organizations']) ?> Eligible Delegation(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Organization</th>
                        <th>Voting Weight</th>
                        <th>Status</th>
                        <th>Cast By (Snapshot)</th>
                        <th>Time (UTC)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monitor['organizations'] as $org): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($org['organization_name']) ?></strong>
                                <?php if ($org['short_name']): ?>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">(<?= htmlspecialchars($org['short_name']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string)$org['voting_weight']) ?> Vote(s)</td>
                            <td>
                                <span id="org-badge-<?= $org['organization_id'] ?>" class="badge <?= $org['has_voted'] ? 'badge-success' : 'badge-warning' ?>">
                                    <?= $org['has_voted'] ? 'VOTED' : 'PENDING' ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($org['voter_name_snapshot'] ?? '—') ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">
                                <?= htmlspecialchars($org['voted_at'] ?? '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
