# Convota — Changelog

All notable changes to the Convota General Assembly Presence and Voting System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to Semantic Versioning.

---

## [Unreleased]

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
