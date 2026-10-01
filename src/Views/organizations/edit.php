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
        <a href="/organizations" class="btn btn-secondary btn-sm">← Back</a>
    </div>

    <form action="/organizations/edit" method="POST">
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

        <div style="display: flex; justify-content: space-between; align-items: center;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
