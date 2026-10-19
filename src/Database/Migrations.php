<?php
declare(strict_types=1);

namespace Convota\Database;

use PDO;

/**
 * Convota Schema Migrations Runner
 */
class Migrations
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function run(): void
    {
        $this->createMigrationTable();
        $this->runInitialMigration();
    }

    private function createMigrationTable(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration_name TEXT NOT NULL UNIQUE,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function isMigrated(string $name): bool
    {
        $row = $this->db->fetch("SELECT id FROM schema_migrations WHERE migration_name = ?", [$name]);
        return $row !== null;
    }

    private function markMigrated(string $name): void
    {
        $this->db->execute("INSERT INTO schema_migrations (migration_name) VALUES (?)", [$name]);
    }

    private function runInitialMigration(): void
    {
        $name = '001_initial_schema';
        if ($this->isMigrated($name)) {
            return;
        }

        $this->db->beginTransactionImmediate();

        try {
            // 1. Organizations
            $this->db->query("
                CREATE TABLE IF NOT EXISTS organizations (
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
                )
            ");

            // 2. Users (Members and Admins)
            $this->db->query("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    organization_id INTEGER REFERENCES organizations(id) ON DELETE SET NULL,
                    email TEXT NOT NULL,
                    password_hash TEXT,
                    name TEXT NOT NULL,
                    surname TEXT NOT NULL,
                    telephone TEXT,
                    is_primary INTEGER NOT NULL DEFAULT 0,
                    system_role TEXT NOT NULL DEFAULT 'member' CHECK (system_role IN ('admin', 'president', 'member')),
                    reset_token TEXT,
                    reset_token_expires_at DATETIME,
                    is_active INTEGER NOT NULL DEFAULT 1,
                    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 3. Meetings
            $this->db->query("
                CREATE TABLE IF NOT EXISTS meetings (
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
                )
            ");

            // 4. Meeting Presence & Designated Representative
            $this->db->query("
                CREATE TABLE IF NOT EXISTS meeting_presence (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    meeting_id INTEGER NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
                    organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
                    representative_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                    is_present INTEGER NOT NULL DEFAULT 1,
                    check_in_method TEXT NOT NULL CHECK (check_in_method IN ('self_login', 'common_hash', 'admin_rollcall')),
                    checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    checked_in_ip TEXT,
                    UNIQUE(meeting_id, organization_id)
                )
            ");

            // 5. Proposals / Elections
            $this->db->query("
                CREATE TABLE IF NOT EXISTS proposals (
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
                )
            ");

            // 6. Proposal Supervisors (Election Committee)
            $this->db->query("
                CREATE TABLE IF NOT EXISTS proposal_supervisors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
                    organization_id INTEGER REFERENCES organizations(id) ON DELETE CASCADE,
                    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE(proposal_id, organization_id, user_id)
                )
            ");

            // 7. Proposal Choices / Options
            $this->db->query("
                CREATE TABLE IF NOT EXISTS proposal_options (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
                    option_text TEXT NOT NULL,
                    order_index INTEGER NOT NULL DEFAULT 0
                )
            ");

            // 8. Organization Voting Participation (Tracks WHO voted; idempotent; triggers emails)
            $this->db->query("
                CREATE TABLE IF NOT EXISTS vote_participation (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
                    organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
                    cast_by_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                    voter_name_snapshot TEXT NOT NULL,
                    voter_ip TEXT,
                    idempotency_key TEXT UNIQUE,
                    voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(proposal_id, organization_id)
                )
            ");

            // 9. Open / Roll-Call Ballots
            $this->db->query("
                CREATE TABLE IF NOT EXISTS open_ballots (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
                    organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
                    option_id INTEGER NOT NULL REFERENCES proposal_options(id) ON DELETE CASCADE,
                    representative_name TEXT NOT NULL,
                    vote_weight REAL NOT NULL DEFAULT 1.0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 10. Anonymous Ballots (Zero foreign keys to organization/user)
            $this->db->query("
                CREATE TABLE IF NOT EXISTS anonymous_ballots (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    proposal_id INTEGER NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
                    option_id INTEGER NOT NULL REFERENCES proposal_options(id) ON DELETE CASCADE,
                    vote_weight REAL NOT NULL DEFAULT 1.0,
                    receipt_hash TEXT NOT NULL UNIQUE,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // 11. Tamper-Evident Audit Trail
            $this->db->query("
                CREATE TABLE IF NOT EXISTS audit_logs (
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
                )
            ");

            // 12. Rate Limiting Table
            $this->db->query("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    key TEXT PRIMARY KEY,
                    attempts INTEGER NOT NULL DEFAULT 1,
                    last_attempt_at INTEGER NOT NULL
                )
            ");

            $this->markMigrated($name);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
