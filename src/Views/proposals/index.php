<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">Proposals & Elections</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Resolutions, motions, and electoral procedures of the General Assembly.
        </p>
    </div>
    <?php if (!empty($isAdmin) || !empty($isPresident)): ?>
        <a href="/proposals/create" class="btn btn-success btn-sm">➕ Create Proposal / Election</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Proposal</th>
                    <th>Ballot Type</th>
                    <th>Timing Mode</th>
                    <th>Status</th>
                    <th>Participation</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($proposals)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            No proposals created yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proposals as $p): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($p['title']) ?></strong>
                                <?php if ($p['description']): ?>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars(substr($p['description'], 0, 85)) ?>...</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $p['is_secret'] ? 'badge-primary' : 'badge-info' ?>">
                                    <?= $p['is_secret'] ? '🔒 Secret' : '📢 Open' ?>
                                </span>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                    <?= htmlspecialchars(str_replace('_', ' ', $p['voting_type'])) ?>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars(ucfirst($p['timing_mode'])) ?>
                                <?php if ($p['timing_mode'] === 'scheduled'): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?= htmlspecialchars((string)$p['start_time']) ?> — <?= htmlspecialchars((string)$p['end_time']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $p['status'] === 'open' ? 'success' : ($p['status'] === 'closed' ? 'danger' : 'secondary') ?>">
                                    <?= htmlspecialchars(strtoupper($p['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= $p['votes_cast'] ?> of <?= $p['eligible_voters'] ?></strong> voted
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.35rem;">
                                    <?php if ($p['status'] === 'open'): ?>
                                        <a href="/proposals/vote?id=<?= $p['id'] ?>" class="btn btn-success btn-sm">Vote</a>
                                    <?php endif; ?>

                                    <a href="/proposals/monitor?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Monitor</a>

                                    <?php if (in_array($p['status'], ['closed', 'archived'], true)): ?>
                                        <a href="/proposals/results?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">Results</a>
                                    <?php endif; ?>

                                    <a href="/proposals/projector?id=<?= $p['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Open Hall Projector View">📽️ Projector</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
