<?php
use Convota\Security\Csrf;
?>
<div style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700;">Create Proposal / Election</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                Configure the motion wording, voting format, timing window, and quorum benchmarks.
            </p>
        </div>
        <a href="<?= url('/proposals') ?>" class="btn btn-secondary btn-sm">← Back</a>
    </div>

    <form action="<?= url('/proposals/create') ?>" method="POST">

        <?= Csrf::field() ?>

        <div class="card">
            <div class="card-header">
                <h3>1. General Motion Information</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="title">Proposal Title / Motion <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Approval of 2026 Strategic Roadmap & Budget" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="meeting_id">Associated Assembly Meeting (Optional)</label>
                    <select id="meeting_id" name="meeting_id" class="form-control">
                        <option value="">-- No Meeting (Independent Online Ballot) --</option>
                        <?php foreach ($meetings as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['title']) ?> (<?= htmlspecialchars($m['scheduled_at']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Detailed Description & Context</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Context, rationale, and resolution text..."></textarea>
                    <p class="form-text">External background documents should be shared with members by external means.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>2. Voting Format & Ballot Mode</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="voting_type">Voting Format</label>
                        <select id="voting_type" name="voting_type" class="form-control" onchange="toggleOptionsBox(this.value)">
                            <option value="yes_no_abstain">Standard: YES / NO / ABSTAIN</option>
                            <option value="multiple_choice">Multiple Choice / Candidates / Custom Selections</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="is_secret">Ballot Confidentiality</label>
                        <select id="is_secret" name="is_secret" class="form-control">
                            <option value="1">🔒 Secret Ballot (Cryptographically decoupled anonymous ballot)</option>
                            <option value="0">📢 Open Roll-Call Ballot (Recorded with organization and voter name)</option>
                        </select>
                    </div>
                </div>

                <!-- Custom Options Box for Multiple Choice -->
                <div id="custom-options-box" style="display: none; margin-top: 1rem; background: #f8fafc; padding: 1rem; border-radius: var(--radius); border: 1px solid var(--border);">
                    <label class="form-label">Multiple Choice Selections (One per line)</label>
                    <textarea name="options" class="form-control" rows="4" placeholder="Candidate Alpha&#10;Candidate Beta&#10;Candidate Gamma&#10;Abstain"></textarea>
                    <p class="form-text">Enter each candidate or choice on a new line. You can include an 'Abstain' choice if desired.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>3. Timing Mode & Schedule (Calendar)</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="timing_mode">Timing Method</label>
                    <select id="timing_mode" name="timing_mode" class="form-control" onchange="toggleTimingBox(this.value)">
                        <option value="manual">Manual (Opened and Closed live by the Presidium)</option>
                        <option value="scheduled">Scheduled Window (Members cast votes between Start and End datetimes)</option>
                    </select>
                </div>

                <div id="scheduled-timing-box" class="form-row" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="start_time">FROM / START Date & Time (UTC)</label>
                        <input type="datetime-local" id="start_time" name="start_time" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="end_time">TO / END Date & Time (UTC)</label>
                        <input type="datetime-local" id="end_time" name="end_time" class="form-control" value="<?= date('Y-m-d\TH:i', time() + 86400 * 2) ?>">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label" for="quorum_percentage">Required Quorum Percentage</label>
                        <input type="number" id="quorum_percentage" name="quorum_percentage" class="form-control" value="50.0" step="0.5" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="majority_percentage">Required Majority Benchmark</label>
                        <input type="number" id="majority_percentage" name="majority_percentage" class="form-control" value="50.0" step="0.5" min="1" max="100">
                        <p class="form-text">Default is 50.0% (Simple Majority). Can be set to 66.7% for 2/3 qualified majorities.</p>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="notify_members_on_start" value="1" checked>
                        <span><strong>Notify Members on Open:</strong> Automatically email all delegates when voting opens.</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>4. Electoral Committee / Supervisors (Optional)</h3>
            </div>
            <div class="card-body">
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                    Optionally assign specific organizations to supervise and monitor this election.
                </p>

                <div style="max-height: 160px; overflow-y: auto; border: 1px solid var(--border); padding: 0.5rem; border-radius: var(--radius);">
                    <?php foreach ($organizations as $org): ?>
                        <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0; cursor: pointer;">
                            <input type="checkbox" name="supervisor_organizations[]" value="<?= $org['id'] ?>">
                            <span><?= htmlspecialchars($org['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <a href="<?= url('/proposals') ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-success">Create Proposal</button>
        </div>

    </form>
</div>

<script nonce="<?= \Convota\Security\SecurityHeaders::getNonce() ?>">
    function toggleOptionsBox(type) {
        document.getElementById('custom-options-box').style.display = (type === 'multiple_choice') ? 'block' : 'none';
    }
    function toggleTimingBox(mode) {
        document.getElementById('scheduled-timing-box').style.display = (mode === 'scheduled') ? 'grid' : 'none';
    }
</script>
