<?php
use Convota\Security\Csrf;
$summary = $preview['summary'];
$rows = $preview['rows'];
$differentRows = array_values(array_filter($rows, fn($r) => empty($r['is_identical'])));
$identicalCount = (int)($summary['identical_rows'] ?? 0);
?>
<div style="margin-bottom: 1.5rem;">
    <h1 style="font-size: 1.5rem; font-weight: 700;">CSV Import Validation & Conflict Analysis</h1>
    <p style="color: var(--text-muted); font-size: 0.9rem;">
        Review duplicate and conflict detection results before committing changes to the database.
    </p>
</div>

<!-- Summary Badges -->
<div class="grid-stats">
    <div class="stat-box">
        <div class="title">Total Rows</div>
        <div class="value"><?= $summary['total'] ?></div>
    </div>
    <div class="stat-box">
        <div class="title">New Lines / Members</div>
        <div class="value" style="color: var(--success);"><?= $summary['new_members'] ?></div>
    </div>
    <div class="stat-box">
        <div class="title">Identical with Database</div>
        <div class="value" style="color: var(--primary);"><?= $identicalCount ?></div>
    </div>
    <div class="stat-box">
        <div class="title">Critical Conflicts</div>
        <div class="value" style="color: var(--danger);"><?= $summary['conflicts'] ?></div>
    </div>
</div>

<?php if ($identicalCount > 0): ?>
    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; border-radius: var(--radius); padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1.25rem;">ℹ️</span>
            <div>
                <strong style="color: #1e40af; font-size: 0.95rem;"><?= $identicalCount ?> <?= $identicalCount === 1 ? 'line was' : 'lines were' ?> identical with the database.</strong>
                <p style="color: #3b82f6; font-size: 0.85rem; margin: 0.25rem 0 0 0;">
                    Identical records (same name, surname, email, and organization) do not require changes and are omitted from the detailed table below. Only different lines are shown.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($summary['conflicts'] > 0): ?>
    <div class="conflict-box">
        ⚠️ <strong>ATTENTION: <?= $summary['conflicts'] ?> Critical Conflict(s) Detected!</strong><br>
        Some members in your CSV are already registered under a different organization. To maintain organizational integrity and historical accountability, conflicting rows will be automatically skipped and will NOT overwrite existing memberships.
    </div>
<?php endif; ?>

<form action="<?= url('/csv/commit') ?>" method="POST">
    <?= Csrf::field() ?>

    <input type="hidden" name="rows_json" value="<?= htmlspecialchars(json_encode($rows), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="rows_hash" value="<?= htmlspecialchars($rowsHash ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <div class="card">
        <div class="card-header">
            <h3>Different Lines Analysis (<?= count($differentRows) ?>)</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Update?</th>
                            <th>Status</th>
                            <th>Member Name</th>
                            <th>Email</th>
                            <th>Target Organization</th>
                            <th>Conflict / Notice Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($differentRows)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                    ✓ All <?= $identicalCount ?> lines were identical with the database. No new or modified lines detected.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($differentRows as $r): ?>
                                <tr style="<?= $r['status'] === 'conflict_different_org' ? 'background: #fef2f2;' : ($r['status'] === 'existing_same_org' ? 'background: #fffbeb;' : '') ?>">
                                    <td>
                                        <?php if ($r['status'] === 'existing_same_org'): ?>
                                            <input type="checkbox" name="update_emails[]" value="<?= htmlspecialchars($r['email']) ?>" checked title="Check to update existing member details">
                                        <?php elseif ($r['status'] === 'conflict_different_org'): ?>
                                            <span style="color: var(--danger); font-size: 1.1rem;" title="Cannot update conflicting member across organizations">🚫</span>
                                        <?php else: ?>
                                            <span style="color: var(--success); font-weight: 700;">➕</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['status'] === 'new'): ?>
                                            <span class="badge badge-success"><?= $r['org_is_new'] ? 'New Org & Member' : 'New Member' ?></span>
                                        <?php elseif ($r['status'] === 'existing_same_org'): ?>
                                            <span class="badge badge-warning">Existing (Different Details)</span>
                                        <?php elseif ($r['status'] === 'conflict_different_org'): ?>
                                            <span class="badge badge-danger">Org Conflict</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars("{$r['name']} {$r['surname']}") ?></strong></td>
                                    <td><?= htmlspecialchars($r['email']) ?></td>
                                    <td><?= htmlspecialchars($r['organization']) ?></td>
                                    <td>
                                        <?php if ($r['warning']): ?>
                                            <div style="font-size: 0.85rem; color: <?= $r['status'] === 'conflict_different_org' ? 'var(--danger)' : 'var(--warning)' ?>; font-weight: 600;">
                                                <?= htmlspecialchars($r['warning']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 0.85rem; color: var(--success);">Ready for insertion</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
        <a href="<?= url('/csv/import') ?>" class="btn btn-secondary">← Cancel & Re-upload</a>
        <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Commit validated rows to the database?');">
            ✓ Commit Validated Import
        </button>
    </div>
</form>
