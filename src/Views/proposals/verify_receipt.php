<div style="max-width: 700px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700;">Voter Receipt Verification Board</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Verify that your secret ballot was received, cryptographically recorded, and included in the assembly tally.
        </p>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="<?= url('/proposals/verify-receipt') ?>" method="GET">
                <div class="form-group">

                    <label class="form-label" for="proposal_id">Select Proposal</label>
                    <select id="proposal_id" name="proposal_id" class="form-control" required>
                        <option value="">-- Choose Proposal --</option>
                        <?php foreach ($allProposals as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (isset($proposalId) && $proposalId === (int)$p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['title']) ?> (<?= htmlspecialchars(strtoupper($p['status'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="receipt_hash">Your Cryptographic Receipt Hash (SHA-256)</label>
                    <input type="text" id="receipt_hash" name="receipt_hash" class="form-control" placeholder="Enter or paste your receipt hash..." value="<?= htmlspecialchars($receiptHash ?? '') ?>" required style="font-family: monospace;">
                </div>

                <button type="submit" class="btn btn-primary">
                    🔍 Verify Ballot Inclusion
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($receiptHash)): ?>
        <?php if (!empty($verificationResult)): ?>
            <div class="card" style="border: 2px solid var(--success); background: #f0fdf4;">
                <div class="card-body" style="text-align: center; padding: 2rem 1.5rem;">
                    <span style="font-size: 3rem;">✅</span>
                    <h3 style="color: var(--success); font-weight: 700; margin-top: 0.5rem;">Ballot Verified as Counted!</h3>
                    <p style="color: var(--text-main); margin-top: 0.5rem; font-size: 0.95rem;">
                        This receipt hash was verified against the immutable anonymous ballot box for <strong><?= htmlspecialchars($proposal['title']) ?></strong>.
                    </p>
                    <div style="font-family: monospace; font-size: 0.8rem; background: white; padding: 0.75rem; border-radius: 4px; border: 1px solid var(--border); margin: 1rem 0; word-break: break-all;">
                        <?= htmlspecialchars($verificationResult['receipt_hash']) ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        Vote Choice: <span class="badge badge-secondary"><?= htmlspecialchars($verificationResult['option_text']) ?></span> | Weight: <?= htmlspecialchars((string)$verificationResult['vote_weight']) ?> | Timestamp: <?= htmlspecialchars($verificationResult['created_at']) ?> UTC
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="border: 2px solid var(--danger); background: var(--danger-bg);">
                <div class="card-body" style="text-align: center; padding: 2rem 1.5rem;">
                    <span style="font-size: 3rem;">❌</span>
                    <h3 style="color: var(--danger); font-weight: 700; margin-top: 0.5rem;">Receipt Hash Not Found</h3>
                    <p style="color: var(--text-main); margin-top: 0.5rem; font-size: 0.95rem;">
                        No matching ballot found for this proposal and receipt hash. Please check that you selected the correct proposal and copied the complete hash.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
