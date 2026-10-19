<?php
declare(strict_types=1);

namespace Convota\Database;

use Convota\Auth\AuthService;
use Convota\Auth\HashAuth;
use Convota\Services\AuditService;

/**
 * Convota Database Seeder
 */
class Seeder
{
    private Database $db;
    private AuditService $audit;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->audit = new AuditService($db);
    }

    public function seed(): void
    {
        // Check if admin already exists
        $admin = $this->db->fetch("SELECT id FROM users WHERE system_role = 'admin'");
        if ($admin) {
            return;
        }

        $this->db->beginTransactionImmediate();

        try {
            // 1. Create Super Admin
            $adminPass = AuthService::hashPassword('AdminConvota2026!');
            $this->db->execute("
                INSERT INTO users (email, password_hash, name, surname, telephone, system_role, is_active)
                VALUES (?, ?, ?, ?, ?, 'admin', 1)
            ", ['adminconvota@email.vlsi.gr', $adminPass, 'System', 'Administrator', '+30 24610 56500']);
            $adminId = (int)$this->db->lastInsertId();

            // 2. Create Sample Organizations
            $orgs = [
                [
                    'name' => 'University of Western Macedonia',
                    'short_name' => 'UOWM',
                    'country' => 'Greece',
                    'website' => 'https://uowm.gr',
                    'voting_weight' => 1.0,
                    'is_president' => 1,
                    'is_observer' => 0,
                    'members' => [
                        ['name' => 'Minas', 'surname' => 'Dasygenis', 'email' => 'convota@email.vlsi.gr', 'phone' => '+30 24610 56501', 'is_primary' => 1],
                        ['name' => 'Konstantinos', 'surname' => 'Stergiou', 'email' => 'convota@email.vlsi.gr', 'phone' => '+30 24610 56502', 'is_primary' => 0]
                    ]
                ],
                [
                    'name' => 'European Open Research Network',
                    'short_name' => 'EORN',
                    'country' => 'Belgium',
                    'website' => 'https://eorn.org',
                    'voting_weight' => 1.0,
                    'is_president' => 0,
                    'is_observer' => 0,
                    'members' => [
                        ['name' => 'Elena', 'surname' => 'Rostova', 'email' => 'convota@email.vlsi.gr', 'phone' => '+32 2 555 0101', 'is_primary' => 1],
                        ['name' => 'Marcus', 'surname' => 'Vogel', 'email' => 'convota@email.vlsi.gr', 'phone' => '+32 2 555 0102', 'is_primary' => 0]
                    ]
                ],
                [
                    'name' => 'International Standards Collective',
                    'short_name' => 'ISC',
                    'country' => 'Switzerland',
                    'website' => 'https://standards-collective.org',
                    'voting_weight' => 2.0, // Admin granted 2 votes
                    'is_president' => 0,
                    'is_observer' => 0,
                    'members' => [
                        ['name' => 'Sophie', 'surname' => 'Dubois', 'email' => 'convota@email.vlsi.gr', 'phone' => '+41 22 700 8800', 'is_primary' => 1]
                    ]
                ],
                [
                    'name' => 'Global NGO Environmental Council',
                    'short_name' => 'GEC',
                    'country' => 'Sweden',
                    'website' => 'https://gec-council.ngo',
                    'voting_weight' => 0.0,
                    'is_president' => 0,
                    'is_observer' => 1, // Observer organization (no voting rights)
                    'members' => [
                        ['name' => 'Astrid', 'surname' => 'Lindqvist', 'email' => 'convota@email.vlsi.gr', 'phone' => '+46 8 123 4567', 'is_primary' => 1]
                    ]
                ]
            ];

            $defaultPass = AuthService::hashPassword('MemberPassword2026!');

            foreach ($orgs as $orgData) {
                $hash = HashAuth::generateUniqueHash($this->db);
                $this->db->execute("
                    INSERT INTO organizations (
                        name, short_name, country, website, voting_weight, is_president, is_observer,
                        common_hash, hash_auth_enabled, email_on_hash_login
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
                ", [
                    $orgData['name'],
                    $orgData['short_name'],
                    $orgData['country'],
                    $orgData['website'],
                    $orgData['voting_weight'],
                    $orgData['is_president'],
                    $orgData['is_observer'],
                    $hash
                ]);
                $orgId = (int)$this->db->lastInsertId();

                foreach ($orgData['members'] as $m) {
                    $systemRole = ($orgData['is_president'] && $m['is_primary']) ? 'president' : 'member';
                    $this->db->execute("
                        INSERT INTO users (
                            organization_id, email, password_hash, name, surname, telephone,
                            is_primary, system_role, is_active, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                    ", [
                        $orgId,
                        $m['email'],
                        $defaultPass,
                        $m['name'],
                        $m['surname'],
                        $m['phone'],
                        $m['is_primary'],
                        $systemRole,
                        $adminId
                    ]);
                }
            }

            $this->audit->log('system_seeded', $adminId, null, ['status' => 'Seeded initial organizations and members']);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
