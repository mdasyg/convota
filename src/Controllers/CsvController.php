<?php
declare(strict_types=1);

namespace Convota\Controllers;

use Convota\App;
use Convota\Services\CsvService;

/**
 * CSV Import (with Conflict Engine) and Export Controller
 */
class CsvController
{
    private App $app;
    private CsvService $csvService;

    public function __construct(App $app)
    {
        $this->app = $app;
        $db = $app->getDb();
        $audit = $app->getAudit();
        $mailer = $app->getMailer();
        $this->csvService = new CsvService($db, $audit, $mailer);
    }

    public function import(): void
    {
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required for bulk CSV operations.');
            return;
        }

        $this->app->render('csv/import', []);
    }

    public function preview(): void
    {
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required.');
            return;
        }

        $csvContent = '';

        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $csvContent = file_get_contents($_FILES['csv_file']['tmp_name']);
        } elseif (!empty($_POST['csv_text'])) {
            $csvContent = $_POST['csv_text'];
        }

        if (empty(trim($csvContent))) {
            $this->app->setFlash('danger', 'Please provide CSV content via file upload or paste in text area.');
            redirect('/csv/import');
        }

        try {
            $preview = $this->csvService->previewImport($csvContent);
            $this->app->render('csv/preview', [
                'preview' => $preview,
                'rawContent' => $csvContent
            ]);
        } catch (\Throwable $e) {
            $this->app->setFlash('danger', $e->getMessage());
            redirect('/csv/import');
        }
    }

    public function commit(): void
    {
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required.');
            return;
        }

        $rawJson = $_POST['rows_json'] ?? '';
        $rows = json_decode($rawJson, true);
        if (!is_array($rows)) {
            $this->app->setFlash('danger', 'Invalid preview payload.');
            redirect('/csv/import');
        }

        $updateEmails = $_POST['update_emails'] ?? [];
        $adminId = $this->app->getAuth()->user()['id'] ?? 1;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Web';
        $baseUrl = $this->app->getConfig()['app']['base_url'] ?? '';

        try {
            $res = $this->csvService->commitImport($rows, $updateEmails, $adminId, $ip, $ua, $baseUrl);
            $msg = sprintf(
                "CSV import committed: %d new organizations, %d new members created, %d members updated, %d skipped/conflicts safely bypassed.",
                $res['created_orgs'],
                $res['created_members'],
                $res['updated_members'],
                $res['skipped']
            );
            $this->app->setFlash('success', $msg);
            redirect('/organizations');
        } catch (\Throwable $e) {
            $this->app->setFlash('danger', $e->getMessage());
            redirect('/csv/import');
        }
    }


    public function exportOrganizations(): void
    {
        $csv = $this->csvService->exportOrganizationsCsv();
        $filename = 'convota_organizations_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        echo "\xEF\xBB\xBF";
        echo $csv;
        exit;
    }

    public function exportMembers(): void
    {
        $orgId = isset($_GET['org_id']) ? (int)$_GET['org_id'] : null;
        $csv = $this->csvService->exportMembersCsv($orgId);
        $filename = 'convota_members_roster_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        echo "\xEF\xBB\xBF";
        echo $csv;
        exit;
    }
}
