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
            $file = $_FILES['csv_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->app->setFlash('danger', 'Error uploading file (Code ' . $file['error'] . ').');
                redirect('/csv/import');
            }

            // Enforce maximum size: 5MB
            if ($file['size'] > 5 * 1024 * 1024) {
                $this->app->setFlash('danger', 'CSV file exceeds maximum permitted size of 5 MB.');
                redirect('/csv/import');
            }

            // Validate extension
            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'], true)) {
                $this->app->setFlash('danger', 'Invalid file extension. Only .csv and .txt files are allowed.');
                redirect('/csv/import');
            }

            // Validate MIME type
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel', 'application/octet-stream'];
                if (!in_array($mime, $allowedMimes, true)) {
                    $this->app->setFlash('danger', 'Invalid file type (' . htmlspecialchars((string)$mime, ENT_QUOTES, 'UTF-8') . '). Only standard CSV files are permitted.');
                    redirect('/csv/import');
                }
            }

            $csvContent = file_get_contents($file['tmp_name']);
        } elseif (!empty($_POST['csv_text'])) {
            $csvContent = $_POST['csv_text'];
        }

        if (empty(trim($csvContent))) {
            $this->app->setFlash('danger', 'Please provide CSV content via file upload or paste in text area.');
            redirect('/csv/import');
        }

        try {
            $preview = $this->csvService->previewImport($csvContent);
            $appKey = $this->app->getConfig()['app']['app_key'] ?? 'convota_secret_staging_key_2026_secure';
            $rowsHash = hash_hmac('sha256', json_encode($preview['rows']), $appKey);

            $this->app->render('csv/preview', [
                'preview' => $preview,
                'rowsHash' => $rowsHash,
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
        $rowsHash = $_POST['rows_hash'] ?? '';
        $appKey = $this->app->getConfig()['app']['app_key'] ?? 'convota_secret_staging_key_2026_secure';
        $expectedHash = hash_hmac('sha256', $rawJson, $appKey);

        if (empty($rowsHash) || !hash_equals($expectedHash, $rowsHash)) {
            $this->app->renderError(403, 'CSV preview payload integrity verification failed. Potential parameter tampering detected.');
            return;
        }

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
                "%d new lines were inserted. %d lines were identical with the database.",
                $res['created_members'],
                $res['identical_lines'] ?? 0
            );
            if (!empty($res['updated_members']) || !empty($res['skipped'])) {
                $msg .= sprintf(" (%d updated, %d skipped/conflicts safely bypassed)", $res['updated_members'], $res['skipped']);
            }
            $this->app->setFlash('success', $msg);
            redirect('/organizations');
        } catch (\Throwable $e) {
            $this->app->setFlash('danger', $e->getMessage());
            redirect('/csv/import');
        }
    }


    public function exportOrganizations(): void
    {
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required to export organization records.');
            return;
        }

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
        if (!$this->app->getAuth()->isAdmin()) {
            $this->app->renderError(403, 'Administrator privileges required to export member rosters.');
            return;
        }

        $orgId = isset($_GET['org_id']) ? (int)$_GET['org_id'] : null;
        $includeAuthHash = !empty($_GET['include_auth_hash']) && in_array(strtolower((string)$_GET['include_auth_hash']), ['1', 'true', 'on', 'yes'], true);

        if ($includeAuthHash) {
            $admin = $this->app->getAuth()->user();
            $actorId = $admin ? (int)$admin['id'] : null;
            $this->app->getAudit()->log('csv_members_auth_hash_exported', $actorId, $orgId, [
                'include_auth_hash' => true,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        }

        $csv = $this->csvService->exportMembersCsv($orgId, $includeAuthHash);
        $filename = 'convota_members_roster_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        echo "\xEF\xBB\xBF";
        echo $csv;
        exit;
    }
}
