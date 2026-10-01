<?php
use Convota\Security\Csrf;
?>
<div style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700;">Add New Organization</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                Only the organization name and first member details are required. All other managerial fields are optional.
            </p>
        </div>
        <a href="/organizations" class="btn btn-secondary btn-sm">← Back to Organizations</a>
    </div>

    <form action="/organizations/create" method="POST">
        <?= Csrf::field() ?>

        <div class="card">
            <div class="card-header">
                <h3>1. Organization Information</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="name">Organization Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Hellenic Open Science Alliance" required autofocus>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="short_name">Short Name / Acronym (Optional)</label>
                        <input type="text" id="short_name" name="short_name" class="form-control" placeholder="e.g. HOSA">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="country">Country (Optional)</label>
                        <input type="text" id="country" name="country" class="form-control" placeholder="e.g. Greece">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="website">Website URL (Optional)</label>
                        <input type="url" id="website" name="website" class="form-control" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="voting_weight">Voting Power / Weight</label>
                        <input type="number" id="voting_weight" name="voting_weight" class="form-control" value="1.0" step="0.1" min="0">
                        <p class="form-text">Default is 1 vote. Can be overridden by Admin (e.g. 2 votes or weighted shares).</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description / Notes (Optional)</label>
                    <textarea id="description" name="description" class="form-control" rows="2" placeholder="Brief overview of the organization..."></textarea>
                </div>

                <div class="form-row" style="margin-top: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_observer" value="1">
                        <span><strong>Observer Organization</strong> (0 votes; participates in presence and views results, cannot cast ballots)</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>2. First Member (Primary Delegate)</h3>
            </div>
            <div class="card-body">
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">
                    This member will automatically receive a welcome email with instructions on how to set a password.
                </p>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_member_name">First Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="first_member_name" name="first_member_name" class="form-control" placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="first_member_surname">Surname <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="first_member_surname" name="first_member_surname" class="form-control" placeholder="Surname" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_member_email">Email Address <span style="color: var(--danger);">*</span></label>
                        <input type="email" id="first_member_email" name="first_member_email" class="form-control" placeholder="delegate@organization.org" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="first_member_telephone">Telephone Number (Optional)</label>
                        <input type="tel" id="first_member_telephone" name="first_member_telephone" class="form-control" placeholder="+30 24610 56500">
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <a href="/organizations" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Organization & Send Welcome Email</button>
        </div>
    </form>
</div>
