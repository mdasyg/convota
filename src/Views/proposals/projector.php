<div id="projector-view" data-proposal-id="<?= $proposal['id'] ?>">
    <div class="projector-header">
        <div>
            <div style="font-size: 1.15rem; color: var(--proj-muted); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700;">
                CONVOTA GENERAL ASSEMBLY WALL
            </div>
            <div class="projector-title"><?= htmlspecialchars($proposal['title']) ?></div>
        </div>

        <div style="text-align: right;">
            <div class="projector-clock" id="projector-live-clock"><?= gmdate('D, d M Y H:i:s') ?> UTC</div>
            <div style="margin-top: 0.5rem;">
                <span class="projector-badge <?= $proposal['status'] === 'open' ? 'open' : 'closed' ?>">
                    <?= htmlspecialchars(strtoupper($proposal['status'])) ?>
                </span>
            </div>
        </div>
    </div>

    <?php if (in_array($proposal['status'], ['closed', 'archived'], true) && !empty($results)): ?>
        <!-- Big Results View -->
        <div class="projector-card">
            <h2 style="font-size: 2rem; color: var(--proj-accent); margin-bottom: 1.5rem; text-align: center;">
                CERTIFIED BALLOT RESULTS
            </h2>

            <div style="display: flex; flex-direction: column; gap: 1.5rem; max-width: 900px; margin: 0 auto;">
                <?php foreach ($results['options'] as $opt): ?>
                    <div style="background: rgba(255, 255, 255, 0.05); padding: 1.5rem 2rem; border-radius: 12px; border: 1px solid var(--proj-border);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <div style="font-size: 2rem; font-weight: 800;"><?= htmlspecialchars($opt['option_text']) ?></div>
                            <div style="font-size: 2.5rem; font-weight: 900; color: var(--proj-accent);"><?= $opt['percentage'] ?>%</div>
                        </div>
                        <div style="background: rgba(255, 255, 255, 0.1); height: 16px; border-radius: 9999px; overflow: hidden;">
                            <div style="background: var(--proj-success); height: 100%; width: <?= $opt['percentage'] ?>%;"></div>
                        </div>
                        <div style="font-size: 1.1rem; color: var(--proj-muted); margin-top: 0.5rem;">
                            <?= $opt['vote_count'] ?> votes | <?= $opt['total_weight'] ?> weight
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Big Live Participation Counters -->
        <div class="projector-counter-grid">
            <div class="projector-counter-box">
                <div class="projector-counter-number" id="proj-voted-count"><?= $monitor['total_voted'] ?></div>
                <div class="projector-counter-label">Delegations Voted</div>
            </div>
            <div class="projector-counter-box">
                <div class="projector-counter-number"><?= $monitor['total_eligible'] ?></div>
                <div class="projector-counter-label">Eligible Delegations</div>
            </div>
            <div class="projector-counter-box">
                <div class="projector-counter-number" id="proj-pct"><?= $monitor['participation_percentage'] ?>%</div>
                <div class="projector-counter-label">Turnout Rate</div>
            </div>
        </div>

        <!-- Big Delegation Roster Grid -->
        <div class="projector-card" style="margin-top: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.5rem; color: var(--proj-muted); text-transform: uppercase;">
                    Delegation Voting Status
                </h3>
                <span style="font-size: 1.1rem; color: var(--proj-muted);">
                    Ballot Selections Sealed Until Voting Ends
                </span>
            </div>

            <div class="projector-voter-grid">
                <?php foreach ($monitor['organizations'] as $o): ?>
                    <div id="proj-org-<?= $o['organization_id'] ?>" class="projector-voter-card <?= $o['has_voted'] ? 'voted' : 'pending' ?>">
                        <span><?= htmlspecialchars($o['organization_name']) ?></span>
                        <span class="status-text"><?= $o['has_voted'] ? 'VOTED' : 'PENDING' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
