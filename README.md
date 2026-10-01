# Convota (Voting Together)

**Convota** (*"Voting Together"*, from Latin *con* + *vota*) is an open, secure, and privacy-preserving General Assembly presence and voting information system built with **PHP 8.4** and **SQLite3**.

## Core Concept
Convota manages a single **Collective** composed of multiple member **Organizations** (applicable to research consortia, international federations, academic alliances, NGOs, standards bodies, cooperatives, etc.).

> **Core Axiom:** *The vote belongs to the organization; the designated representative is the authenticated person exercising that organization's voting right.*

---

## Key Features

1. **Organization & Membership Roster:**
   - Organizations with configurable voting weights (default 1 vote, custom weights, or 0 votes for Observers).
   - One organization designated as the **President Organization** (reassignable by Admin).
   - Each organization has a **Primary Delegate** and one or more **Substitute Members**.
   - Members can be added/removed by organization members or admins, with full historical logging (who, when, IP).
   - **Shared Organization Access Hash:** Unique shared token per organization with granular security controls (notify members on use, email token, rotate/reinitialize token, enable/disable token).

2. **Presence & Real-Time Quorum Engine:**
   - Real-time check-in and designation of the active participating voter per meeting.
   - Dynamic quorum monitoring (e.g. `Participating: 14 of 28 voting organizations (50.0%)`).
   - Projector / Wall display mode for physical assembly halls.
   - Attendance export to CSV.

3. **Dual-Mode Voting Engine (Open & Anonymous):**
   - Proposal formats: `YES / NO / ABSTAIN` or `Multiple Choice` (Choice A, B, C... / Abstain).
   - Timing options: Manual Open/Close or scheduled **FROM / START** and **TO / END** voting window with interactive calendar/datetime picker.
   - **Strict Privacy in Secret Ballots:** Cryptographically decoupled ballot box (`anonymous_ballots` has zero foreign keys or user references). Voters receive a cryptographic verification receipt hash.
   - **Open Roll-Call Ballots:** Records organization and representative name directly in public minutes.
   - Real-time President/Admin status screen (who has voted vs pending). **Results are sealed until voting closes.**
   - Intra-organization notification: Automated email sent to all members of an organization when their vote is cast.

4. **Smart CSV Import with Conflict Detection:**
   - Format: `A/a, name, surname, email, organization, [telephone], [vote_weight]`
   - Visual conflict detection: Flags existing emails belonging to another organization with a prominent red warning, and offers checkbox resolution for existing members.

5. **Security & Auditing:**
   - **HTTPS Enforcement:** Automatic 301/302 redirection from `http://` to `https://` with HSTS and hardened security headers (CSP, X-Frame-Options: DENY, X-Content-Type-Options: nosniff).
   - **Strict Authorization:** Granular authorization checks on every web route and API endpoint (organization isolation, designated voter verification, electoral committee boundaries).
   - **Anti-Replay & Idempotent Voting API:** Protection against duplicate vote submission via SQLite atomic immediate transactions, unique constraints, and single-use ballot nonces / idempotency keys.
   - **Deterministic State Machine:** Explicit proposal lifecycle (`DRAFT` -> `OPEN` / `PAUSED` -> `CLOSED` -> `ARCHIVED`) preventing invalid or retroactive ballot manipulation.
   - **Hardened Sessions & Auth:** Argon2id password hashing, secure session management (`SameSite=Strict`, `HttpOnly`, `Secure`), and per-request CSRF tokens.
   - **Tamper-Evident Audit Trail:** SHA-256 hash chaining of every system mutation and ballot event.
   - Zero bulky dependencies, fast, self-contained, and easy to audit.

---

## Documentation
- Complete System Specification: [SPECIFICATIONS.md](file:///projects/convota/SPECIFICATIONS.md)
