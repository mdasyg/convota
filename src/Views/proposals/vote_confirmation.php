<div style="max-width: 650px; margin: 2rem auto;">
    <div class="card" style="border: 2px solid var(--success); text-align: center; padding: 2rem 1.5rem;">
        <span style="font-size: 3.5rem;">🗳️✅</span>
        
        <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--success); margin: 0.75rem 0 0.5rem 0;">
            Vote Successfully Cast & Recorded!
        </h1>

        <p style="color: var(--text-main); font-size: 1.05rem;">
            <?= htmlspecialchars($result['message']) ?>
        </p>

        <!-- Intra-organization notification badge -->
        <div style="background: var(--info-bg); border: 1px solid var(--info-border); color: var(--info); border-radius: var(--radius); padding: 0.75rem 1rem; margin: 1.25rem 0; font-size: 0.9rem; text-align: left;">
            ✉️ <strong>Intra-Organization Transparency:</strong> An automated confirmation notification has been dispatched to all registered members of your delegation confirming that your vote has been cast.
        </div>

        <?php if (!empty($result['receipt_hash'])): ?>
            <!-- Cryptographic Secret Ballot Receipt -->
            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem; text-align: left; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <strong style="font-size: 0.85rem; text-transform: uppercase; color: var(--primary);">
                        🔒 Cryptographic Receipt Hash (SHA-256)
                    </strong>
                    <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= htmlspecialchars($result['receipt_hash']) ?>">
                        Copy Receipt
                    </button>
                </div>
                <div style="font-family: monospace; font-size: 0.85rem; word-break: break-all; background: white; padding: 0.75rem; border-radius: 4px; border: 1px solid var(--border);">
                    <?= htmlspecialchars($result['receipt_hash']) ?>
                </div>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                    Save this hash! When the election concludes, you can verify on the public results board that your ballot was counted in the final tally without revealing your selection.
                </p>
            </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: center; gap: 0.75rem; margin-top: 1rem;">
            <a href="<?= url('/proposals/verify-receipt') ?>" class="btn btn-secondary">Test Receipt Verification</a>
            <a href="<?= url('/proposals/monitor?id=' . $proposal['id']) ?>" class="btn btn-secondary">Live Monitor</a>
            <a href="<?= url('/dashboard') ?>" class="btn btn-primary">Return to Dashboard</a>
        </div>

    </div>
</div>
