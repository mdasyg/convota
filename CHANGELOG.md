# Convota — Changelog

All notable changes to the Convota General Assembly Presence and Voting System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to Semantic Versioning.

---

## [Unreleased]

## [1.3.0] - 2026-10-02
### Added
- **Frontend Nginx Reverse Proxy Configuration (`convota.conf` & `config/convota.conf`)**:
  - Templated from production-tested `rtl2schematic.conf` and `chronos.conf`.
  - Configures modular location `/convota/` for deployment in frontend web servers (FreeBSD `/usr/local/etc/nginx/locations/` or Linux `/etc/nginx/locations/`).
  - Canonical trailing slash enforcement (`/convota` -> 301 to `https://$host/convota/`).
  - Regex priority shielding with `location ^~ /convota/` ensuring parent regex blocks (`~ \.php$`) do not intercept subpath API endpoints.
  - Proxy header propagation: `Host`, `X-Real-IP`, `X-Forwarded-For`, `X-Forwarded-Proto $scheme`, and `X-Forwarded-Prefix /convota`.
  - Buffer tuning, HTTP/1.1 persistent connections, and 50MB payload limit for CSV roster processing and real-time live displays.
  - Validated syntax with `nginx -t`.
  - Documented deployment instructions in `README.md`.

## [1.2.0] - 2026-10-01
### Changed
- **Default Superadmin Email**:
  - Changed default administrator email to `adminconvota@email.vlsi.gr` across configuration (`config/config.php`), database seeder (`src/Database/Seeder.php`), tests (`tests/test_phase1.php`), and documentation.
  - Executed migration in active production SQLite database (`data/convota.sqlite`).
- **Delegate Email Domain Safety Standard**:
  - Standardized all delegate, member, and demonstration email addresses to the safe test domain `@email.vlsi.gr` (e.g. `mdasyg@email.vlsi.gr`, `kstergiou@email.vlsi.gr`, `elena.rostova@email.vlsi.gr`, `marcus.vogel@email.vlsi.gr`, `sophie@email.vlsi.gr`, `astrid@email.vlsi.gr`, `tuomas@email.vlsi.gr`, `frederik@email.vlsi.gr`).
  - Removed all real-world domains (`@uowm.gr`, `@eorn.org`, `@standards-intl.org`, `@gec-ngo.org`, `@nordic-ai.org`, `@danish-consortium.dk`, etc.) to prevent inadvertent outbound email delivery to real-world individuals.
  - Updated all test suites (`test_phase1.php` through `test_phase5.php`) to validate using `@email.vlsi.gr`.
  - Updated placeholder attributes in all view templates (`src/Views/auth/login.php`, `forgot_password.php`, `csv/import.php`, `organizations/portal.php`, `organizations/create.php`).
  - Updated active SQLite database records in `users` table to point to `@email.vlsi.gr`.
- **System Mailer Default Sender**:
  - Changed default outbound system sender from `no-reply@convota.local` to `no-reply@email.vlsi.gr` in `config/config.php` and `src/Services/MailerService.php`.
- **Documentation**:
  - Updated `README.md` and `SPECIFICATIONS.md` with the new default credentials, domain safety standard, and comprehensive dual-deployment portability documentation (subfolder `/convota` vs root/subdomain `/`).

## [1.1.0] - 2026-10-01
### Added
- Nginx Web Server integration in `/etc/nginx/sites-available/rtl2schematic`:
  - 301 Redirect for `/convota` to `/convota/` (preserving query parameters).
  - Aliased prefix routing `location ^~ /convota/` pointing to `/projects/convota/public/`.
  - FastCGI integration with PHP-FPM 8.4 socket (`unix:/run/php/php8.4-fpm.sock`).
  - Fallback named rewrite location `@convota` routing requests through `/convota/index.php`.
  - Static asset cache rules for `.css`, `.js`, `.png`, `.svg`, etc. with 7-day expiration.
  - Strict security location blocks preventing direct access to `data/`, `src/`, `config/`, `tests/`, `.git/`, and `.sqlite/.log` files (returns HTTP 404).
  - Reverse proxy prefix and referer detection for seamless `/convota` routing.
- Subpath-Aware Routing & Dynamic URL Helper:
  - Added `App::detectBasePath()`, `App::baseUrl()`, and `App::redirectUrl()` in `src/App.php`.
  - Added global helper functions `url()` and `redirect()` in `src/helpers.php` (included via `src/Autoload.php`).
  - Dynamic route URI normalization stripping `/convota` prefix for router dispatch while preserving dual-environment portability (works standalone at `/` or under `/convota`).
  - Updated all controllers to use `redirect()`.
  - Updated all view templates, layouts, form actions, asset links, and navigation tags to use `url(...)`.
  - Updated AJAX fetch calls in `app.js` and `projector.js` to dynamically prefix requests with `<meta name="base-url">`.
- Phase 6 Test Suite:
  - Added `tests/test_nginx_routing.php` (10 passing tests) and updated master runner to execute 130 tests across all 6 phases.

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
