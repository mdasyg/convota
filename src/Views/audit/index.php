<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 700;">Tamper-Evident System Audit Trail</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Cryptographic SHA-256 hash-chained log of all administrative actions, roster mutations, and ballot events.
        </p>
    </div>

    <div>
        <?php if ($isChainIntact): ?>
            <span class="badge badge-success" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                🛡️ Cryptographic Chain Intact (SHA-256 Verified)
            </span>
        <?php else: ?>
            <span class="badge badge-danger" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                ⚠️ TAMPERING DETECTED: Broken Hash Chain!
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Audit Log History (Last 200 Events)</h3>
        <span class="badge badge-secondary"><?= count($logs) ?> Events</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Action</th>
                        <th>Actor / User</th>
                        <th>Organization</th>
                        <th>IP Address</th>
                        <th>Timestamp (UTC)</th>
                        <th>SHA-256 Entry Hash</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td>#<?= $l['id'] ?></td>
                            <td>
                                <?php if ($l['action'] === 'email_sent'): ?>
                                    <strong style="font-size: 0.85rem; color: #2563eb;">✉️ email_sent</strong>
                                <?php else: ?>
                                    <strong style="font-size: 0.85rem; color: var(--primary);"><?= htmlspecialchars($l['action']) ?></strong>
                                <?php endif; ?>
                                <?php if ($l['details']): ?>
                                    <?php 
                                        $detailData = json_decode($l['details'], true);
                                        $displayDetail = is_array($detailData) && isset($detailData['event_type']) 
                                            ? "Type: {$detailData['event_type']} | To: " . ($detailData['recipient'] ?? '') . " | Subj: " . ($detailData['subject'] ?? '')
                                            : $l['details'];
                                    ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($l['details']) ?>">
                                        <?= htmlspecialchars($displayDetail) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($l['user_name'] ?? 'System / Anonymous') ?></td>
                            <td><?= htmlspecialchars($l['organization_name'] ?? '—') ?></td>
                            <td><code style="font-size: 0.8rem;"><?= htmlspecialchars($l['ip_address'] ?? '—') ?></code></td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($l['created_at']) ?></td>
                            <td style="font-family: monospace; font-size: 0.75rem;">
                                <?= substr($l['entry_hash'], 0, 16) ?>...
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
