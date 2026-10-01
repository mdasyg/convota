# Convota (Voting Together)

**Convota** is a secure, lightweight, and modern General Assembly presence and voting information system built with **PHP 8.4** and **SQLite3**.

## Core Goals
- **Verified Presence & Quorum Tracking:** Real-time check-in (in-person QR/code or digital) and dynamic quorum monitoring.
- **Proxy Management:** Authorized representation with verifiable limits per delegate.
- **Dual Voting Modes:**
  - **Secret Ballot:** Cryptographically decoupled voter participation from anonymous ballot entries with verifiable vote receipts.
  - **Open Roll-Call:** Transparent recorded voting for public resolutions.
- **Defense-in-Depth Security:** Native SQLite WAL mode, strict PDO prepared statements, Argon2id passwords, strict session and CSRF protections, and tamper-evident audit logging.
- **Zero Heavy Dependencies:** Clean, maintainable, self-hosted PHP architecture without cumbersome runtime dependencies.

## Documentation
- Detailed architectural and functional specifications: [SPECIFICATIONS.md](file:///projects/convota/SPECIFICATIONS.md)

## Project Structure
- `SPECIFICATIONS.md` — Complete system architecture, database schema, security threat model, and module designs.
- `config/` — SQLite and application configuration.
- `data/` — SQLite database files (protected from direct web access).
- `public/` — Front controller and public web assets.
- `src/` — Application source code (Controllers, Services, Models, Auth).
- `tests/` — Automated test suite.
