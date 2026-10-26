# Convota (Voting Together)

<p align="center">
  <img src="docs/images/convota_hero_banner.svg" alt="Convota — Voting Together" width="100%">
</p>

**Convota** (*"Voting Together"*, from Latin *con* + *vota*) is an open, secure, and privacy-preserving General Assembly Presence and Voting Information System built with **native PHP 8.4** and **SQLite3 (WAL mode)** with zero third-party framework dependencies.

Designed for international research consortia, academic senates, university general assemblies, federations, standards organizations, cooperatives, and NGOs.

> **Core Axiom:** *The vote belongs to the organization; the designated representative is the authenticated person exercising that organization's voting right.*

---

## Visual Showcase Gallery & Architecture

### 1. System Architecture & Data Flow
<p align="center">
  <img src="docs/images/convota_architecture.svg" alt="Convota System Architecture &amp; Data Flow" width="100%">
</p>

### 2. Live Assembly Dashboard
<p align="center">
  <img src="docs/images/convota_dashboard_preview.svg" alt="Convota Assembly Dashboard Preview" width="100%">
</p>

### 3. General Assembly Presence & Roll-Call Registry
<p align="center">
  <img src="docs/images/convota_presence_preview.svg" alt="Convota General Assembly Presence and Quorum Registry" width="100%">
</p>

### 4. Delegation Portal & Shared Access Key Management
<p align="center">
  <img src="docs/images/convota_portal_preview.svg" alt="Convota Delegation Portal &amp; Shared Access Key Security" width="100%">
</p>

### 5. Smart CSV Import & Three-Way Conflict Analysis
<p align="center">
  <img src="docs/images/convota_csv_conflict_preview.svg" alt="Convota Smart CSV Conflict Detection &amp; Auto-Bypass" width="100%">
</p>

### 6. Live Voting Ballot & Cryptographic Receipt Verification
<p align="center">
  <img src="docs/images/convota_ballot_preview.svg" alt="Convota Live Voting Ballot &amp; Receipt Verification Tool" width="100%">
</p>

### 7. Privacy-Preserving Secret Decoupled Ballot Architecture
<p align="center">
  <img src="docs/images/convota_secret_ballot.svg" alt="Convota Secret Decoupled Ballot Architecture" width="100%">
</p>

### 8. Assembly Hall Big-Screen Projector Mode
<p align="center">
  <img src="docs/images/convota_projector_preview.svg" alt="Convota Assembly Hall Projector Display" width="100%">
</p>

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
   - **Presidium Attendance Reminders:** President or Administrator can dispatch reminder links (with discovered absolute URLs and direct delegation keys) to every member of unconfigured organizations (batch or individual delegation).
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
   - Cryptographic pre-commit integrity: Form state signed via HMAC-SHA256 to prevent client-side parameter tampering.

6. **Defense-in-Depth Security & OWASP Top 10 Hardening:**
   - SQLite WAL mode (`PRAGMA journal_mode = WAL;`, `foreign_keys = ON;`, `busy_timeout = 5000;`).
   - Canonical HTTPS redirection and HSTS.
   - Strict server-side session identity resolution in voting controllers (eliminating IDOR).
   - Nonce-based Content Security Policy (`script-src 'self' 'nonce-...'`).
   - Whitelist-restricted CORS policy on API routes (`arch.ece.uowm.gr`, `uowm.gr`, `localhost`).
   - Automatic redaction of security tokens and hashes from application logs.
   - Open redirect sanitization for internal application routing.
   - SQLite-backed rate limiting on user password login and shared organization hash endpoints.
   - Argon2id password hashing, secure session management (`HttpOnly`, `SameSite=Strict`, `Secure`).
   - CSRF protection on all mutating requests (form token and `X-CSRF-Token` header).
   - Tamper-evident audit trail with SHA-256 hash chaining.

---

## Quickstart & Local Run

### 1. Start Built-in Web Server
```bash
cd /projects/convota
php -S 0.0.0.0:8000 -t public/
```
Then navigate to: `http://localhost:8000` (or `https://` if behind a reverse proxy).

### 2. Default Seeded Credentials

> [!IMPORTANT]
> **Centralized Testing Mailbox Invariant:**
> The ONLY email authorized for testing, automated test suites, dummy delegates, test organizations, and mock data is **`convota@email.vlsi.gr`** (with **`adminconvota@email.vlsi.gr`** strictly reserved for super-admin).
> Fake external domains (such as `@example.com` or dummy addresses) are strictly forbidden as they trigger upstream MTA delivery rejections (`554 5.7.1 Lazy spammer sending to obviously bogus addresses`).

#### Super Administrator:
- **Email:** `adminconvota@email.vlsi.gr`
- **Password:** `AdminConvota2026!` (configurable via `CONVOTA_ADMIN_PASSWORD` environment variable)

#### Sample Member Organizations:
- **University of Western Macedonia** (President Organization, 1 Vote)
  - Primary Delegate: `convota@email.vlsi.gr` (Minas Dasygenis) / `MemberPassword2026!`
  - Substitute: `convota@email.vlsi.gr` (Konstantinos Stergiou)
- **European Open Research Network** (1 Vote)
  - Primary Delegate: `convota@email.vlsi.gr` (Elena Rostova) / `MemberPassword2026!`
  - Substitute: `convota@email.vlsi.gr` (Marcus Vogel)
- **International Standards Collective** (2 Votes)
  - Primary Delegate: `convota@email.vlsi.gr` (Sophie Dubois) / `MemberPassword2026!`
- **Global Environmental Council** (Observer, 0 Votes)
  - Delegate: `convota@email.vlsi.gr` (Astrid Lindqvist) / `MemberPassword2026!`

---

## Deployment & Path Portability

Convota is fully portable and operates out-of-the-box in two distinct topologies without manual code adjustments:

1. **Subfolder / Subpath Mode** (e.g. `https://arch.ece.uowm.gr/convota/`):
   - Fully supported via frontend reverse proxy or local Nginx alias.
   - The application dynamically detects `/convota` as the base path via `App::detectBasePath()`, `$_SERVER['REQUEST_URI']`, or `X-Forwarded-Prefix`.
   - All internal links, stylesheets, scripts, forms, and AJAX API requests are generated using `url('/relative-path')`.
2. **Root / Subdomain Mode** (e.g. `http://convota.domain.com/` or `http://localhost:8000/`):
   - Operates cleanly at document root with an empty base path (`""`).
   - Both web server configurations support clean routing to `index.php`.

### Frontend Web Server Reverse Proxy (`convota.conf`)

For public deployment behind a forward-facing Nginx reverse proxy (e.g., FreeBSD `/usr/local/etc/nginx/locations/` or Linux `/etc/nginx/locations/`), use the included [`convota.conf`](file:///projects/convota/convota.conf):

```bash
# Copy to frontend server locations directory:
# FreeBSD:
cp convota.conf /usr/local/etc/nginx/locations/convota.conf
# Linux:
cp convota.conf /etc/nginx/locations/convota.conf

# Verify and reload frontend Nginx:
nginx -t && nginx -s reload
```

**Key features of `convota.conf`:**
- Enforces canonical trailing slash (`/convota` -> `/convota/`).
- Uses `location ^~ /convota/` modifier to prevent outer regex locations (e.g. `~ \.php$`) from hijacking subpath requests.
- Passes `X-Forwarded-Prefix /convota` and `X-Forwarded-Proto $scheme` for flawless subpath and HTTPS link generation.
- Configures 50MB file size limit for CSV roster imports.
- Sets HTTP/1.1 persistent connections and optimized buffering for real-time assembly hall displays.

---

## Automated Test Suite

Convota includes a comprehensive automated test runner covering all 7 test phases (175 automated tests):

```bash
php /projects/convota/tests/run_all_tests.php
```

Individual test suites:
- `php tests/test_phase1.php` — Database WAL mode, Migrations, Seeder, Audit hash-chaining, Auth, CSRF (31 tests)
- `php tests/test_phase2.php` — Organizations, Member Roster, CSV Conflict Engine, CSV Exports (39 tests)
- `php tests/test_phase3.php` — Meetings, Presence Tracking, Designated Representative, Dynamic Quorum (27 tests)
- `php tests/test_phase4.php` — Voting Engine, Secret Decoupled Ballots, Idempotency, Anti-Replay, Notifications (27 tests)
- `php tests/test_phase5.php` — REST APIs (`/api/quorum`, `/api/voting-status`, `/api/vote`), Web Dispatcher, Security Headers (12 tests)
- `php tests/test_nginx_routing.php` — Subpath & Subdomain Portability, Base Path Auto-Detection, URL Helpers (16 tests)
- `php tests/test_security_fixes.php` — OWASP Top 10 Hardening, IDOR Ballot Defense, Open Redirect, HMAC, Rate Limiting, Redaction (23 tests)

---

## Documentation & User Guides

Detailed documentation and operational manuals are available in the [`documentation/`](file:///projects/convota/documentation) directory:

* 📘 **[Administrator & Presidium Guide](file:///projects/convota/documentation/ADMINISTRATOR_GUIDE.md):** Complete manual for administrators and assembly presidium officers covering collective configuration, delegation roster management, shared access key rotation, smart CSV imports/exports, dynamic quorum calculation, presidium attendance reminders, secret/open voting sessions, and cryptographic audit log verification.
* 📗 **[Member & Delegate User Guide](file:///projects/convota/documentation/MEMBER_GUIDE.md):** Step-by-step instructions for organization delegates covering personal login, shared access key one-click URLs, delegation roster management, meeting presence registration, designating active voters, secret balloting, saving cryptographic voter receipts, and public receipt verification.
* 🛡️ **[Security Audit & OWASP Assessment](file:///root/SECURITY_AUDIT_convota.md):** Complete vulnerability audit and remediation report detailing all 13 security fixes.

---

## Project Structure

```
/projects/convota/
├── .gitignore
├── CHANGELOG.md               <-- Detailed version and phase changelog
├── README.md                  <-- Project overview and quickstart guide (English)
├── README-GR.md               <-- Project overview and quickstart guide (Greek)
├── SPECIFICATIONS.md          <-- Complete technical and security specifications
├── AGENTS.md                  <-- Autonomous agent rules & testing protocols
├── convota.conf               <-- Frontend Nginx reverse proxy configuration
├── docs/                      <-- Indicative architecture diagrams and UI vector assets
│   └── images/                <-- Scalable vector showcase gallery
├── documentation/             <-- Comprehensive user and administration guides
│   ├── ADMINISTRATOR_GUIDE.md <-- Detailed guide for system administrators & presidium
│   └── MEMBER_GUIDE.md        <-- Step-by-step guide for delegates and voting members
├── config/
│   └── config.php             <-- Application constants, mail, and security settings
├── data/
│   ├── .htaccess              <-- Deny all direct web access
│   ├── convota.sqlite         <-- SQLite production database (WAL mode)
│   └── mail.log               <-- Notification log with token redaction
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
│   │   └── HashAuth.php       <-- Shared organization access key manager & rate limiting
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
    ├── run_all_tests.php      <-- Master runner (all 175 tests across 7 phases)
    ├── test_phase1.php
    ├── test_phase2.php
    ├── test_phase3.php
    ├── test_phase4.php
    ├── test_phase5.php
    ├── test_nginx_routing.php
    └── test_security_fixes.php
```
