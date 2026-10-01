<?php
use Convota\Security\Csrf;
?>
<div style="max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700;">Schedule Assembly Meeting</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                Set up a new meeting session for presence verification and voting.
            </p>
        </div>
        <a href="<?= url('/meetings') ?>" class="btn btn-secondary btn-sm">← Back</a>
    </div>

    <form action="<?= url('/meetings/create') ?>" method="POST">

        <?= Csrf::field() ?>

        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="title">Meeting Title <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" placeholder="e.g. 14th Ordinary General Assembly 2026" required autofocus>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="scheduled_at">Date & Time (UTC)</label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="location">Location / Modality</label>
                        <input type="text" id="location" name="location" class="form-control" placeholder="e.g. Kozani / Hybrid Zoom">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Meeting Agenda / Scope</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Overview of the assembly agenda..."></textarea>
                </div>

                <div class="form-row" style="margin-top: 1rem; align-items: center;">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="require_quorum" value="1" checked>
                            <span><strong>Require Quorum Verification</strong></span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="quorum_percentage">Required Quorum Percentage</label>
                        <input type="number" id="quorum_percentage" name="quorum_percentage" class="form-control" value="50.0" step="0.5" min="1" max="100">
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <a href="<?= url('/meetings') ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Schedule Meeting</button>
        </div>

    </form>
</div>
