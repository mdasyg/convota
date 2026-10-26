<?php
use Convota\Security\Csrf;
?>
<div style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700;">Edit Organization</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                Update managerial metadata and voting power for <?= htmlspecialchars($org['name']) ?>.
            </p>
        </div>
        <a href="<?= url('/organizations') ?>" class="btn btn-secondary btn-sm">← Back</a>
    </div>

    <form action="<?= url('/organizations/edit') ?>" method="POST">

        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= $org['id'] ?>">

        <div class="card">
            <div class="card-header">
                <h3>Organization Information</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="name">Organization Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($org['name']) ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="short_name">Short Name / Acronym</label>
                        <input type="text" id="short_name" name="short_name" class="form-control" value="<?= htmlspecialchars($org['short_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="country">Country</label>
                        <input type="text" id="country" name="country" class="form-control" value="<?= htmlspecialchars($org['country'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="website">Website URL</label>
                        <input type="url" id="website" name="website" class="form-control" value="<?= htmlspecialchars($org['website'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="voting_weight">Voting Power / Weight</label>
                        <input type="number" id="voting_weight" name="voting_weight" class="form-control" value="<?= htmlspecialchars((string)$org['voting_weight']) ?>" step="0.1" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="status">Operational Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active" <?= $org['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $org['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $org['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description / Notes</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($org['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row" style="margin-top: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_observer" value="1" <?= $org['is_observer'] ? 'checked' : '' ?>>
                        <span><strong>Observer Organization</strong> (0 votes; observer status)</span>
                    </label>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; align-items: center;">
            <button type="submit" class="btn btn-primary">Save Organization Information</button>
        </div>
    </form>

    <!-- Delegation Members Management -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3>Delegation Roster & Member Management</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    Manage delegates, substitutes, and assign the primary voting representative.
                </p>
            </div>
            <span class="badge badge-info"><?= count($org['members'] ?? []) ?> Members</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($org['members'])): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name & Surname</th>
                                <th>Email</th>
                                <th>Telephone</th>
                                <th>Delegation Role</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($org['members'] as $m): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($m['name'] . ' ' . $m['surname']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($m['email']) ?></td>
                                    <td><?= htmlspecialchars($m['telephone'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($m['is_primary']): ?>
                                            <span class="badge badge-success">⭐ Primary Delegate</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Substitute Member</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <!-- Edit Member Button -->
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('edit-member-modal-<?= $m['id'] ?>').style.display='flex'">
                                            ✏️ Edit
                                        </button>

                                        <!-- Make Primary Button (if not already primary) -->
                                        <?php if (!$m['is_primary']): ?>
                                            <form action="<?= url('/organizations/set-primary') ?>" method="POST" style="display: inline;">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                                <input type="hidden" name="redirect_to" value="<?= url('/organizations/edit?id=' . $org['id']) ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" title="Promote to Primary Delegate (others become substitutes)">
                                                    ⭐ Set Primary
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Remove Member Button -->
                                        <form action="<?= url('/organizations/remove-member') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove member <?= htmlspecialchars($m['name'] . ' ' . $m['surname']) ?> from this organization?');">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                            <input type="hidden" name="redirect_to" value="<?= url('/organizations/edit?id=' . $org['id']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                🗑️ Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Member Edit Modal -->
                                <div id="edit-member-modal-<?= $m['id'] ?>" class="convota-modal" style="display: none;">
                                    <div class="convota-modal-backdrop" onclick="document.getElementById('edit-member-modal-<?= $m['id'] ?>').style.display='none'"></div>
                                    <div class="convota-modal-content">
                                        <div class="convota-modal-header">
                                            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;">Edit Delegation Member</h3>
                                            <button type="button" class="convota-modal-close" onclick="document.getElementById('edit-member-modal-<?= $m['id'] ?>').style.display='none'">&times;</button>
                                        </div>
                                        <form action="<?= url('/organizations/update-member') ?>" method="POST">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                            <input type="hidden" name="redirect_to" value="<?= url('/organizations/edit?id=' . $org['id']) ?>">
                                            
                                            <div class="convota-modal-body">
                                                <div class="form-row">
                                                    <div class="form-group">
                                                        <label class="form-label">First Name <span style="color: var(--danger);">*</span></label>
                                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($m['name']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Surname <span style="color: var(--danger);">*</span></label>
                                                        <input type="text" name="surname" class="form-control" value="<?= htmlspecialchars($m['surname']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group">
                                                        <label class="form-label">Email Address <span style="color: var(--danger);">*</span></label>
                                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($m['email']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Telephone</label>
                                                        <input type="tel" name="telephone" class="form-control" value="<?= htmlspecialchars($m['telephone'] ?? '') ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group" style="margin-top: 0.75rem;">
                                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                                        <input type="checkbox" name="is_primary" value="1" <?= $m['is_primary'] ? 'checked' : '' ?>>
                                                        <span><strong>Set as Primary Delegate</strong> (Other members automatically become substitutes)</span>
                                                    </label>
                                                </div>
                                                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem;">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('edit-member-modal-<?= $m['id'] ?>').style.display='none'">Cancel</button>
                                                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding: 1.5rem; text-align: center; color: var(--text-muted);">
                    No members registered in this organization yet. Add the first member below.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Member Card -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h3>Add Member to Delegation</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                Register a new primary delegate or substitute member for this organization.
            </p>
        </div>
        <div class="card-body">
            <form action="<?= url('/organizations/add-member') ?>" method="POST">
                <?= Csrf::field() ?>
                <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                <input type="hidden" name="redirect_to" value="<?= url('/organizations/edit?id=' . $org['id']) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_name">First Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="add_name" name="name" class="form-control" placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_surname">Surname <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="add_surname" name="surname" class="form-control" placeholder="Surname" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_email">Email Address <span style="color: var(--danger);">*</span></label>
                        <input type="email" id="add_email" name="email" class="form-control" placeholder="convota@email.vlsi.gr" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_phone">Telephone (Optional)</label>
                        <input type="tel" id="add_phone" name="telephone" class="form-control" placeholder="+30 ...">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_primary" value="1" <?= empty($org['members']) ? 'checked' : '' ?>>
                        <span><strong>Set as Primary Delegate</strong> (Other members automatically become substitutes)</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">
                    Add Member & Send Welcome Email
                </button>
            </form>
        </div>
    </div>
</div>
