<?php
declare(strict_types=1);

namespace Convota\Services;

use Convota\Database\Database;

/**
 * Tamper-Evident Audit Service with SHA-256 Hash Chaining
 */
class AuditService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Log a tamper-evident system event
     */
    public function log(
        string $action,
        ?int $userId = null,
        ?int $organizationId = null,
        array|string|null $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int {
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System');
        $detailsJson = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : (string)$details;

        // Fetch the previous hash in the chain
        $last = $this->db->fetch("SELECT entry_hash FROM audit_logs ORDER BY id DESC LIMIT 1");
        $prevHash = $last ? (string)$last['entry_hash'] : 'GENESIS_CONVOTA_HASH_00000000000000000000000000000000';

        $timestamp = gmdate('Y-m-d H:i:s');
        $payload = implode('|', [
            $prevHash,
            (string)$userId,
            (string)$organizationId,
            $action,
            $detailsJson,
            $ip,
            $ua,
            $timestamp
        ]);
        $entryHash = hash('sha256', $payload);

        $this->db->execute("
            INSERT INTO audit_logs (
                user_id, organization_id, action, details, ip_address, user_agent, prev_hash, entry_hash, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $userId,
            $organizationId,
            $action,
            $detailsJson,
            $ip,
            $ua,
            $prevHash,
            $entryHash,
            $timestamp
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Verify the integrity of the cryptographic audit log chain
     * Returns true if chain is intact, or false if tampering is detected.
     */
    public function verifyChain(): bool
    {
        $rows = $this->db->fetchAll("SELECT * FROM audit_logs ORDER BY id ASC");
        if (empty($rows)) {
            return true;
        }

        $expectedPrevHash = 'GENESIS_CONVOTA_HASH_00000000000000000000000000000000';

        foreach ($rows as $row) {
            if ($row['prev_hash'] !== $expectedPrevHash) {
                return false; // Broken link
            }

            $payload = implode('|', [
                $row['prev_hash'],
                (string)$row['user_id'],
                (string)$row['organization_id'],
                $row['action'],
                (string)$row['details'],
                (string)$row['ip_address'],
                (string)$row['user_agent'],
                $row['created_at']
            ]);

            $recalculated = hash('sha256', $payload);
            if ($recalculated !== $row['entry_hash']) {
                return false; // Tampered content
            }

            $expectedPrevHash = $row['entry_hash'];
        }

        return true;
    }
}
