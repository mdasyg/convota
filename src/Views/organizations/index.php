<?php
use Convota\Security\Csrf;
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">Member Organizations</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Organizations participating in the Collective, voting weights, and delegation members.
        </p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a href="<?= url('/csv/export-organizations') ?>" class="btn btn-secondary btn-sm" title="Export Organizations CSV">Export Orgs CSV</a>
        <a href="<?= url('/csv/export-members') ?>" id="btnIndexExportMembers" class="btn btn-secondary btn-sm" title="Export Members Roster CSV">Export Members CSV</a>
        <label style="font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.25rem; margin-bottom: 0; color: var(--text-muted); cursor: pointer;" title="Append shared Organization Authorization Key to each line">
            <input type="checkbox" onchange="document.getElementById('btnIndexExportMembers').href = '<?= url('/csv/export-members') ?>' + (this.checked ? '?include_auth_hash=1' : '');">
            Auth Key
        </label>
        <?php if (!empty($isAdmin)): ?>
            <a href="<?= url('/organizations/create') ?>" class="btn btn-primary btn-sm">➕ Add Organization</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Country</th>
                    <th>Role & Status</th>
                    <th>Voting Power</th>
                    <th>Delegation</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($organizations as $org): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($org['name']) ?></strong>
                            <?php if ($org['short_name']): ?>
                                <span style="color: var(--text-muted); font-size: 0.85rem;">(<?= htmlspecialchars($org['short_name']) ?>)</span>
                            <?php endif; ?>
                            <?php if ($org['website']): ?>
                                <div style="font-size: 0.75rem;"><a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($org['website']) ?></a></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($org['country'] ?? '—') ?></td>
                        <td>
                            <?php if ($org['is_president']): ?>
                                <span class="badge badge-president">President</span>
                            <?php elseif ($org['is_observer']): ?>
                                <span class="badge badge-warning">Observer</span>
                            <?php else: ?>
                                <span class="badge badge-primary">Member</span>
                            <?php endif; ?>

                            <span class="badge <?= $org['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                <?= htmlspecialchars($org['status']) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars((string)$org['voting_weight']) ?></strong> <?= $org['voting_weight'] == 1 ? 'Vote' : 'Votes' ?>
                        </td>
                        <td>
                            <div><strong><?= $org['member_count'] ?></strong> member(s)</div>
                            <?php if ($org['primary_member_name']): ?>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Primary: <?= htmlspecialchars($org['primary_member_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 0.25rem;">
                                <a href="<?= url('/organizations/portal?id=' . $org['id']) ?>" class="btn btn-secondary btn-sm" title="Delegation Portal">Portal</a>

                                <?php if (!empty($isAdmin)): ?>
                                    <a href="<?= url('/organizations/edit?id=' . $org['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>

                                    <?php if (!$org['is_president'] && !$org['is_observer']): ?>
                                        <form action="<?= url('/organizations/set-president') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Designate <?= htmlspecialchars($org['name']) ?> as the President Organization?');">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Set as President Organization">Set President</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>
</div>
