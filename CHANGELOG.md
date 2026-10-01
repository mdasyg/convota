# Convota — Changelog

All notable changes to the Convota General Assembly Presence and Voting System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to Semantic Versioning.

---

## [Unreleased]

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
