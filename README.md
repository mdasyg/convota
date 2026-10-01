# Convota (Voting Together)

**Convota** (*"Voting Together"*, from Latin *con* + *vota*) is an open, secure, and privacy-preserving General Assembly Presence and Voting Information System built with **PHP 8.4** and **SQLite3**.

Designed for international research consortia, academic senates, federations, standards organizations, cooperatives, and NGOs.

> **Core Axiom:** *The vote belongs to the organization; the designated representative is the authenticated person exercising that organization's voting right.*

---

## Key Features

1. **Collective & Organization Model:**
   - Single Collective installation managing multiple member Organizations.
   - Configurable voting weights (default 1 vote, weighted shares, or 0 votes for Observers).
   - Dynamic **President Organization** designation (reassignable by Administrator).
   - Public assembly roster displaying: `[Organization] — [Vote Weight] — [Designated Representative]`.

2. **Delegation Roster & Shared Access Keys:**
   - Primary delegates and substitute members.
   - Any member or admin can add/remove substitutes (even the primary delegate).
   - **Shared Organization Access Key:** Unique shared token per organization with granular security controls (email key to members, reinitialize/rotate key, enable/disable key, email alerts on key usage).
   - Historical accountability: All membership additions and removals record actor, target, client IP, and timestamps in a cryptographic audit trail.

3. **Presence & Real-Time Dynamic Quorum:**
   - Active representative designation per meeting (via member login, shared key, or presidium roll-call).
   - Dynamic quorum monitoring (e.g. `Participating 14 of 28 voting organizations (50.0% attendance)`).
   - Auto-refreshing presence screen (AJAX/Fetch polling).
   - Attendance export to CSV.

4. **Dual-Mode Voting Engine:**
   - **Secret Decoupled Ballot:** `anonymous_ballots` has **zero relational linkage or foreign keys** to voters or organizations. Voters receive a cryptographic SHA-256 receipt hash for independent tally verification.
   - **Open Roll-Call Ballot:** Records organization and representative name in public minutes.
   - **Anti-Replay & Idempotent API:** SQLite `BEGIN IMMEDIATE TRANSACTION`, unique constraints, and single-use ballot nonces prevent duplicate voting or replay attacks.
   - **Deterministic State Machine:** `DRAFT` $\rightarrow$ `OPEN` $\rightleftharpoons$ `PAUSED` $\rightarrow$ `CLOSED` $\rightarrow$ `ARCHIVED`.
   - **Sealed Results:** Vote selections are strictly hidden during voting; mathematical tallies and percentages are revealed only after voting closes.
   - **Intra-Organization Transparency:** Automated email alert sent to all members of an organization when their vote is cast.
   - **Assembly Hall Projector Mode:** Fullscreen, high-contrast big-screen display for assembly halls.

5. **Smart CSV Import with Conflict Detection:**
   - Format: `A/a, name, surname, email, organization, [telephone], [vote_weight]`
   - Visual conflict engine: Flags existing emails registered under a *different organization* with a **prominent red warning** and skips them automatically.
   - Allows checkbox resolution to update existing members in the *same organization*.

6. **Defense-in-Depth Security:**
   - SQLite WAL mode (`PRAGMA journal_mode = WAL;`, `foreign_keys = ON;`, `busy_timeout = 5000;`).
   - Canonical HTTPS redirection and HSTS.
   - Argon2id password hashing, secure session management (`HttpOnly`, `SameSite=Strict`, `Secure`).
   - CSRF protection on all mutating requests (form token and `X-CSRF-Token` header).
   - Tamper-evident audit trail with SHA-256 hash chaining.
   - Protected database and logs via `.htaccess` (`Require all denied`).

---

## Quickstart & Local Run

### 1. Start Built-in Web Server
```bash
cd /projects/convota
php -S 0.0.0.0:8000 -t public/
```
Then navigate to: `http://localhost:8000` (or `https://` if behind a reverse proxy).

### 2. Default Seeded Credentials

#### Super Administrator:
- **Email:** `admin@convota.local`
- **Password:** `AdminConvota2026!`

#### Sample Member Organizations:
- **University of Western Macedonia** (President Organization, 1 Vote)
  - Delegate: `mdasyg@vlsi.gr` / `MemberPassword2026!`
- **European Open Research Network** (1 Vote)
  - Delegate: `elena.rostova@eorn.org` / `MemberPassword2026!`
- **International Standards Collective** (2 Votes)
  - Delegate: `sophie@standards-collective.org` / `MemberPassword2026!`
- **Global NGO Environmental Council** (Observer, 0 Votes)
  - Delegate: `astrid@gec-council.ngo` / `MemberPassword2026!`

---

## Automated Test Suite

Convota includes a comprehensive test suite covering all 5 phases:

```bash
php /projects/convota/tests/run_all_tests.php
```

You can also run individual test suites:
- `php tests/test_phase1.php` — Database WAL mode, Migrations, Seeder, Audit hash-chaining, Auth, CSRF
- `php tests/test_phase2.php` — Organizations, Member Roster, CSV Conflict Engine, CSV Exports
- `php tests/test_phase3.php` — Meetings, Presence Tracking, Designated Representative, Dynamic Quorum
- `php tests/test_phase4.php` — Voting Engine, Secret Decoupled Ballots, Idempotency, Anti-Replay, Notifications
- `php tests/test_phase5.php` — REST APIs (`/api/quorum`, `/api/voting-status`, `/api/vote`), Web Dispatcher, Security Headers

---

## Project Structure

```
/projects/convota/
├── .gitignore
├── CHANGELOG.md               <-- Detailed version and phase changelog
├── README.md                  <-- Project overview and quickstart guide
├── SPECIFICATIONS.md          <-- Complete technical and security specifications
├── config/
│   └── config.php             <-- Application constants, mail, and security settings
├── data/
│   ├── .htaccess              <-- Deny all direct web access
│   ├── convota.sqlite         <-- SQLite production database (WAL mode)
│   └── mail.log               <-- Notification log for member alerts
├── public/
│   ├── .htaccess              <-- Security headers, clean URL rewrites, HTTPS redirect
│   ├── index.php              <-- Front controller entry point
│   └── assets/
│       ├── css/
│       │   ├── app.css        <-- Modern responsive design system
│       │   └── projector.css  <-- High-contrast hall projector theme
│       └── js/
│           ├── app.js         <-- Live polling, copy utils, CSRF integration
│           └── projector.js   <-- Fullscreen clock & live synchronization
├── src/
│   ├── App.php                <-- Application container, router & middleware
│   ├── Autoload.php           <-- PSR-4 autoloader
│   ├── Auth/
│   │   ├── AuthService.php    <-- Argon2id passwords, sessions, lockout, reset tokens
│   │   └── HashAuth.php       <-- Shared organization access key manager & security
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── OrganizationController.php
│   │   ├── MeetingController.php
│   │   ├── ProposalController.php
│   │   ├── VoteController.php
│   │   ├── CsvController.php
│   │   ├── AuditController.php
│   │   └── ApiController.php
│   ├── Database/
│   │   ├── Database.php       <-- PDO wrapper with WAL mode & savepoints
│   │   ├── Migrations.php     <-- 12 tables matching specifications
│   │   └── Seeder.php         <-- Superadmin and demonstration collective
│   ├── Models/
│   │   ├── Organization.php
│   │   ├── User.php
│   │   ├── Meeting.php
│   │   └── Proposal.php
│   ├── Security/
│   │   ├── Csrf.php
│   │   ├── SecurityHeaders.php
│   │   └── Validator.php
│   ├── Services/
│   │   ├── AuditService.php   <-- Cryptographic SHA-256 hash-chained logger
│   │   ├── CsvService.php     <-- Smart conflict-detecting CSV engine
│   │   ├── MailerService.php  <-- Intra-org and voter notification dispatcher
│   │   ├── QuorumService.php  <-- Dynamic quorum & attendance calculator
│   │   └── VotingService.php  <-- Idempotent, anti-replay dual-mode voting engine
│   └── Views/                 <-- Semantic, UTF-8 escaped HTML views
└── tests/
    ├── run_all_tests.php      <-- Master runner (all 120 tests)
    ├── test_phase1.php
    ├── test_phase2.php
    ├── test_phase3.php
    ├── test_phase4.php
    └── test_phase5.php
```
