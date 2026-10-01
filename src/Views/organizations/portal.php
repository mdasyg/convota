<?php
use Convota\Security\Csrf;
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">Delegation Portal: <?= htmlspecialchars($org['name']) ?></h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Manage your organization's representatives, designate the active voting delegate, and control shared access keys.
        </p>
    </div>

    <div>
        <span class="badge badge-info" style="font-size: 0.85rem;">Weight: <?= htmlspecialchars((string)$org['voting_weight']) ?> <?= $org['voting_weight'] == 1 ? 'Vote' : 'Votes' ?></span>
        <?php if ($org['is_observer']): ?>
            <span class="badge badge-warning">Observer</span>
        <?php endif; ?>
        <?php if ($org['is_president']): ?>
            <span class="badge badge-president">President Organization</span>
        <?php endif; ?>
    </div>
</div>

<!-- 1. Active Assembly Designated Representative Section -->
<?php if (!empty($activeMeeting)): ?>
    <div class="card" style="border: 2px solid var(--accent); background: #f0f7ff;">
        <div class="card-header" style="background: #e0effe;">
            <h3 style="color: var(--primary);">🗳️ Active Assembly: <?= htmlspecialchars($activeMeeting['title']) ?></h3>
            <span class="badge badge-primary">Current Session</span>
        </div>
        <div class="card-body">
            <p style="font-size: 0.9rem; color: var(--text-main); margin-bottom: 1rem;">
                <strong>Select Participating Member:</strong> The vote belongs to the organization, while the representative is the authenticated person exercising that organization's voting right during this assembly.
            </p>

            <form action="/meetings/set-presence" method="POST" style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
                <?= Csrf::field() ?>
                <input type="hidden" name="meeting_id" value="<?= $activeMeeting['id'] ?>">
                <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">

                <div style="flex: 1; min-width: 250px;">
                    <label class="form-label" for="representative_user_id">Designated Voting Member</label>
                    <select id="representative_user_id" name="representative_user_id" class="form-control" required>
                        <?php foreach ($org['members'] as $m): ?>
                            <?php 
                                $isSelected = (!empty($presence['representative_user_id']) && (int)$presence['representative_user_id'] === (int)$m['id'])
                                    || (empty($presence['representative_user_id']) && $m['is_primary']);
                            ?>
                            <option value="<?= $m['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                <?= htmlspecialchars("{$m['name']} {$m['surname']} ({$m['email']})") ?> <?= $m['is_primary'] ? '— [Primary Delegate]' : '— [Substitute]' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Confirm Designated Voter</button>

                <?php if (!empty($presence['is_present'])): ?>
                    <button type="submit" name="mark_absent" value="1" class="btn btn-secondary btn-sm" onclick="return confirm('Mark your organization as absent for this meeting?');">
                        Mark Absent
                    </button>
                <?php endif; ?>
            </form>

            <?php if (!empty($presence['is_present'])): ?>
                <div style="margin-top: 1rem; font-size: 0.85rem; color: var(--success); font-weight: 600;">
                    ✓ Organization is currently marked PRESENT (Method: <?= htmlspecialchars($presence['check_in_method']) ?> at <?= htmlspecialchars($presence['checked_in_at']) ?> UTC)
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- 2. Roster Management (Primary & Substitutes) -->
<div class="card">
    <div class="card-header">
        <h3>Delegation Roster (Primary Delegate & Substitutes)</h3>
        <span class="badge badge-secondary"><?= count($org['members']) ?> Registered Member(s)</span>
    </div>
    <div class="card-body">
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
            Any verified delegation member or administrator can add new substitutes or remove existing members (even the primary delegate). All modifications are permanently recorded in the tamper-evident audit log with IP and timestamp.
        </p>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th>Email Address</th>
                        <th>Telephone</th>
                        <th>Role</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($org['members'] as $m): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars("{$m['name']} {$m['surname']}") ?></strong>
                            </td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td><?= htmlspecialchars($m['telephone'] ?? '—') ?></td>
                            <td>
                                <?php if ($m['is_primary']): ?>
                                    <span class="badge badge-primary">Primary Delegate</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Substitute Member</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.35rem;">
                                    <?php if (!$m['is_primary']): ?>
                                        <form action="/organizations/set-primary" method="POST" style="display: inline;">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" title="Promote to Primary Delegate">Set Primary</button>
                                        </form>
                                    <?php endif; ?>

                                    <form action="/organizations/remove-member" method="POST" style="display: inline;" onsubmit="return confirm('Remove <?= htmlspecialchars($m['name']) ?> from the delegation roster? This action is recorded in the audit trail.');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Remove Member">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Substitute Member Form -->
        <div style="margin-top: 1.5rem; background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem;">
            <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem;">➕ Add Substitute Member</h4>
            <form action="/organizations/add-member" method="POST">
                <?= Csrf::field() ?>
                <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="new_name">First Name</label>
                        <input type="text" id="new_name" name="name" class="form-control" placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_surname">Surname</label>
                        <input type="text" id="new_surname" name="surname" class="form-control" placeholder="Surname" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="new_email">Email Address</label>
                        <input type="email" id="new_email" name="email" class="form-control" placeholder="substitute@organization.org" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_phone">Telephone (Optional)</label>
                        <input type="tel" id="new_phone" name="telephone" class="form-control" placeholder="+30 ...">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">
                    Add Member & Send Welcome Email
                </button>
            </form>
        </div>
    </div>
</div>

<!-- 3. Shared Organization Access Key Management & Security Controls -->
<div class="card">
    <div class="card-header">
        <h3>🔑 Organization Access Key & Delegation Security</h3>
        <span class="badge <?= $org['hash_auth_enabled'] ? 'badge-success' : 'badge-danger' ?>">
            Key Auth: <?= $org['hash_auth_enabled'] ? 'ENABLED' : 'DISABLED' ?>
        </span>
    </div>
    <div class="card-body">
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
            The Common Access Key is unique to <?= htmlspecialchars($org['name']) ?> and shared between delegation members. It functions as a shared login to self-manage this portal and select the participating voter.
        </p>

        <!-- Current Key Display -->
        <div class="form-group">
            <label class="form-label">Current Shared Organization Key</label>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" class="form-control" value="<?= htmlspecialchars($org['common_hash']) ?>" readonly style="font-family: monospace; background: #f8fafc;">
                <button type="button" class="btn btn-secondary" data-copy="<?= htmlspecialchars($org['common_hash']) ?>">Copy Key</button>
            </div>
        </div>

        <!-- Security Action Buttons -->
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem;">
            <!-- Email Key to Members -->
            <form action="/organizations/email-hash" method="POST" style="display: inline;">
                <?= Csrf::field() ?>
                <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                <button type="submit" class="btn btn-secondary">
                    📧 Email Key to All Members
                </button>
            </form>

            <!-- Reinitialize / Rotate Key -->
            <form action="/organizations/rotate-hash" method="POST" style="display: inline;" onsubmit="return confirm('Rotate access key? The old key will be instantly invalidated and the new key emailed to all members.');">
                <?= Csrf::field() ?>
                <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                <button type="submit" class="btn btn-warning">
                    🔄 Reinitialize & Email New Key
                </button>
            </form>

            <!-- Toggle Hash Auth -->
            <form action="/organizations/toggle-hash" method="POST" style="display: inline;">
                <?= Csrf::field() ?>
                <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                <input type="hidden" name="enabled" value="<?= $org['hash_auth_enabled'] ? '0' : '1' ?>">
                <button type="submit" class="btn <?= $org['hash_auth_enabled'] ? 'btn-danger' : 'btn-success' ?>">
                    <?= $org['hash_auth_enabled'] ? 'Disable Key Authorization' : 'Enable Key Authorization' ?>
                </button>
            </form>

            <!-- Toggle Email Alert on Key Use -->
            <form action="/organizations/toggle-email-alert" method="POST" style="display: inline;">
                <?= Csrf::field() ?>
                <input type="hidden" name="organization_id" value="<?= $org['id'] ?>">
                <input type="hidden" name="enabled" value="<?= $org['email_on_hash_login'] ? '0' : '1' ?>">
                <button type="submit" class="btn btn-secondary">
                    <?= $org['email_on_hash_login'] ? '🔔 Email on Key Use: ON' : '🔕 Email on Key Use: OFF' ?>
                </button>
            </form>
        </div>
    </div>
</div>
