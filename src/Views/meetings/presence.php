<?php
use Convota\Security\Csrf;
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;"><?= htmlspecialchars($meeting['title']) ?></h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            <?= htmlspecialchars($meeting['scheduled_at']) ?> UTC | <?= htmlspecialchars($meeting['location'] ?? 'Online') ?>
        </p>
    </div>

    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= url('/meetings/export-csv?id=' . $meeting['id']) ?>" class="btn btn-secondary btn-sm">📥 Export Attendance CSV</a>

        <!-- Reminder Actions for Presidium / Admin -->
        <?php if (!empty($isAdmin) || !empty($isPresident)): ?>
            <?php
                $unconfiguredCount = 0;
                foreach ($roster as $r) {
                    if (empty($r['is_present'])) {
                        $unconfiguredCount++;
                    }
                }
            ?>
            <?php if ($unconfiguredCount > 0): ?>
                <form action="<?= url('/meetings/send-presence-reminders') ?>" method="POST" style="display: inline;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">
                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Send attendance reminder links to all <?= $unconfiguredCount ?> unconfigured organization delegations?');">
                        ✉️ Send Reminder Link to All Unconfigured (<?= $unconfiguredCount ?>)
                    </button>
                </form>
            <?php endif; ?>

            <!-- State Machine Actions for Presidium / Admin -->
            <form action="<?= url('/meetings/status') ?>" method="POST" style="display: inline;">
                <?= Csrf::field() ?>

                <input type="hidden" name="id" value="<?= $meeting['id'] ?>">

                <?php if ($meeting['status'] === 'draft'): ?>
                    <button type="submit" name="status" value="registration" class="btn btn-warning btn-sm">▶ Open Registration</button>
                <?php elseif ($meeting['status'] === 'registration'): ?>
                    <button type="submit" name="status" value="in_session" class="btn btn-success btn-sm">▶ Convene Assembly (In Session)</button>
                <?php elseif ($meeting['status'] === 'in_session'): ?>
                    <button type="submit" name="status" value="concluded" class="btn btn-danger btn-sm" onclick="return confirm('Conclude and permanently close this meeting?');">⏹ Conclude Assembly</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Dynamic Quorum Banner (Auto-refreshed via AJAX) -->
<div id="live-quorum-monitor" data-meeting-id="<?= $meeting['id'] ?>" class="quorum-banner <?= $quorum['is_quorum_reached'] ? 'met' : 'unmet' ?>">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <div>
            <span class="badge <?= $quorum['is_quorum_reached'] ? 'badge-success' : 'badge-warning' ?>" style="margin-right: 0.5rem;">
                <?= $quorum['is_quorum_reached'] ? 'QUORUM REACHED' : 'QUORUM INCOMPLETE' ?>
            </span>
            <span style="font-size: 0.9rem; color: var(--text-muted);">
                Required Threshold: <?= $quorum['quorum_threshold'] ?>% of voting organizations
            </span>
        </div>
        <span class="badge badge-secondary"><?= htmlspecialchars(strtoupper(str_replace('_', ' ', $meeting['status']))) ?></span>
    </div>

    <div style="font-size: 1.35rem; font-weight: 800; color: var(--primary); margin: 0.5rem 0;">
        <span id="quorum-banner-message"><?= htmlspecialchars($quorum['status_message']) ?></span>
    </div>

    <div class="progress-bar-container">
        <div id="quorum-progress-bar" class="progress-bar <?= $quorum['is_quorum_reached'] ? 'success' : 'warning' ?>" style="width: <?= $quorum['attendance_percentage'] ?>%;"></div>
    </div>
</div>

<!-- Presidium Quick Roll-Call / Check-in Form -->
<?php if (!empty($isAdmin) || !empty($isPresident)): ?>
    <div class="card">
        <div class="card-header">
            <h3>Presidium Roll-Call / Manual Check-In</h3>
        </div>
        <div class="card-body">
            <form action="<?= url('/meetings/set-presence') ?>" method="POST" style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
                <?= Csrf::field() ?>

                <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">

                <div style="flex: 1; min-width: 220px;">
                    <label class="form-label">Select Organization</label>
                    <select name="organization_id" id="rollcall_org_select" class="form-control" required onchange="updateMemberOptions(this.value)">
                        <option value="">-- Choose Organization --</option>
                        <?php foreach ($allOrgs as $o): ?>
                            <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?> (<?= $o['is_observer'] ? 'Observer' : $o['voting_weight'] . ' Vote' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 220px;">
                    <label class="form-label">Designated Participating Delegate</label>
                    <select name="representative_user_id" id="rollcall_user_select" class="form-control">
                        <option value="">-- Primary Delegate or First Available --</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Mark Present & Designate</button>
            </form>
        </div>
    </div>

    <script>
        const orgMembersData = <?= json_encode(array_column($allOrgs, 'members', 'id')) ?>;
        function updateMemberOptions(orgId) {
            const userSelect = document.getElementById('rollcall_user_select');
            userSelect.innerHTML = '<option value="">-- Primary Delegate or First Available --</option>';
            if (orgMembersData[orgId]) {
                orgMembersData[orgId].forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = `${m.name} ${m.surname} (${m.email}) ${m.is_primary ? '— [Primary]' : ''}`;
                    userSelect.appendChild(opt);
                });
            }
        }
    </script>
<?php endif; ?>

<!-- Detailed Attendance Roster -->
<div class="card">
    <div class="card-header">
        <h3>Assembly Attendance Roster</h3>
        <span class="badge badge-info"><?= $quorum['present_organizations'] ?> of <?= $quorum['total_organizations'] ?> Total Organizations Present</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Organization</th>
                        <th>Type & Weight</th>
                        <th>Status</th>
                        <th>Designated Representative</th>
                        <th>Check-In Details</th>
                        <?php if (!empty($isAdmin) || !empty($isPresident)): ?>
                            <th style="text-align: right;">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roster as $r): ?>
                        <tr style="<?= $r['is_present'] ? 'background: #f0fdf4;' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($r['organization_name']) ?></strong>
                                <?php if ($r['short_name']): ?>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">(<?= htmlspecialchars($r['short_name']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['is_president']): ?>
                                    <span class="badge badge-president">President</span>
                                <?php elseif ($r['is_observer']): ?>
                                    <span class="badge badge-warning">Observer</span>
                                <?php else: ?>
                                    <span class="badge badge-primary"><?= htmlspecialchars((string)$r['voting_weight']) ?> Vote(s)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['is_present']): ?>
                                    <span class="badge badge-success">✓ PRESENT</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">ABSENT</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['is_present']): ?>
                                    <strong><?= htmlspecialchars($r['representative_name'] ?? 'Assigned Representative') ?></strong>
                                    <?php if ($r['representative_email']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($r['representative_email']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">N/A (Absent)</span>
                                    <?php if ($r['primary_member_name']): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Primary: <?= htmlspecialchars($r['primary_member_name']) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['is_present']): ?>
                                    <span style="font-size: 0.8rem;"><?= htmlspecialchars(str_replace('_', ' ', (string)$r['check_in_method'])) ?></span>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars((string)$r['checked_in_at']) ?> UTC</div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <?php if (!empty($isAdmin) || !empty($isPresident)): ?>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if ($r['is_present']): ?>
                                        <form action="<?= url('/meetings/set-presence') ?>" method="POST" style="display: inline;">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">
                                            <input type="hidden" name="organization_id" value="<?= $r['organization_id'] ?>">
                                            <input type="hidden" name="mark_absent" value="1">
                                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Mark absent?');">Mark Absent</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="<?= url('/meetings/send-presence-reminders') ?>" method="POST" style="display: inline; margin-right: 0.35rem;">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">
                                            <input type="hidden" name="organization_id" value="<?= $r['organization_id'] ?>">
                                            <button type="submit" class="btn btn-warning btn-sm" title="Email attendance reminder link to all members of this delegation" onclick="return confirm('Send attendance reminder link to all members of <?= htmlspecialchars(addslashes($r['organization_name'])) ?>?');">✉️ Remind</button>
                                        </form>
                                        <form action="<?= url('/meetings/set-presence') ?>" method="POST" style="display: inline;">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">
                                            <input type="hidden" name="organization_id" value="<?= $r['organization_id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Check In</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
