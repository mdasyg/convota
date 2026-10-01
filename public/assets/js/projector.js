/**
 * Convota Projector Auto-Refresh & Fullscreen Helper
 */

document.addEventListener('DOMContentLoaded', function () {
    const clockEl = document.getElementById('projector-live-clock');
    if (clockEl) {
        setInterval(() => {
            const now = new Date();
            clockEl.innerText = now.toUTCString().replace('GMT', 'UTC');
        }, 1000);
    }

    // Auto refresh projector status every 4 seconds
    const proposalEl = document.getElementById('projector-view');
    if (proposalEl) {
        const propId = proposalEl.getAttribute('data-proposal-id');
        setInterval(() => {
            fetch(`/api/voting-status?proposal_id=${propId}`)
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.monitor) {
                        const m = d.monitor;
                        const vEl = document.getElementById('proj-voted-count');
                        const pEl = document.getElementById('proj-pct');
                        if (vEl) vEl.innerText = m.total_voted;
                        if (pEl) pEl.innerText = m.participation_percentage + '%';

                        if (m.organizations) {
                            m.organizations.forEach(o => {
                                const card = document.getElementById(`proj-org-${o.organization_id}`);
                                if (card) {
                                    if (o.has_voted) {
                                        card.className = 'projector-voter-card voted';
                                        const statusText = card.querySelector('.status-text');
                                        if (statusText) statusText.innerText = 'VOTED';
                                    }
                                }
                            });
                        }
                    }
                })
                .catch(e => console.error(e));
        }, 4000);
    }
});
