<?php
use Convota\Security\Csrf;
?>
<div style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700;">Bulk CSV Import</h1>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Import organizations and delegate members in bulk with smart duplicate & conflict resolution.
        </p>
    </div>

    <form action="/csv/preview" method="POST" enctype="multipart/form-data">
        <?= Csrf::field() ?>

        <div class="card">
            <div class="card-header">
                <h3>1. Select CSV File</h3>
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
                    <textarea id="csv_text" name="csv_text" class="form-control" rows="8" placeholder="A/a,name,surname,email,organization,telephone,vote_weight&#10;1,Minas,Dasygenis,mdasyg@vlsi.gr,University of Western Macedonia,+30 24610 56501,1.0&#10;2,Elena,Rostova,elena.rostova@eorn.org,European Open Research Network,+32 2 555 0101,1.0"></textarea>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; margin-top: 1rem;">
                    <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--primary); margin-bottom: 0.25rem;">Supported CSV Columns</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">
                        <code>A/a, name, surname, email, organization, [telephone], [vote_weight]</code>
                    </p>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <a href="/organizations" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                Preview & Validate Conflicts →
            </button>
        </div>
    </form>
</div>
