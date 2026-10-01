# Convota — General Assembly Presence & Voting System
## Detailed Technical & Functional Specifications

**Project Name:** Convota (*"Voting Together"*, from Latin *con* + *vota*)  
**Repository Path:** `/projects/convota`  
**Target Platform:** PHP 8.4+, SQLite 3 (WAL mode, Foreign Keys ON)  
**Frontend Stack:** Modern Semantic HTML5, CSS3 (Mobile-first & Hall Projector views), jQuery / Fetch API  
**Security Standard:** Strict OWASP Top 10 compliance, decoupled anonymous ballots, Argon2id auth, CSRF tokens, tamper-evident audit logs  

---

## 1. Domain Model & Core Philosophy

**Convota** manages a single **Collective** composed of multiple member **Organizations**.

### 1.1 Universal Domain Agnostic
The system does not assume member organizations are universities or companies. It is purposefully engineered for:
- International Research Consortia & Academic Alliances
- Federations & Trade Associations
- Standards Organizations & Technical Working Groups
- Non-Governmental Organizations (NGOs) & Civil Societies
- Cooperative Alliances & Housing/Condominium Collectives

### 1.2 Fundamental Voting Principle
> **"The vote belongs to the organization; the designated representative is the authenticated person exercising that organization's voting right."**

- Every organization has one vote by default, or is designated as an **Observer** (0 votes / no voting rights).
- The Collective Administrator can override voting weights (e.g., granting weighted shares or granting the President Organization 2 votes).
- One organization is designated as the **President Organization**. The Admin can reassign this designation at any time.

---

## 2. Organization & Membership Lifecycle

### 2.1 Organization Data Model
- **Required:** Name (e.g. "Hellenic Open Science Alliance").
- **Optional Attributes:** Short name/code (e.g. "HOSA"), Description, Country, Website, Status (active/inactive), Notes, Created At.
- **Voting Capabilities:**
  - Voting Power (Integer/Decimal, default `1.0`, or `0.0` for observers).
  - Observer flag (can participate in presence, view public results, cannot cast ballots).
  - President flag (`is_president`).

### 2.2 Representatives & Substitutes
- Each organization has:
  1. A **Primary Delegate**.
  2. One or more **Substitute Members**.
- **Initial Setup:** Admin registers an organization and inputs its first member (Name, Surname, Email, optional Phone).
- **Onboarding:** The first member receives a set-password activation email with a secure single-use token.
- **Member Delegation & Modifications:**
  - Any verified member of an organization (or the Admin) can add new members or remove existing members (including changing or removing the primary delegate).
  - Full historical audit trail: The system records who added/removed whom, from what IP address, with timestamp and user-agent.

### 2.3 Common Organization Access Hash ("Shared Key")
Each organization possesses a unique, cryptographically strong **Common Access Hash**:
- **Equivalence:** The hash acts as an organization-level token (equivalent to a shared username & password).
- **Access Scope:** Entering via the common hash grants direct access to the organization's self-management portal:
  - Add or remove substitute members.
  - Set the active participating voter from the member roster for an upcoming or live assembly.
- **Organization Security Controls for the Common Hash:**
  - `email_on_hash_login`: Setting to immediately notify all registered members of the organization when the common hash is used.
  - **Email Hash to Members:** Admin or member can trigger sending the hash to all registered organization members.
  - **Reinitialize & Rotate Hash:** Generates a new random hash, revoking the old one, and automatically dispatches the new credentials to all members.
  - **Enable / Disable Hash Authorization:** Organization or Admin can toggle whether hash-based access is active or deactivated.

---

## 3. Assembly Presence & Dynamic Quorum

### 3.1 Designating the Active Voter
- For any meeting, each organization designates one active representative from its roster.
- Designation can be made:
  - By an individual member logged in with their email and password.
  - By someone accessing via the organization's Common Access Hash.
  - By the Presidium/Admin during roll call.
- The public roster displays:
  `[Organization Name] — [Vote Weight] — [Designated Representative Name]`
  *(e.g., `University of Western Macedonia - 1 Vote - Minas Dasygenis`)*.

### 3.2 Presence Tracking & Roll-Call
- Real-time status for Chair and Admin:
  `"Participating: 14 of 28 voting organizations (50.0% attendance)"`
- Detailed participant table:
  - Organization Name, Status (Present / Absent / Proxy)
  - Designated Representative (Name, Email)
  - Check-in method (Self digital / Desk Teller / Common Hash)
  - Check-in timestamp & IP
- **CSV Export:** One-click export of the attendance roster with full participant details.
- **Dynamic Quorum Calculation:**
  - Default: Enabled.
  - Threshold configurable (e.g. Simple Majority $>50\%$, $2/3$, or custom percentage).
  - Visual quorum meter (Green = Quorum reached, Orange/Red = Inquorate).

---

## 4. Proposals, Elections & Voting Engine

### 4.1 Proposal Setup & Configuration
The President or Admin can create proposals with:
- **Title & Description:** Detailed description/rationale (external documents referenced; no document attachment bloat).
- **Timing Modes:**
  1. *Manual:* Open & Closed directly by Chair/Admin during live meeting.
  2. *Scheduled Window:* Configured with **FROM / START** and **TO / END** datetimes (using an interactive calendar/datetime picker) allowing representatives to vote at their convenience.
- **Quorum & Majority Rules:**
  - Quorum check toggle (default: ON) and percentage requirement.
  - Majority threshold (default: 50% + 1 of votes cast, or custom percentage).
- **Voting Formats:**
  1. `YES / NO / ABSTAIN`
  2. `Multiple Choice`: Selection A / Selection B / Selection C ... / Abstain (options can be added, updated, or removed).
- **Voter Notification on Start:**
  - Option to send mass emails upon vote opening:
    - To all members with direct ballot links.
    - Or to organizations using their single shared access hash.

### 4.2 Supervised Elections & Electoral Committee
- The President or Admin can assign an **Election Committee** (a designated subgroup of specific organizations or members) to manage and monitor a specific proposal/election.
- If an election committee is assigned, only members of that committee (and Admin) can manage the vote and view sealed results.

### 4.3 Real-Time President / Admin Status Screen
- Live asynchronous updates (AJAX / jQuery polling) displaying:
  - Total votes cast vs total eligible.
  - List of organizations that have cast their vote vs. pending organizations (`Org A: Voted`, `Org B: Pending`).
- **Results Secrecy:** **The vote results/tallies are strictly hidden during the voting window.** Results are revealed **only after the vote is formally closed**.
- **Display View / Projector View:** High-visibility projector mode for meeting halls.
- **Observer Access:** Observer organizations can view the status screen and final results, but cannot cast votes.

### 4.4 Voter Notification & Intra-Organization Transparency
- Whenever an organization's vote is cast:
  - An automated email is immediately dispatched to **every registered member** of that organization:
    > *"Your organization [Name] has cast its vote for proposal '[Title]' by member [Representative Name]."*
  - Within the organization portal, every member sees a verified badge:
    `"Your organization has cast the vote by member [Representative Name] on [Date/Time]."`

### 4.5 Dual Ballot Confidentiality Modes

#### A. Open Voting (Roll-Call)
- The organization's vote and representative's name are recorded openly.
- Publicly visible in the results and assembly minutes, permanently connected with the member's name at the time of voting (even if that member leaves the organization later).

#### B. Anonymous Voting (Cryptographically Decoupled)
To ensure that an administrator with raw SQLite database access cannot determine how an organization voted:
1. **Participation Tracking:**
   `vote_participation` records `(proposal_id, organization_id, representative_id, voted_at)`.
   - Proves the organization exercised its right.
   - Prevents double voting.
   - Triggers the member notification emails.
2. **Anonymous Ballot Box:**
   `anonymous_ballots` records `(proposal_id, option_id, weight, receipt_hash, created_at)`.
   - **Zero foreign keys** to `organization_id` or `user_id`.
   - Inserted in independent, shuffled database operations.
   - `receipt_hash = SHA-256(proposal_id || option_id || secret_ballot_nonce)`.
3. **Verification Receipt:**
   - The voting representative receives their personal `receipt_hash` on screen.
   - When the vote closes, the complete list of anonymous receipts and their corresponding choices is published. Voters can independently verify their hash is included in the tally, while remaining completely private from administrators.

### 4.6 Tabulation & Display
- Results are displayed neutrally with raw counts, weights, and percentages:
  - E.g.: `YES: 18 votes (64.3%), NO: 8 votes (28.6%), ABSTAIN: 2 votes (7.1%)`
  - E.g.: `Option A: 12 votes (42.9%), Option B: 10 votes (35.7%), Option C: 6 votes (21.4%)`
- The system **does not arbitrarily declare a winner**; it calculates the exact math and presents the certified quorum and majority benchmarks.

---

## 5. CSV Import & Export Specification

### 5.1 Roster CSV Import
- Import format:
  `A/a, name, surname, email, organization, [telephone], [vote_weight]`
- **Smart Duplicate & Conflict Resolution Engine:**
  1. *New Member & New Organization:* Both created automatically.
  2. *Existing Member with Same Organization:* Detected and flagged. A checkbox allows updating or skipping without creating duplicates.
  3. *Conflict Detection (Existing Member with Different Organization):*
     - Flagged with a **Prominent Red Warning**:
       `"Conflict: Member [email] is already registered under organization [Org X], but CSV specifies [Org Y]."`
     - Requires explicit administrator intervention before any data mutation occurs.
- **Preview Step:** The administrator views a parsed summary table with colored badges (New, Existing, Conflict) before executing the database commit.

### 5.2 CSV Exports
- **Attendance Export:** `organization_name, representative_name, representative_email, checkin_status, checkin_time, checkin_ip`.
- **Election Results Export:** Full breakdown of options, weights, percentages, and (for open votes) roll-call records.

---

## 6. Security Architecture & Threat Model

### 6.1 Transport & Header Security
1. **Canonical HTTPS Enforcement:**
   - Any request arriving via plain `http://` is automatically redirected with HTTP 301/302 to `https://`.
   - Handled at both `.htaccess` web server level and application middleware layer (checking `HTTPS`, `HTTP_X_FORWARDED_PROTO`, and `SERVER_PORT`).
2. **Hardened HTTP Security Headers:**
   - `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
   - `X-Frame-Options: DENY` (prevents clickjacking)
   - `X-Content-Type-Options: nosniff` (prevents MIME-type sniffing)
   - `Referrer-Policy: strict-origin-when-cross-origin`
   - `Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()`
   - `Content-Security-Policy (CSP): default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';`

### 6.2 Authentication, Sessions & CSRF
1. **Session & Cookie Hardening:**
   - Cookies configured with `HttpOnly`, `SameSite=Strict`, and `Secure=true`.
   - Explicit session regeneration (`session_regenerate_id(true)`) upon authentication, privilege change, and role assumption.
   - Idle session expiration (e.g., 30 minutes).
2. **CSRF Protection:**
   - Cryptographically random CSRF tokens generated per session and bound to form submissions.
   - Strict CSRF token validation enforced on all state-mutating requests (`POST`, `PUT`, `DELETE`).
   - Dual support for header-based CSRF (`X-CSRF-Token`) for AJAX/API requests and hidden form input for standard forms.
3. **Strict Authorization on Every API Endpoint:**
   - Every API endpoint executes an explicit access check:
     - Authentication verification (logged in user session or verified common organization hash).
     - Organization boundary check (members can only view/mutate their own organization's records; admins can access all).
     - Voting authorization (caller must be the designated representative of a voting-eligible organization for that specific proposal).
     - Election committee boundaries (only committee members or admins can view sealed election panels).

### 6.3 Anti-Replay & Idempotent Voting API
1. **Protection Against Duplicate Vote Submission:**
   - Database constraint: `UNIQUE(proposal_id, organization_id)` in `vote_participation`.
   - Concurrency control: SQLite `BEGIN IMMEDIATE TRANSACTION` ensures atomic check-and-insert, eliminating race conditions or double-click races.
2. **Idempotency Key & Single-Use Ballot Nonce:**
   - When a designated representative loads the ballot interface, an **Idempotency-Key / Ballot Nonce** is issued for that voting session.
   - If a network retry, client timeout, or duplicate submission occurs with the same idempotency key:
     - The system detects the duplicate request.
     - Instead of creating duplicate ballots or throwing an unhandled exception, it safely returns the existing confirmed receipt status.
   - Nonces cannot be replayed across different proposals or re-used after transaction settlement.

### 6.4 Explicit Voting-Session State Machine
The proposal voting process is governed by a strict, deterministic state machine:

```
                  ┌───────────────────────────────┐
                  │             DRAFT             │
                  └───────────────┬───────────────┘
                                  │ Start (Manual or Scheduled Time reached)
                                  ▼
┌──────────────┐  Pause           ┌───────────────────────────────┐
│    PAUSED    │ ◄──────────────  │             OPEN              │
│ (Ballots Off)│  ──────────────► │    (Accepting Ballots)        │
└──────────────┘  Resume          └───────────────┬───────────────┘
                                                  │ Close (Manual or Scheduled End reached)
                                                  ▼
                                  ┌───────────────────────────────┐
                                  │            CLOSED             │
                                  │ (Results Tallied & Revealed)  │
                                  └───────────────┬───────────────┘
                                                  │ Archive
                                                  ▼
                                  ┌───────────────────────────────┐
                                  │           ARCHIVED            │
                                  │    (Immutable Historical)     │
                                  └───────────────────────────────┘
```

**State Transition Guards & Invariants:**
1. **`DRAFT -> OPEN`:** Permitted only by Admin/Chair/Committee; requires at least 2 options defined and quorum/majority rules configured. If scheduled, transitions automatically when `CURRENT_TIMESTAMP >= start_time`.
2. **`OPEN` State Invariant:** Ballots can ONLY be cast when status is strictly `OPEN`. If `timing_mode = 'scheduled'`, the request timestamp must strictly satisfy: `start_time <= NOW <= end_time`.
3. **`OPEN -> CLOSED`:** Seals the ballot box. Once `CLOSED`, no further votes can ever be accepted. The tally is computed and revealed.
4. **`CLOSED -> ARCHIVED`:** Locks the proposal permanently into certified historical records.
5. **No Invalid Transitions:** Direct transitions such as `DRAFT -> CLOSED` or `CLOSED -> OPEN` without an explicit, audited admin override are strictly forbidden.

### 6.5 Tamper-Evident Audit Logging
- Every sensitive event (member added/removed, organization modified, common hash rotated/used, vote opened/closed, ballot cast) is recorded in `audit_logs` with actor, IP, timestamp, and SHA-256 integrity hash chaining (`prev_hash` -> `entry_hash`).

---

## 7. SQLite Database Schema Design

```sql
-- 1. Organizations
CREATE TABLE organizations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    short_name TEXT,
    description TEXT,
    country TEXT,
    website TEXT,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    voting_weight REAL NOT NULL DEFAULT 1.0,
    is_observer INTEGER NOT NULL DEFAULT 0,
    is_president INTEGER NOT NULL DEFAULT 0,
    common_hash TEXT NOT NULL UNIQUE,
    hash_auth_enabled INTEGER NOT NULL DEFAULT 1,
    email_on_hash_login INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. Users (Members and System Admins)
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER REFERENCES organizations(id) ON DELETE SET NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT,
    name TEXT NOT NULL,
    surname TEXT NOT NULL,
    telephone TEXT,
    is_primary INTEGER NOT NULL DEFAULT 0,
    system_role TEXT NOT NULL DEFAULT 'member' CHECK (system_role IN ('admin', 'president', 'member')),
    reset_token TEXT,
    reset_token_expires_at DATETIME,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_by INTEGER REFERENCES users(id),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. Meetings / Assemblies
CREATE TABLE meetings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    location TEXT,
    scheduled_at DATETIME NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'registration', 'in_session', 'concluded')),
    require_quorum INTEGER NOT NULL DEFAULT 1,
    quorum_percentage REAL NOT NULL DEFAULT 50.0,
    created_by INTEGER NOT NULL REFERENCES users(id),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    concluded_at DATETIME
);

-- 4. Meeting Presence & Designated Representative
CREATE TABLE meeting_presence (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    representative_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    is_present INTEGER NOT NULL DEFAULT 1,
    check_in_method TEXT NOT NULL CHECK (check_in_method IN ('self_login', 'common_hash', 'admin_rollcall')),
    checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checked_in_ip TEXT,
    UNIQUE(meeting_id, organization_id)
);

-- 5. Proposals / Elections
CREATE TABLE proposals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    meeting_id INTEGER REFERENCES meetings(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    description TEXT,
    voting_type TEXT NOT NULL CHECK (voting_type IN ('yes_no_abstain', 'multiple_choice')),
    is_secret INTEGER NOT NULL DEFAULT 0,
    timing_mode TEXT NOT NULL DEFAULT 'manual' CHECK (timing_mode IN ('manual', 'scheduled')),
    start_time DATETIME,
    end_time DATETIME,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'open', 'paused', 'closed', 'archived')),
    require_quorum INTEGER NOT NULL DEFAULT 1,
    quorum_percentage REAL NOT NULL DEFAULT 50.0,
    majority_percentage REAL NOT NULL DEFAULT 50.0,
    notify_members_on_start INTEGER NOT NULL DEFAULT 0,
    created_by INTEGER NOT NULL REFERENCES users(id),
    opened_at DATETIME,
    closed_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 6. Election Committee Assignments (Optional supervisor group)
CREATE TABLE proposal_supervisors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
    organization_id INTEGER REFERENCES organizations(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(proposal_id, organization_id, user_id)
);

-- 7. Proposal Choices / Options
CREATE TABLE proposal_options (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
    option_text TEXT NOT NULL,
    order_index INTEGER NOT NULL DEFAULT 0
);

-- 8. Organization Voting Participation (Proves organization voted & triggers notifications)
CREATE TABLE vote_participation (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
    organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    cast_by_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    voter_name_snapshot TEXT NOT NULL,
    voter_ip TEXT,
    idempotency_key TEXT UNIQUE,
    voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(proposal_id, organization_id)
);

-- 9. Open / Roll-Call Ballots (When is_secret = 0)
CREATE TABLE open_ballots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
    organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    option_id INTEGER NOT NULL REFERENCES proposal_options(id) ON DELETE CASCADE,
    representative_name TEXT NOT NULL,
    vote_weight REAL NOT NULL DEFAULT 1.0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 10. Anonymous Ballots (When is_secret = 1; zero FK links to organization or user)
CREATE TABLE anonymous_ballots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
    option_id INTEGER NOT NULL REFERENCES proposal_options(id) ON DELETE CASCADE,
    vote_weight REAL NOT NULL DEFAULT 1.0,
    receipt_hash TEXT NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 11. Tamper-Evident System Audit Trail
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    organization_id INTEGER REFERENCES organizations(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    details TEXT,
    ip_address TEXT,
    user_agent TEXT,
    prev_hash TEXT,
    entry_hash TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

---

## 8. Directory Layout

```
/projects/convota/
├── .gitignore
├── README.md
├── SPECIFICATIONS.md
├── config/
│   ├── config.php             <-- Main configuration & security constants
│   └── database.php           <-- SQLite PDO connection & PRAGMA setup
├── data/
│   ├── .htaccess              <-- Deny direct access
│   └── convota.sqlite         <-- SQLite production database
├── public/
│   ├── .htaccess              <-- Security headers & clean URL rewrite
│   ├── index.php              <-- Application Front Controller
│   ├── assets/
│   │   ├── css/
│   │   │   ├── app.css        <-- Clean responsive UI
│   │   │   └── projector.css  <-- High contrast assembly hall projector UI
│   │   └── js/
│   │       ├── app.js         <-- Interactive controls & live polling
│   │       └── projector.js   <-- Fullscreen auto-refresh display
├── src/
│   ├── App.php                <-- Application container & router
│   ├── Auth/
│   │   ├── AuthService.php    <-- Session, passwords, Argon2id, tokens
│   │   └── HashAuth.php       <-- Common organization hash authorization
│   ├── Database/
│   │   ├── Database.php       <-- PDO wrapper with transactions & WAL
│   │   ├── Migrations.php     <-- Automated table creation & schema updater
│   │   └── Seeder.php         <-- Initial admin user & demo setup
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── OrganizationController.php
│   │   ├── MeetingController.php
│   │   ├── ProposalController.php
│   │   ├── VoteController.php
│   │   ├── ImportExportController.php
│   │   └── ApiController.php
│   ├── Services/
│   │   ├── QuorumService.php  <-- Dynamic quorum & attendance calculations
│   │   ├── VotingService.php  <-- Decoupled anonymous and open voting engine
│   │   ├── MailerService.php  <-- Notification dispatcher
│   │   ├── AuditService.php   <-- Cryptographic SHA-256 hash-chained logger
│   │   └── CsvService.php     <-- Conflict-detecting CSV importer & exporter
│   └── Views/                 <-- Modular templates with strict HTML escaping
└── tests/
    └── test_suite.php         <-- Automated tests for voting, quorum & security
```
