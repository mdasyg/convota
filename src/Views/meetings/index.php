<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">General Assembly Meetings</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Assemblies scheduled, registration sessions, and presence records.
        </p>
    </div>
    <?php if (!empty($isAdmin) || !empty($isPresident)): ?>
        <a href="/meetings/create" class="btn btn-primary btn-sm">➕ Schedule Assembly Meeting</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Assembly Meeting</th>
                    <th>Scheduled Date</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Attendance</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($meetings)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            No assembly meetings recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($meetings as $m): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($m['title']) ?></strong>
                                <?php if ($m['description']): ?>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars(substr($m['description'], 0, 80)) ?>...</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($m['scheduled_at']) ?></td>
                            <td><?= htmlspecialchars($m['location'] ?? 'Online / Hybrid') ?></td>
                            <td>
                                <span class="badge badge-<?= $m['status'] === 'in_session' ? 'success' : ($m['status'] === 'registration' ? 'warning' : 'secondary') ?>">
                                    <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $m['status']))) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= $m['attendee_count'] ?></strong> organization(s)
                            </td>
                            <td style="text-align: right;">
                                <a href="/meetings/presence?id=<?= $m['id'] ?>" class="btn btn-secondary btn-sm">
                                    Presence & Quorum →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
