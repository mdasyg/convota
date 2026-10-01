<?php
use Convota\Security\Validator;
?>
<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);">Collective Assembly Dashboard</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">
                General Assembly presence, dynamic quorum monitoring, and secure voting operations.
            </p>
        </div>

        <?php if (!empty($isAdmin) || !empty($isPresident)): ?>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="<?= url('/meetings/create') ?>" class="btn btn-primary btn-sm">➕ Schedule Meeting</a>
                <a href="<?= url('/proposals/create') ?>" class="btn btn-success btn-sm">🗳️ New Proposal</a>
                <a href="<?= url('/csv/import') ?>" class="btn btn-secondary btn-sm">📄 Import CSV</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Active Meeting Dynamic Quorum Banner -->
<?php if (!empty($activeMeeting) && !empty($quorumData)): ?>
    <div id="live-quorum-monitor" data-meeting-id="<?= (int)$activeMeeting['id'] ?>" class="quorum-banner <?= $quorumData['is_quorum_reached'] ? 'met' : 'unmet' ?>" style="display: flex; flex-direction: column; gap: 0.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <span class="badge <?= $quorumData['is_quorum_reached'] ? 'badge-success' : 'badge-warning' ?>" style="margin-right: 0.5rem;">
                    <?= $quorumData['is_quorum_reached'] ? 'QUORUM REACHED' : 'QUORUM PENDING' ?>
                </span>
                <strong>Active Meeting:</strong> <?= htmlspecialchars($activeMeeting['title']) ?>
            </div>
            <div>
                <a href="<?= url('/meetings/presence?id=' . (int)$activeMeeting['id']) ?>" class="btn btn-secondary btn-sm">Live Presence & Roll-Call →</a>
            </div>
        </div>


        <div style="font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-top: 0.25rem;">
            <span id="quorum-banner-message"><?= htmlspecialchars($quorumData['status_message']) ?></span>
        </div>

        <div class="progress-bar-container">
            <div id="quorum-progress-bar" class="progress-bar <?= $quorumData['is_quorum_reached'] ? 'success' : 'warning' ?>" style="width: <?= $quorumData['attendance_percentage'] ?>%;"></div>
        </div>
    </div>
<?php endif; ?>

<!-- Organization Delegation Status Box (for delegates) -->
<?php if (!empty($currentOrg)): ?>
    <div class="card" style="border-left: 4px solid var(--accent);">
        <div class="card-header">
            <h3>Delegation: <?= htmlspecialchars($currentOrg['name']) ?></h3>
            <div>
                <span class="badge badge-info">Weight: <?= htmlspecialchars((string)$currentOrg['voting_weight']) ?> <?= $currentOrg['voting_weight'] == 1 ? 'Vote' : 'Votes' ?></span>
                <?php if ($currentOrg['is_observer']): ?>
                    <span class="badge badge-warning">Observer</span>
                <?php endif; ?>
                <?php if ($currentOrg['is_president']): ?>
                    <span class="badge badge-president">President Organization</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.75rem;">
                The vote belongs to the organization; the designated representative exercises that vote during assembly sessions.
            </p>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <a href="<?= url('/organizations/portal') ?>" class="btn btn-primary btn-sm">Manage Delegation Roster & Shared Key →</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Active Open Proposals -->
<div class="card">
    <div class="card-header">
        <h2>🗳️ Active Open Proposals & Elections</h2>
        <span class="badge badge-success"><?= count($openProposals) ?> OPEN NOW</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($openProposals)): ?>
            <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                There are currently no open proposals accepting ballots.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Proposal Title</th>
                            <th>Mode</th>
                            <th>Timing</th>
                            <th>Participation</th>
                            <th>Your Delegation</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($openProposals as $p): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($p['title']) ?></strong>
                                    <?php if ($p['description']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars(substr($p['description'], 0, 90)) ?>...</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $p['is_secret'] ? 'badge-primary' : 'badge-info' ?>">
                                        <?= $p['is_secret'] ? '🔒 Secret Ballot' : '📢 Open Roll-Call' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars(ucfirst($p['timing_mode'])) ?>
                                    <?php if ($p['timing_mode'] === 'scheduled'): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Until: <?= htmlspecialchars((string)$p['end_time']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600;"><?= $p['votes_cast'] ?> of <?= $p['eligible_voters'] ?></span> voted
                                </td>
                                <td>
                                    <?php if ($p['has_voted']): ?>
                                        <span class="badge badge-success">✓ Voted</span>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">By: <?= htmlspecialchars($p['voted_by']) ?></div>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending Ballot</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($p['has_voted']): ?>
                                        <a href="<?= url('/proposals/monitor?id=' . $p['id']) ?>" class="btn btn-secondary btn-sm">Monitor</a>
                                    <?php else: ?>
                                        <a href="<?= url('/proposals/vote?id=' . $p['id']) ?>" class="btn btn-success btn-sm">Cast Vote →</a>
                                    <?php endif; ?>

                                    <?php if (!empty($isAdmin) || !empty($isPresident)): ?>
                                        <a href="<?= url('/proposals/monitor?id=' . $p['id']) ?>" class="btn btn-secondary btn-sm">Presidium Console</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>


<!-- Collective Overview Stats (Admin & President) -->
<?php if (!empty($isAdmin) || !empty($isPresident)): ?>
    <div class="grid-stats">
        <div class="stat-box">
            <div class="title">Total Organizations</div>
            <div class="value"><?= $stats['total_orgs'] ?></div>
        </div>
        <div class="stat-box">
            <div class="title">Voting Organizations</div>
            <div class="value"><?= $stats['voting_orgs'] ?></div>
        </div>
        <div class="stat-box">
            <div class="title">Observer Organizations</div>
            <div class="value"><?= $stats['observer_orgs'] ?></div>
        </div>
        <div class="stat-box">
            <div class="title">Registered Delegates</div>
            <div class="value"><?= $stats['total_members'] ?></div>
        </div>
    </div>
<?php endif; ?>
