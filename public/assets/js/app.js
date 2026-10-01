/**
 * Convota Client-Side Script
 * Asynchronous AJAX Polling, Live Voting Monitor & CSRF Integration
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Setup CSRF Token for all fetch calls
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // 2. Tab Switching (Login Page)
    const tabButtons = document.querySelectorAll('[data-tab-target]');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-tab-target');
            document.querySelectorAll('.tab-content').forEach(tc => tc.style.display = 'none');
            document.querySelectorAll('[data-tab-target]').forEach(b => b.classList.remove('active'));
            
            const targetEl = document.getElementById(targetId);
            if (targetEl) targetEl.style.display = 'block';
            this.classList.add('active');
        });
    });

    // 3. Copy to Clipboard Utility
    document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', function () {
            const textToCopy = this.getAttribute('data-copy');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const originalText = this.innerText;
                    this.innerText = 'Copied!';
                    setTimeout(() => { this.innerText = originalText; }, 2000);
                });
            }
        });
    });

    // 4. Live Voting Status Polling (Proposal Monitor Screen)
    const monitorContainer = document.getElementById('live-vote-monitor');
    if (monitorContainer) {
        const proposalId = monitorContainer.getAttribute('data-proposal-id');
        const pollInterval = 3000; // 3 seconds

        function pollVotingStatus() {
            fetch(`/api/voting-status?proposal_id=${proposalId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.monitor) {
                    const m = data.monitor;
                    const totalVotedEl = document.getElementById('monitor-voted-count');
                    const totalEligibleEl = document.getElementById('monitor-eligible-count');
                    const pctEl = document.getElementById('monitor-percentage');
                    const barEl = document.getElementById('monitor-progress-bar');

                    if (totalVotedEl) totalVotedEl.innerText = m.total_voted;
                    if (totalEligibleEl) totalEligibleEl.innerText = m.total_eligible;
                    if (pctEl) pctEl.innerText = m.participation_percentage + '%';
                    if (barEl) barEl.style.width = m.participation_percentage + '%';

                    // Update organization badges
                    if (m.organizations && Array.isArray(m.organizations)) {
                        m.organizations.forEach(org => {
                            const badgeEl = document.getElementById(`org-badge-${org.organization_id}`);
                            if (badgeEl) {
                                if (org.has_voted) {
                                    badgeEl.className = 'badge badge-success';
                                    badgeEl.innerText = 'VOTED';
                                } else {
                                    badgeEl.className = 'badge badge-warning';
                                    badgeEl.innerText = 'PENDING';
                                }
                            }
                        });
                    }
                }
            })
            .catch(err => console.error("Polling error:", err));
        }

        setInterval(pollVotingStatus, pollInterval);
    }

    // 5. Live Quorum Status Polling (Meeting Presence Screen)
    const quorumContainer = document.getElementById('live-quorum-monitor');
    if (quorumContainer) {
        const meetingId = quorumContainer.getAttribute('data-meeting-id');
        const pollInterval = 4000;

        function pollQuorumStatus() {
            fetch(`/api/quorum?meeting_id=${meetingId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.quorum) {
                    const q = data.quorum;
                    const bannerEl = document.getElementById('quorum-banner-message');
                    const barEl = document.getElementById('quorum-progress-bar');
                    const boxEl = document.getElementById('quorum-banner-box');

                    if (bannerEl) bannerEl.innerText = q.status_message;
                    if (barEl) {
                        barEl.style.width = q.attendance_percentage + '%';
                        barEl.className = q.is_quorum_reached ? 'progress-bar success' : 'progress-bar warning';
                    }
                    if (boxEl) {
                        boxEl.className = q.is_quorum_reached ? 'quorum-banner met' : 'quorum-banner unmet';
                    }
                }
            })
            .catch(err => console.error("Quorum polling error:", err));
        }

        setInterval(pollQuorumStatus, pollInterval);
    }
});
