<?php
use Convota\Security\Csrf;
?>
<div style="max-width: 900px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700;">CSV Operations (Import & Export)</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Export collective roster data for external tooling or import organizations and delegates in bulk with conflict detection.
        </p>
    </div>

    <!-- Export Section -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <h3>📥 Export Database to CSV</h3>
        </div>
        <div class="card-body">
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                Download current records from the database. The exported CSV file can be used for reporting, offline review, or integration with external administrative programs.
            </p>

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem;">
                    <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer; user-select: none; margin-bottom: 0;">
                        <input type="checkbox" id="csvExportIncludeHash" style="margin-top: 0.25rem;" onchange="updateCsvExportLinks(this.checked)">
                        <div>
                            <strong style="font-size: 0.9rem;">Include Organization Authorization Key / Hash in output file</strong>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem;">
                                Appends the shared Organization Access Key (<code>Authorization Hash</code>) to each line. Useful for automated credential handoffs or third-party mailing systems. (Default: <strong>OFF</strong>).
                            </div>
                        </div>
                    </label>
                </div>

                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <a href="<?= url('/csv/export-members') ?>" id="btnExportMembers" class="btn btn-primary">
                        📥 Export All Members Roster CSV
                    </a>
                    <a href="<?= url('/csv/export-organizations') ?>" class="btn btn-secondary">
                        📥 Export Organizations CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Section -->
    <form action="<?= url('/csv/preview') ?>" method="POST" enctype="multipart/form-data">
        <?= Csrf::field() ?>

        <div class="card">
            <div class="card-header">
                <h3>📤 Bulk Import from CSV</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="csv_file">Upload CSV File</label>
                    <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv,text/csv">
                </div>

                <div style="text-align: center; margin: 1rem 0; color: var(--text-muted); font-weight: 600;">
                    — OR PASTE CSV TEXT DIRECTLY —
                </div>

                <div class="form-group">
                    <label class="form-label" for="csv_text">Paste CSV Content</label>
                    <textarea id="csv_text" name="csv_text" class="form-control" rows="8" placeholder="A/a,name,surname,email,organization,telephone,vote_weight&#10;1,Minas,Dasygenis,mdasyg@email.vlsi.gr,University of Western Macedonia,+30 24610 56501,1.0&#10;2,Elena,Rostova,elena.rostova@email.vlsi.gr,European Open Research Network,+32 2 555 0101,1.0"></textarea>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; margin-top: 1rem;">
                    <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--primary); margin-bottom: 0.25rem;">Supported CSV Columns</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                        <code>A/a, name, surname, email, organization, [telephone], [vote_weight]</code>
                    </p>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                        💡 Lines identical to existing database records will be automatically detected and counted without displaying duplicate rows in the preview.
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <a href="<?= url('/organizations') ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                Preview & Validate Conflicts →
            </button>
        </div>
    </form>
</div>

<script>
function updateCsvExportLinks(includeHash) {
    const baseMembersUrl = '<?= url('/csv/export-members') ?>';
    const btn = document.getElementById('btnExportMembers');
    if (btn) {
        btn.href = includeHash ? (baseMembersUrl + '?include_auth_hash=1') : baseMembersUrl;
    }
}
</script>
