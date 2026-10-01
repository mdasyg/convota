<?php
declare(strict_types=1);

namespace Convota\Services;

use Convota\Database\Database;
use Convota\Models\Organization;
use Convota\Models\User;
use Convota\Security\Validator;
use InvalidArgumentException;

/**
 * Smart CSV Import & Export Service with Conflict Detection
 */
class CsvService
{
    private Database $db;
    private AuditService $audit;
    private MailerService $mailer;

    public function __construct(Database $db, ?AuditService $audit = null, ?MailerService $mailer = null)
    {
        $this->db = $db;
        $this->audit = $audit ?? new AuditService($db);
        $this->mailer = $mailer ?? new MailerService();
    }

    /**
     * Preview CSV content with smart duplicate & conflict detection
     * CSV Columns: A/a, name, surname, email, organization, [telephone], [vote_weight]
     */
    public function previewImport(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines)) {
            throw new InvalidArgumentException("CSV content is empty.");
        }

        $parsedRows = [];
        $summary = [
            'total' => 0,
            'new_members' => 0,
            'new_organizations' => 0,
            'existing_same_org' => 0,
            'conflicts' => 0
        ];

        $seenOrgs = [];
        $headerSkipped = false;

        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $fields = str_getcsv($line);
            if (count($fields) < 4) {
                continue;
            }

            // Detect and skip header row
            if (!$headerSkipped && (
                stripos($fields[0], 'A/a') !== false ||
                stripos($fields[1], 'name') !== false ||
                stripos($fields[3], 'email') !== false
            )) {
                $headerSkipped = true;
                continue;
            }

            // Normalization
            // Fields: 0: A/a, 1: name, 2: surname, 3: email, 4: organization, 5: phone, 6: weight
            $index = $fields[0] ?? (string)($lineIndex + 1);
            $name = Validator::cleanString($fields[1] ?? '');
            $surname = Validator::cleanString($fields[2] ?? '');
            $email = strtolower(trim($fields[3] ?? ''));
            $orgName = Validator::cleanString($fields[4] ?? '');
            $phone = isset($fields[5]) ? Validator::cleanString($fields[5]) : null;
            $weight = isset($fields[6]) && is_numeric($fields[6]) ? (float)$fields[6] : 1.0;

            if (empty($name) || empty($surname) || empty($email) || empty($orgName)) {
                continue;
            }

            $summary['total']++;

            $rowAnalysis = [
                'index' => $index,
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'organization' => $orgName,
                'telephone' => $phone,
                'voting_weight' => $weight,
                'status' => 'new', // 'new', 'existing_same_org', 'conflict_different_org'
                'status_label' => 'New Member & Organization',
                'warning' => null,
                'existing_org_id' => null,
                'existing_user_id' => null,
                'org_is_new' => false
            ];

            // 1. Check organization existence
            $existingOrg = $this->db->fetch("
                SELECT id, name FROM organizations WHERE LOWER(name) = ?
            ", [strtolower($orgName)]);

            if ($existingOrg) {
                $rowAnalysis['existing_org_id'] = (int)$existingOrg['id'];
            } else {
                $rowAnalysis['org_is_new'] = true;
                if (!isset($seenOrgs[strtolower($orgName)])) {
                    $seenOrgs[strtolower($orgName)] = true;
                    $summary['new_organizations']++;
                }
            }

            // 2. Check user email existence in system
            $existingUser = $this->db->fetch("
                SELECT u.id, u.name, u.surname, u.organization_id, o.name as org_name
                FROM users u
                LEFT JOIN organizations o ON u.organization_id = o.id
                WHERE LOWER(u.email) = ?
            ", [$email]);

            if ($existingUser) {
                $rowAnalysis['existing_user_id'] = (int)$existingUser['id'];

                if ($rowAnalysis['existing_org_id'] && (int)$existingUser['organization_id'] === $rowAnalysis['existing_org_id']) {
                    // Existing member under the SAME organization
                    $rowAnalysis['status'] = 'existing_same_org';
                    $rowAnalysis['status_label'] = 'Existing Member (Same Organization)';
                    $rowAnalysis['warning'] = "Member '{$email}' already exists in this organization. Check box to update details or leave unchecked to skip.";
                    $summary['existing_same_org']++;
                } else {
                    // Conflict: Email already belongs to a DIFFERENT organization!
                    $currentOrgName = $existingUser['org_name'] ?? 'Unassigned';
                    $rowAnalysis['status'] = 'conflict_different_org';
                    $rowAnalysis['status_label'] = 'CRITICAL CONFLICT (Different Organization)';
                    $rowAnalysis['warning'] = "Conflict: Member '{$email}' is already registered under '{$currentOrgName}', but CSV specifies '{$orgName}'. This is likely an error and will NOT be modified.";
                    $summary['conflicts']++;
                }
            } else {
                $rowAnalysis['status'] = 'new';
                $rowAnalysis['status_label'] = $rowAnalysis['org_is_new'] ? 'New Member & New Organization' : 'New Member (Existing Organization)';
                $summary['new_members']++;
            }

            $parsedRows[] = $rowAnalysis;
        }

        return [
            'rows' => $parsedRows,
            'summary' => $summary
        ];
    }

    /**
     * Commit the imported CSV rows to SQLite
     */
    public function commitImport(
        array $rows,
        array $updateExistingEmails = [],
        ?int $adminUserId = null,
        ?string $ip = null,
        ?string $ua = null,
        string $baseUrl = ''
    ): array {
        $orgModel = new Organization($this->db, $this->audit, $this->mailer);
        $userModel = new User($this->db, $this->audit, $this->mailer);

        $results = [
            'created_orgs' => 0,
            'created_members' => 0,
            'updated_members' => 0,
            'skipped' => 0
        ];

        $this->db->beginTransactionImmediate();

        try {
            $createdOrgsCache = [];

            foreach ($rows as $row) {
                $status = $row['status'] ?? 'new';
                $email = strtolower(trim($row['email']));
                $orgName = $row['organization'];

                // 1. Critical conflict with different organization: NEVER overwrite, always skip!
                if ($status === 'conflict_different_org') {
                    $results['skipped']++;
                    continue;
                }

                // 2. Existing member in same organization
                if ($status === 'existing_same_org') {
                    if (in_array($email, $updateExistingEmails, true)) {
                        // User opted to update
                        $this->db->execute("
                            UPDATE users SET name = ?, surname = ?, telephone = ?, updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                        ", [$row['name'], $row['surname'], $row['telephone'], $row['existing_user_id']]);
                        $results['updated_members']++;
                    } else {
                        $results['skipped']++;
                    }
                    continue;
                }

                // 3. New member / Organization resolution
                $orgId = $row['existing_org_id'] ?? null;
                if (!$orgId && isset($createdOrgsCache[strtolower($orgName)])) {
                    $orgId = $createdOrgsCache[strtolower($orgName)];
                }

                if (!$orgId) {
                    // Check database again in case created during this loop
                    $existingOrg = $this->db->fetch("SELECT id FROM organizations WHERE LOWER(name) = ?", [strtolower($orgName)]);
                    if ($existingOrg) {
                        $orgId = (int)$existingOrg['id'];
                    } else {
                        // Create organization with first member
                        $res = $orgModel->createWithFirstMember(
                            [
                                'name' => $orgName,
                                'voting_weight' => $row['voting_weight'] ?? 1.0,
                                'is_observer' => ($row['voting_weight'] ?? 1.0) == 0 ? 1 : 0
                            ],
                            [
                                'name' => $row['name'],
                                'surname' => $row['surname'],
                                'email' => $email,
                                'telephone' => $row['telephone']
                            ],
                            $adminUserId,
                            $ip,
                            $ua,
                            $baseUrl
                        );

                        $orgId = $res['organization_id'];
                        $createdOrgsCache[strtolower($orgName)] = $orgId;
                        $results['created_orgs']++;
                        $results['created_members']++;
                        continue;
                    }
                }

                // Organization exists, add new member
                $userModel->addMember(
                    $orgId,
                    [
                        'name' => $row['name'],
                        'surname' => $row['surname'],
                        'email' => $email,
                        'telephone' => $row['telephone'],
                        'is_primary' => 0
                    ],
                    $adminUserId,
                    $ip,
                    $ua,
                    $baseUrl
                );
                $results['created_members']++;
            }

            $this->audit->log('csv_import_committed', $adminUserId, null, $results, $ip, $ua);
            $this->db->commit();

            return $results;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Export registered organizations to CSV
     */
    public function exportOrganizationsCsv(): string
    {
        $rows = $this->db->fetchAll("
            SELECT o.id, o.name, o.short_name, o.country, o.website, o.status,
                   o.voting_weight, o.is_president, o.is_observer, o.created_at,
                   (SELECT COUNT(*) FROM users u WHERE u.organization_id = o.id AND u.is_active = 1) as member_count
            FROM organizations o
            ORDER BY o.name ASC
        ");

        $f = fopen('php://temp', 'r+');
        fputcsv($f, ['ID', 'Name', 'Short Name', 'Country', 'Website', 'Status', 'Voting Weight', 'Is President', 'Is Observer', 'Member Count', 'Created At']);

        foreach ($rows as $r) {
            fputcsv($f, [
                $r['id'],
                $r['name'],
                $r['short_name'],
                $r['country'],
                $r['website'],
                $r['status'],
                $r['voting_weight'],
                $r['is_president'] ? 'YES' : 'NO',
                $r['is_observer'] ? 'YES' : 'NO',
                $r['member_count'],
                $r['created_at']
            ]);
        }

        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);
        return $csv;
    }

    /**
     * Export members to CSV
     */
    public function exportMembersCsv(?int $orgId = null): string
    {
        $sql = "
            SELECT u.id, u.name, u.surname, u.email, u.telephone, u.is_primary, u.system_role,
                   o.name as organization_name, o.voting_weight
            FROM users u
            LEFT JOIN organizations o ON u.organization_id = o.id
            WHERE u.is_active = 1
        ";
        $params = [];
        if ($orgId !== null) {
            $sql .= " AND u.organization_id = ?";
            $params[] = $orgId;
        }
        $sql .= " ORDER BY o.name ASC, u.is_primary DESC, u.surname ASC";

        $rows = $this->db->fetchAll($sql, $params);

        $f = fopen('php://temp', 'r+');
        fputcsv($f, ['A/a', 'Name', 'Surname', 'Email', 'Organization', 'Telephone', 'Is Primary Delegate', 'System Role', 'Org Voting Weight']);

        $i = 1;
        foreach ($rows as $r) {
            fputcsv($f, [
                $i++,
                $r['name'],
                $r['surname'],
                $r['email'],
                $r['organization_name'] ?? 'System Admin',
                $r['telephone'],
                $r['is_primary'] ? 'YES' : 'NO',
                $r['system_role'],
                $r['voting_weight'] ?? '1.0'
            ]);
        }

        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);
        return $csv;
    }
}
