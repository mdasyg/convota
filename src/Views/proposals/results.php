<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <h1 style="font-size: 1.5rem; font-weight: 700;">Certified Results: <?= htmlspecialchars($proposal['title']) ?></h1>
            <span class="badge badge-danger">CLOSED & SEALED</span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            <?= $results['is_secret'] ? '🔒 Secret Ballot (Decoupled Anonymous Ballots)' : '📢 Open Roll-Call Ballot' ?> | Certified Tabulation
        </p>
    </div>

    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= url('/proposals') ?>" class="btn btn-secondary btn-sm">← Back to Proposals</a>
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">🖨️ Print Protocol</button>
    </div>

</div>

<!-- Quorum & Majority Benchmark Card -->
<div class="card" style="border-left: 4px solid <?= $results['quorum_achieved'] ? 'var(--success)' : 'var(--danger)' ?>;">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="font-size: 1.1rem; font-weight: 700;">
                Quorum Status: <?= $results['quorum_achieved'] ? 'VALID (Quorum Reached)' : 'INQUORATE (Quorum Not Met)' ?>
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                Total Weight Cast: <strong><?= $results['total_weight'] ?></strong> | Total Ballots: <strong><?= $results['total_ballots'] ?></strong> | Required Quorum: <?= $results['quorum_percentage_required'] ?>% | Majority Rule: <?= $results['majority_percentage_required'] ?>%
            </p>
        </div>
        <div>
            <span class="badge <?= $results['quorum_achieved'] ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                <?= $results['quorum_achieved'] ? 'QUORUM MET' : 'QUORUM FAILED' ?>
            </span>
        </div>
    </div>
</div>

<!-- Tabulated Results by Choice -->
<div class="card">
    <div class="card-header">
        <h3>Tabulation & Percentages</h3>
        <span class="badge badge-info">Neutral Calculation</span>
    </div>
    <div class="card-body">
        <?php foreach ($results['options'] as $opt): ?>
            <div style="margin-bottom: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                    <div>
                        <strong style="font-size: 1.05rem;"><?= htmlspecialchars($opt['option_text']) ?></strong>
                        <span style="color: var(--text-muted); font-size: 0.85rem; margin-left: 0.5rem;">
                            (<?= $opt['vote_count'] ?> <?= $opt['vote_count'] == 1 ? 'ballot' : 'ballots' ?>, <?= $opt['total_weight'] ?> weight)
                        </span>
                    </div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary);">
                        <?= $opt['percentage'] ?>%
                    </div>
                </div>

                <div class="progress-bar-container" style="height: 16px;">
                    <div class="progress-bar success" style="width: <?= $opt['percentage'] ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>

        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 0.75rem;">
            * In compliance with democratic specifications, the system does not declare winners; it displays certified mathematical tallies.
        </p>
    </div>
</div>

<!-- Open Roll-Call Breakdown (If Open Vote) -->
<?php if (!$results['is_secret']): ?>
    <div class="card">
        <div class="card-header">
            <h3>📢 Open Roll-Call Audit Protocol</h3>
            <span class="badge badge-primary"><?= count($results['roll_call']) ?> Recorded Vote(s)</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Organization</th>
                            <th>Representative Name</th>
                            <th>Option Cast</th>
                            <th>Weight</th>
                            <th>Timestamp (UTC)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['roll_call'] as $rc): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($rc['organization_name']) ?></strong></td>
                                <td><?= htmlspecialchars($rc['representative_name']) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($rc['option_text']) ?></span></td>
                                <td><?= htmlspecialchars((string)$rc['vote_weight']) ?></td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($rc['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Anonymous Receipt Board (If Secret Vote) -->
    <div class="card">
        <div class="card-header">
            <h3>🔒 Anonymous Verification Board</h3>
            <span class="badge badge-success">Cryptographically Unlinked</span>
        </div>
        <div class="card-body">
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                Each anonymous ballot is listed below with its cryptographic SHA-256 verification hash. Voters can verify their receipt was tallied without exposing their identity.
            </p>

            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Receipt Hash (SHA-256)</th>
                            <th>Choice Included</th>
                            <th>Weight</th>
                            <th>Timestamp (UTC)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['verification_receipts'] as $vr): ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    <?= htmlspecialchars($vr['receipt_hash']) ?>
                                </td>
                                <td><span class="badge badge-secondary"><?= htmlspecialchars($vr['option_text']) ?></span></td>
                                <td><?= htmlspecialchars((string)$vr['vote_weight']) ?></td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($vr['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
