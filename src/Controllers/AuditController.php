<?php
declare(strict_types=1);

namespace Convota\Controllers;

use Convota\App;

/**
 * Tamper-Evident Audit Trail Controller
 */
class AuditController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required to view system audit logs.');
            return;
        }

        $audit = $this->app->getAudit();
        $isChainIntact = $audit->verifyChain();

        $db = $this->app->getDb();
        $logs = $db->fetchAll("
            SELECT a.*, 
                   u.name || ' ' || u.surname as user_name,
                   o.name as organization_name
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN organizations o ON a.organization_id = o.id
            ORDER BY a.id DESC LIMIT 200
        ");

        $this->app->render('audit/index', [
            'logs' => $logs,
            'isChainIntact' => $isChainIntact
        ]);
    }
}
