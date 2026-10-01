# Convota — General Assembly Presence & Voting System
## System Specification Document

**Project Name:** Convota (*"Voting Together"*, from Latin *con* + *vota*)  
**Repository Path:** `/projects/convota`  
**Primary Tech Stack:** PHP 8.4+, SQLite 3 (WAL mode), HTML5 / Modern CSS / Vanilla JS  
**Security Standard:** Zero-trust principles, cryptographic voter privacy, defense-in-depth, OWASP compliance  

---

## 1. Executive Summary & Purpose

**Convota** is a self-hosted, lightweight, and robust General Assembly (GA) information system engineered for organizations, cooperatives, academic bodies, associations, and shareholder assemblies. 

General assemblies require strict legal compliance, verified member presence, dynamic quorum calculations, proxy management, and dependable voting (both open roll-call votes and confidential/secret ballots). Convota bridges physical and remote participation with verifiable, tamper-evident proceedings, minimal infrastructure overhead, and an intuitive, mobile-friendly interface for delegates and administrators.

---

## 2. Core Stakeholders & Roles

1. **System Administrator (`superadmin`)**
   - System configuration, backup management, global user directory, audit log inspection.
2. **Assembly Presidium / Chair (`chair`)**
   - Conducts the meeting agenda, opens/closes motions, manages speaker lists, and certifies quorum and results.
3. **Scrutineer / Electoral Committee (`teller`)**
   - Verifies voter credentials, handles in-person desk check-ins, validates proxies, and audits ballot counts.
4. **Member / Delegate (`voter`)**
   - Attends assemblies, marks presence (or attends in-person via desk QR/code), casts votes on active motions (self + authorized proxies), reviews personal vote receipts.
5. **Observer / Guest (`observer`)**
   - Read-only access to public assembly livestream/projector view, agenda items, and declared final tallies (no voting rights).

---

## 3. Key Functional Modules

```
+-----------------------------------------------------------------------------------+
|                                  CONVOTA SYSTEM                                   |
+-------------------------+-------------------------------+-------------------------+
|   1. Member & Roster    |      2. Presence & Quorum     |    3. Motions & Voting  |
| - Member directory      | - Check-in (QR / PIN / Manual)| - Secret / Open Ballots |
| - Voting weights        | - Dynamic Quorum calculation  | - Instant Tabulation    |
| - Proxy representation  | - Live attendance monitor     | - Cryptographic receipts|
+-------------------------+-------------------------------+-------------------------+
|   4. Presidium Console  |      5. Projector / Wall      |    6. Audit & Minutes   |
| - Agenda orchestration  | - Real-time big screen view   | - Tamper-evident logs   |
| - Motion opening/closing| - Quorum & tally presentation | - Certified PDF/HTML    |
+-------------------------+-------------------------------+-------------------------+
```

### 3.1. Assembly Lifecycle Management
Assemblies progress through explicit state transitions:
1. **Draft / Scheduled:** Agenda items and member lists imported; proxies registered.
2. **Registration Open:** Members check in physically or log in digitally; presence and quorum thresholds update live.
3. **In Session:** Agenda items addressed sequentially; motions opened and voted on.
4. **Adjourned / Paused:** Temporary recess; voting locked.
5. **Concluded / Certified:** Assembly locked permanently; official minutes and cryptographic audit trails archived and signed.

### 3.2. Presence, Proxies, and Quorum Engine
- **Check-In Mechanisms:**
  - *Desk Check-In:* Scrutineers scan member QR codes or search member ID with physical ID verification.
  - *Self Check-In:* Authenticated digital check-in (optional geofencing / assembly room Wi-Fi token or physical rotation PIN).
- **Proxy Vote Management:**
  - One member can represent up to $N$ other members (statutory limit configurable per assembly, typically 1 or 2).
  - Explicit delegation recorded with proof documents/timestamps.
  - When voting, delegates cast votes for themselves and their assigned proxies in a unified or step-by-step workflow.
- **Dynamic Quorum Calculation:**
  - Configurable quorum formula (e.g., Simple Majority $> 50\%$, $2/3$ qualified, or absolute minimum number of voting shares/weights).
  - Quorum recalculation logged automatically before each motion ballot opens.

### 3.3. Motions and Voting Engine
- **Motion Types:**
  - **Single Choice:** Yes / No / Abstain.
  - **Multiple Choice:** Select 1 of $K$, or select up to $M$ of $K$ options.
  - **Candidate Elections:** Preferential or standard multi-seat electoral list.
- **Ballot Confidentiality Modes:**
  1. **Secret Ballot (Blind / Decoupled):**
     - Absolute voter privacy. The system records *that* Member $A$ has voted (to prevent double voting), but stores the ballot in an unlinked table with a randomized blind token.
     - The voter receives a cryptographic verification receipt hash allowing them to verify their vote was included in the tally without revealing *how* they voted to administrators.
  2. **Open / Roll-Call Ballot:**
     - Transparency-first voting. Member names and their individual votes are recorded and published in the official minutes.
- **Vote Weights:**
  - Supports 1-person-1-vote or weighted shares (e.g., cooperative shares or apartment co-ownership percentages).
- **Anti-Collusion & Live Privacy:**
  - Live tallies can be hidden until the voting window is closed by the Chair to prevent bandwagon bias.

### 3.4. Assembly Display / Projector View
- High-contrast, clean display mode for assembly hall projectors.
- Displays current agenda topic, motion wording, live quorum status, active voting countdown timer, and final certified results.

### 3.5. Minutes & Export Generation
- Comprehensive, one-click export of the assembly protocol (HTML/PDF/JSON).
- Includes:
  - Exact attendee and proxy list at time of convening.
  - Quorum status at every motion.
  - Full text of resolutions.
  - Tabulated results with percentages and quorum confirmation.
  - Cryptographic verification summary (SHA-256 integrity hash of assembly records).

---

## 4. Security Architecture & Threat Model

Convota adopts strict defense-in-depth principles:

### 4.1. Core Security Policies
- **Authentication:** Password hashing using `PASSWORD_ARGON2ID` with fallback to `PASSWORD_BCRYPT` ($cost \ge 12$).
- **Session Protection:**
  - Session cookies configured with `HttpOnly`, `SameSite=Strict`, and `Secure` (over HTTPS).
  - Automatic session regeneration (`session_regenerate_id(true)`) upon authentication and privilege changes.
  - Inactivity timeout (e.g., 30 minutes) and single active session enforcement per member to eliminate concurrent unauthorized voting.
- **CSRF Defense:** Cryptographic, per-session/per-form CSRF tokens validated on all `POST`/`PUT`/`DELETE` requests.
- **SQL Injection Prevention:** Strictly 100% prepared statements via PHP Data Objects (`PDO`) with parameterized queries. No string interpolation in SQL.
- **XSS Mitigation:** Context-aware escaping using `htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`, combined with strict Content-Security-Policy (CSP) headers.
- **Brute Force & Rate Limiting:** In-memory or SQLite-backed IP and user-level throttling for authentication and ballot submission attempts.

### 4.2. Secret Ballot Cryptographic Decoupling
To guarantee vote secrecy while preventing duplicate voting and allowing individual verification:

```
[Voter Authenticated]
       │
       ├─── Step 1: Check eligibility & proxy rights
       │
       ├─── Step 2: Open SQLite Transaction
       │      │
       │      ├─── Record in `ballot_participation`:
       │      │      (assembly_id, motion_id, member_id, voted_at)
       │      │      *Prevents double voting*
       │      │
       │      ├─── Generate random Ballot Salt & Receipt Hash:
       │      │      Receipt = SHA-256(motion_id || ballot_salt || vote_choice)
       │      │
       │      ├─── Insert into unlinked `anonymous_ballots`:
       │      │      (motion_id, vote_choice, vote_weight, receipt_hash, created_at)
       │      │      *NO member_id or IP recorded here!*
       │      │
       │      └─── Commit Transaction
       │
       └─── Step 3: Return Receipt Hash to Voter Screen
                     *Voter can verify their receipt hash is listed in public tally table*
```

---

## 5. Database Schema Specification (SQLite 3)

The SQLite database uses:
- `PRAGMA journal_mode = WAL;` (Write-Ahead Logging for high concurrency)
- `PRAGMA foreign_keys = ON;` (Strict relational integrity)
- `PRAGMA busy_timeout = 5000;` (Resilience under concurrent writes)

```sql
-- 1. Users & Administrators
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('superadmin', 'chair', 'teller', 'voter', 'observer')),
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. Assemblies
CREATE TABLE assemblies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    location TEXT,
    scheduled_at DATETIME NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'registration', 'in_session', 'paused', 'concluded')),
    quorum_type TEXT NOT NULL DEFAULT 'majority_of_active' CHECK (quorum_type IN ('majority_of_active', 'two_thirds', 'percentage_of_all', 'absolute_count')),
    quorum_threshold REAL NOT NULL DEFAULT 50.0,
    created_by INTEGER NOT NULL REFERENCES users(id),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    concluded_at DATETIME
);

-- 3. Assembly Members & Voting Weights
CREATE TABLE assembly_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    assembly_id INTEGER NOT NULL REFERENCES assemblies(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    member_code TEXT NOT NULL, -- e.g. Card / Badge ID / QR token
    vote_weight REAL NOT NULL DEFAULT 1.0,
    is_eligible INTEGER NOT NULL DEFAULT 1,
    UNIQUE(assembly_id, user_id),
    UNIQUE(assembly_id, member_code)
);

-- 4. Attendance & Check-In Log
CREATE TABLE attendances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    assembly_id INTEGER NOT NULL REFERENCES assemblies(id) ON DELETE CASCADE,
    assembly_member_id INTEGER NOT NULL REFERENCES assembly_members(id) ON DELETE CASCADE,
    check_in_type TEXT NOT NULL CHECK (check_in_type IN ('in_person', 'digital', 'proxy_only')),
    checked_in_by INTEGER REFERENCES users(id), -- Teller or null for self-checkin
    checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_present INTEGER NOT NULL DEFAULT 1,
    UNIQUE(assembly_id, assembly_member_id)
);

-- 5. Proxy Representation (Member delegating vote to another attendee)
CREATE TABLE proxies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    assembly_id INTEGER NOT NULL REFERENCES assemblies(id) ON DELETE CASCADE,
    grantor_member_id INTEGER NOT NULL REFERENCES assembly_members(id) ON DELETE CASCADE,
    proxy_holder_member_id INTEGER NOT NULL REFERENCES assembly_members(id) ON DELETE CASCADE,
    proof_document_path TEXT,
    is_verified INTEGER NOT NULL DEFAULT 0,
    verified_by INTEGER REFERENCES users(id),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(assembly_id, grantor_member_id)
);

-- 6. Agenda & Motions
CREATE TABLE motions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    assembly_id INTEGER NOT NULL REFERENCES assemblies(id) ON DELETE CASCADE,
    order_index INTEGER NOT NULL DEFAULT 0,
    title TEXT NOT NULL,
    description TEXT,
    voting_type TEXT NOT NULL CHECK (voting_type IN ('yes_no_abstain', 'single_choice', 'multiple_choice')),
    is_secret INTEGER NOT NULL DEFAULT 1,
    majority_rule TEXT NOT NULL DEFAULT 'simple' CHECK (majority_rule IN ('simple', 'absolute', 'two_thirds', 'three_quarters', 'unanimous')),
    max_choices INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'open', 'closed', 'cancelled')),
    opened_at DATETIME,
    closed_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 7. Motion Choices / Options
CREATE TABLE motion_options (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    motion_id INTEGER NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
    option_text TEXT NOT NULL,
    order_index INTEGER NOT NULL DEFAULT 0
);

-- 8. Voter Participation (Audit record of WHO voted to stop double voting - NO choice recorded here)
CREATE TABLE ballot_participation (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    motion_id INTEGER NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
    assembly_member_id INTEGER NOT NULL REFERENCES assembly_members(id) ON DELETE CASCADE,
    as_proxy_for_member_id INTEGER REFERENCES assembly_members(id) ON DELETE SET NULL,
    voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(motion_id, assembly_member_id, as_proxy_for_member_id)
);

-- 9. Anonymous Ballots (Stores WHAT was voted in secret ballots - completely unlinked from members)
CREATE TABLE anonymous_ballots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    motion_id INTEGER NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
    option_id INTEGER NOT NULL REFERENCES motion_options(id) ON DELETE CASCADE,
    vote_weight REAL NOT NULL DEFAULT 1.0,
    receipt_hash TEXT NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 10. Open / Roll-Call Ballots (Used ONLY when motion is is_secret = 0)
CREATE TABLE open_ballots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    motion_id INTEGER NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
    assembly_member_id INTEGER NOT NULL REFERENCES assembly_members(id) ON DELETE CASCADE,
    option_id INTEGER NOT NULL REFERENCES motion_options(id) ON DELETE CASCADE,
    vote_weight REAL NOT NULL DEFAULT 1.0,
    as_proxy_for_member_id INTEGER REFERENCES assembly_members(id) ON DELETE SET NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 11. Tamper-Evident System Audit Trail
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    assembly_id INTEGER REFERENCES assemblies(id) ON DELETE SET NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
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

## 6. Directory and Codebase Structure

```
/projects/convota/
├── .gitignore
├── README.md
├── SPECIFICATIONS.md          <-- (This Document)
├── config/
│   ├── database.php           <-- SQLite connection & PRAGMA configuration
│   └── settings.php           <-- Application settings & security constants
├── data/                      <-- Protected folder (outside or forbidden by web server)
│   ├── .htaccess              <-- "Deny from all" for Apache / nginx equivalent
│   └── convota.sqlite         <-- SQLite production database file
├── public/                    <-- Web Server Document Root
│   ├── .htaccess              <-- URL rewriting & security headers
│   ├── index.php              <-- Front controller & routing
│   ├── assets/
│   │   ├── css/
│   │   │   ├── app.css        <-- Clean modern styling (responsive, dark/light)
│   │   │   └── projector.css  <-- Large-screen display styling
│   │   └── js/
│   │       ├── app.js         <-- Client-side UX & live polling
│   │       └── projector.js   <-- Auto-refreshing projector scripts
├── src/                       <-- PSR-4 style application code
│   ├── Auth/                  <-- Authentication, Sessions, CSRF
│   ├── Database/              <-- Database connection manager & Migrations
│   ├── Models/                <-- Data access & business logic
│   ├── Controllers/           <-- Request handling & validation
│   ├── Services/              <-- Quorum calculator, Ballot cryptography, Audit logger
│   └── Views/                 <-- Semantic, escaped HTML template components
└── tests/                     <-- Unit & integration tests for quorum and voting
```

---

## 7. Phased Implementation Roadmap

1. **Phase 1: Foundation & Security Setup**
   - Initialize directory structure and database connection manager (`PDO` + SQLite WAL).
   - Database migration and seed system for quick installation.
   - Core security utilities: Session management, CSRF generator/validator, password hashing, and HTML escaping helper.
2. **Phase 2: Authentication & User/Assembly Management**
   - User authentication and role-based access control.
   - Assembly creation and scheduling.
   - Member roster import/export (CSV support).
3. **Phase 3: Attendance, Proxies & Real-time Quorum Engine**
   - Desk check-in (token/QR/search) and self-checkin.
   - Proxy assignment and limit verification.
   - Live quorum calculation view.
4. **Phase 4: Motions & Voting Engine**
   - Agenda management and motion authoring.
   - Voting execution (secret decoupled ballot vs. open roll-call).
   - Receipt generation and verification interface.
   - Real-time tally computation and majority certification.
5. **Phase 5: Big-Screen Projector View & Protocol Export**
   - Assembly hall projector screen with live state sync.
   - Minutes and audit report generation (HTML/printable view).
6. **Phase 6: Automated Testing & Security Audit**
   - Unit tests for quorum calculations, proxy edge cases, and cryptographic ballot unlinking.
   - Hardening and installation guide.
