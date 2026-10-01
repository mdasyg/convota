# Convota — Changelog

All notable changes to the Convota General Assembly Presence and Voting System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to Semantic Versioning.

---

## [Unreleased]

## [1.0.0] - 2026-10-01
### Added
- Complete Web Application Router & Front Controller (`App.php`, `public/index.php`).
- Controllers:
  - `AuthController.php`: Dual-tabbed login (Member vs Shared Key), password reset token flow, session management.
  - `DashboardController.php`: Role-based views for collective administrators, presidium, and member delegates.
  - `OrganizationController.php`: CRUD, delegation roster management, primary/substitute delegates, shared key controls (email, rotate, toggle).
  - `MeetingController.php`: Assembly scheduling, presence tracking, dynamic quorum monitoring, roll-call check-in, attendance CSV export.
  - `ProposalController.php`: Proposal creation with calendar datetime picker, state machine transitions (`draft` -> `open` <-> `paused` -> `closed` -> `archived`), live monitor, sealed results, and assembly hall projector view.
  - `VoteController.php`: Ballot casting with anti-replay, single-use idempotency keys, receipt hash generation, intra-org alerts, and receipt verification tool.
  - `CsvController.php`: CSV roster upload, interactive conflict detection preview (red warnings for different org, checkboxes for same org), and export tools.
  - `AuditController.php`: Tamper-evident audit trail viewer with live SHA-256 chain verification badge.
  - `ApiController.php`: REST JSON endpoints for live quorum (`/api/quorum`), voting status (`/api/voting-status`), and authenticated idempotent voting (`/api/vote`).
- Views & Responsive Design:
  - Modern responsive design system in `public/assets/css/app.css`.
  - Big-screen high-contrast assembly hall styling in `public/assets/css/projector.css`.
  - Client-side AJAX polling & live clock in `public/assets/js/app.js` and `public/assets/js/projector.js`.
- Security configurations: `public/.htaccess` and `data/.htaccess`.
- Automated Phase 5 test suite (`tests/test_phase5.php`) and Master Test Runner (`tests/run_all_tests.php`) executing all 120 unit, integration, and security tests.

## [0.5.0] - 2026-10-01
### Added
- Proposal Model (`Proposal.php`) implementing a deterministic finite state machine (`draft` -> `open` <-> `paused` -> `closed` -> `archived`).
- Dual-Mode Voting Engine (`VotingService.php`):
  - Idempotent Voting API & Anti-Replay: Idempotency keys/nonces, `BEGIN IMMEDIATE TRANSACTION`, and duplicate vote rejection.
  - Secret Decoupled Ballot Box: Complete architectural decoupling in `anonymous_ballots` (zero foreign keys or relational linkage to voters or organizations).
  - Cryptographic voter receipt generation (`receipt_hash`) and self-verification tool.
  - Open Roll-Call Ballot Box: Preserves voter name and organization in public records.
  - Intra-Organization Transparency: Automated email notifications dispatched to all registered members when their organization's vote is cast.
  - Real-time participation monitor (AJAX/Fetch polling) that strictly hides vote selections during active voting.
  - Sealed Results Engine: Reveals mathematical tallies and majority/quorum percentages exclusively after closing.
- Automated Phase 4 test suite (`tests/test_phase4.php`) with 27 passing tests.

## [0.4.0] - 2026-10-01
### Added
- Meeting Model (`Meeting.php`) managing the full General Assembly lifecycle (`draft`, `registration`, `in_session`, `concluded`).
- Presence and Designated Representative Engine:
  - Supports self-login, common hash, and presidium roll-call check-in methods.
  - Active representative switching per assembly with immediate roster propagation.
- Dynamic Quorum Engine (`QuorumService.php`):
  - Dynamic attendance percentage calculation based exclusively on eligible voting organizations (preventing observer skew).
  - Configurable quorum threshold checks and human-readable status banners.
  - Full attendance roster with participant details and check-in metadata.
  - Attendance CSV export engine.
- Automated Phase 3 test suite (`tests/test_phase3.php`) with 24 passing tests.

## [0.3.0] - 2026-10-01
### Added
- Organization Model (`Organization.php`) with full metadata attributes, observer flags, voting weights, and president assignment.
- User / Member Model (`User.php`) with primary delegate assignment, substitute management, and member removal capability.
- Historical accountability logging: Recording who added/removed members, IP address, user-agent, and timestamps in tamper-evident logs.
- Smart CSV Service (`CsvService.php`) with conflict detection:
  - Detection of new members and new organizations.
  - Detection of existing members under the same organization (with update/skip option).
  - Prominent red warning and automated conflict prevention when an email is already registered under a different organization.
  - CSV exports for organizations and members roster.
- Nested transaction and SQLite savepoint engine in `Database.php`.
- Automated Phase 2 test suite (`tests/test_phase2.php`) with 26 passing tests.

## [0.2.0] - 2026-10-01
### Added
- Core database layer (`Database.php`) with SQLite WAL mode, foreign keys, busy timeout, and `BEGIN IMMEDIATE TRANSACTION`.
- Schema migrations (`Migrations.php`) covering all 12 tables and constraints.
- Initial seeder (`Seeder.php`) creating superadmin, sample organizations (with president and observer roles), and members.
- Cryptographic tamper-evident audit service (`AuditService.php`) with SHA-256 hash chaining and integrity verification.
- Security layer: Canonical HTTPS enforcement (`SecurityHeaders.php`), CSRF token generator & validator (`Csrf.php`), input validator & UTF-8 XSS escaping (`Validator.php`).
- Authentication services (`AuthService.php`, `HashAuth.php`) supporting Argon2id passwords, rate limiting, password reset tokens, and shared organization access keys.
- Mailer notification dispatcher (`MailerService.php`).
- PSR-4 autoloader (`Autoload.php`).
- Automated Phase 1 test suite (`tests/test_phase1.php`) with 31 passing unit & integration tests.

### [0.1.0] - 2026-10-01
#### Added
- Initial project layout and Git repository configuration.
- Comprehensive technical and security specifications in `SPECIFICATIONS.md`.
- Architecture outline and documentation in `README.md`.
- Threat modeling including HTTPS enforcement, strict CSRF defense, anti-replay, and idempotent voting API.
