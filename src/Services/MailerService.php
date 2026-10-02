<?php
declare(strict_types=1);

namespace Convota\Services;

/**
 * Mailer & Notification Dispatcher for Assembly and Voter Alerts
 */
class MailerService
{
    private array $config;
    private ?AuditService $audit = null;

    public function __construct(?array $config = null, ?AuditService $audit = null)
    {
        if ($config === null) {
            $appConfig = require __DIR__ . '/../../config/config.php';
            $this->config = $appConfig['mail'];
        } else {
            $this->config = $config;
        }

        $this->audit = $audit;
    }

    public function setAudit(AuditService $audit): void
    {
        $this->audit = $audit;
    }

    /**
     * Dispatch an email notification and record in tamper-evident audit trail
     */
    public function send(
        string|array $to,
        string $subject,
        string $bodyText,
        string $eventType = 'general_alert',
        ?int $actorUserId = null,
        ?int $orgId = null
    ): bool {
        $recipients = is_array($to) ? $to : [$to];
        $from = $this->config['from_address'] ?? 'no-reply@email.vlsi.gr';
        $fromName = $this->config['from_name'] ?? 'Convota Assembly System';
        $driver = $this->config['driver'] ?? 'mail';
        $logFile = $this->config['log_file'] ?? (__DIR__ . '/../../data/mail.log');

        $headers = [
            'From' => "{$fromName} <{$from}>",
            'Reply-To' => $from,
            'X-Mailer' => 'Convota/1.0',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8'
        ];

        $overallSuccess = true;

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (empty($recipient)) {
                continue;
            }

            $sent = false;
            $failReason = null;

            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $sent = false;
                $failReason = "Invalid recipient email address format: '{$recipient}'";
            } elseif ($driver === 'mail') {
                if (!function_exists('mail')) {
                    $sent = false;
                    $failReason = "PHP mail() function is not available or disabled in php.ini";
                } else {
                    $headerStr = '';
                    foreach ($headers as $k => $v) {
                        $headerStr .= "{$k}: {$v}\r\n";
                    }

                    error_clear_last();
                    // Attempt mail delivery with envelope sender (-f)
                    $sent = @mail($recipient, $subject, $bodyText, $headerStr, "-f " . escapeshellarg($from));
                    if (!$sent) {
                        $lastErr = error_get_last();
                        // Fallback attempt without additional parameters in case MTA restricts -f
                        $sent = @mail($recipient, $subject, $bodyText, $headerStr);
                        if (!$sent) {
                            $err = error_get_last() ?? $lastErr;
                            $failReason = !empty($err['message']) 
                                ? $err['message'] 
                                : 'PHP mail() returned false (MTA delivery rejected or sendmail error)';
                        }
                    }
                }
            } else {
                // Driver is 'log'
                $sent = true;
            }

            // Always record in data/mail.log for traceability and debugging
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            $statusLabel = $sent ? 'SUCCESS' : 'FAILED';
            $logEntry = sprintf(
                "[%s] STATUS: %s (driver: %s%s) | TO: %s | SUBJECT: %s\n%s\n%s\n\n",
                gmdate('Y-m-d H:i:s'),
                $statusLabel,
                $driver,
                $failReason ? " | REASON: {$failReason}" : "",
                $recipient,
                $subject,
                str_repeat('-', 40),
                $bodyText
            );
            @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

            if (!$sent) {
                $overallSuccess = false;
            }

            // Cryptographic audit log for email events (recording SUCCESS or FAILED with reason)
            if ($this->audit !== null) {
                $action = $sent ? 'email_sent' : 'email_failed';
                $this->audit->log($action, $actorUserId, $orgId, [
                    'status' => $sent ? 'SUCCESS' : 'FAILED',
                    'event_type' => $eventType,
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'driver' => $driver,
                    'reason' => $failReason
                ]);
            }
        }

        return $overallSuccess;
    }

    /**
     * Build an absolute URL containing discovered scheme, host/domain, and base path
     */
    public function buildFullUrl(string $pathOrUrl, string $baseUrl = ''): string
    {
        // 1. If already a full URL with scheme, return as-is
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            return $pathOrUrl;
        }

        // 2. If a full baseUrl with scheme was explicitly provided, use it
        if (!empty($baseUrl) && preg_match('#^https?://#i', $baseUrl)) {
            $base = rtrim($baseUrl, '/');
            $path = '/' . ltrim($pathOrUrl, '/');
            $parsedBase = parse_url($base);
            $basePath = $parsedBase['path'] ?? '';
            if ($basePath !== '' && $basePath !== '/') {
                if (str_starts_with($path, $basePath . '/')) {
                    $path = substr($path, strlen($basePath));
                }
            }
            return $base . $path;
        }

        // 3. Delegate to App::fullUrl if available
        if (class_exists('\\Convota\\App')) {
            if (!empty($baseUrl) && !str_starts_with($baseUrl, 'http')) {
                $base = '/' . trim($baseUrl, '/');
                $path = '/' . ltrim($pathOrUrl, '/');
                if (!str_starts_with($path, $base . '/')) {
                    $path = $base . $path;
                }
                return \Convota\App::fullUrl($path);
            }
            return \Convota\App::fullUrl($pathOrUrl);
        }

        // 4. Standalone fallback
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'vm.dyn.vlsi.gr');
        $base = !empty($baseUrl) ? '/' . trim($baseUrl, '/') : '';
        $path = '/' . ltrim($pathOrUrl, '/');
        return $proto . '://' . $host . $base . $path;
    }

    /**
     * Send new member welcome & password setup link
     */
    public function sendPasswordSetup(string $email, string $name, string $resetToken, string $baseUrl = '', ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $link = $this->buildFullUrl('/auth/reset-password?token=' . urlencode($resetToken), $baseUrl);
        $subject = 'Welcome to Convota — Set Your Password';
        $body = "Hello {$name},\n\n"
              . "You have been registered as an assembly representative in Convota.\n"
              . "Please visit the following link to set your password and access the assembly system:\n\n"
              . "{$link}\n\n"
              . "This setup link is valid for 24 hours.\n\n"
              . "Best regards,\nConvota Assembly Presidium";

        return $this->send($email, $subject, $body, 'password_setup', $actorUserId, $orgId);
    }

    /**
     * Send password reset token email
     */
    public function sendPasswordReset(string $email, string $name, string $resetToken, string $baseUrl = '', ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $link = $this->buildFullUrl('/auth/reset-password?token=' . urlencode($resetToken), $baseUrl);
        $subject = 'Convota — Password Reset Request';
        $body = "Hello {$name},\n\n"
              . "A password reset request was received for your Convota account.\n"
              . "To reset your password, visit the following link:\n\n"
              . "{$link}\n\n"
              . "If you did not request this, you can safely ignore this message.\n\n"
              . "Best regards,\nConvota Security Team";

        return $this->send($email, $subject, $body, 'password_reset', $actorUserId, $orgId);
    }

    /**
     * Alert members when organization common hash is used to log in
     */
    public function sendHashLoginAlert(array $memberEmails, string $orgName, string $ip, ?int $actorUserId = null, ?int $orgId = null, string $baseUrl = ''): bool
    {
        $portalUrl = $this->buildFullUrl('/organizations/portal', $baseUrl);
        $subject = "[Security Alert] Organization Access Key Used for {$orgName}";
        $body = "Dear Member,\n\n"
              . "The shared Organization Access Key for '{$orgName}' was just used to log into Convota.\n\n"
              . "Time (UTC): " . gmdate('Y-m-d H:i:s') . "\n"
              . "IP Address: {$ip}\n\n"
              . "Organization Portal Link:\n"
              . "{$portalUrl}\n\n"
              . "If this was an authorized action by your delegation, no action is needed.\n"
              . "If this was unexpected, please contact your organization administrator immediately to rotate the access key.\n\n"
              . "Best regards,\nConvota System";

        return $this->send($memberEmails, $subject, $body, 'hash_login_alert', $actorUserId, $orgId);
    }

    /**
     * Email the Organization Access Key to members
     */
    public function sendOrganizationHash(array $memberEmails, string $orgName, string $hash, bool $isRotated = false, string $baseUrl = '', ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $statusText = $isRotated ? "reinitialized and rotated" : "requested";
        $loginUrl = $this->buildFullUrl('/member_login.sh&hash=' . urlencode($hash), $baseUrl);

        $subject = "Organization Access Key for {$orgName}";
        $body = "Dear Members of {$orgName},\n\n"
              . "The shared Organization Access Key has been {$statusText}.\n\n"
              . "Your Organization Access Key is:\n"
              . "{$hash}\n\n"
              . "Direct Access Link:\n"
              . "{$loginUrl}\n\n"
              . "This key allows managing your organization's roster and designating your active assembly voter.\n\n"
              . "Best regards,\nConvota Presidium";

        return $this->send($memberEmails, $subject, $body, 'organization_hash_distribution', $actorUserId, $orgId);
    }

    /**
     * Alert members immediately when their organization casts its vote
     */
    public function sendVoteCastAlert(array $memberEmails, string $orgName, string $proposalTitle, string $voterName, string $timestamp, ?int $actorUserId = null, ?int $orgId = null, string $baseUrl = ''): bool
    {
        $proposalsUrl = $this->buildFullUrl('/proposals', $baseUrl);
        $subject = "[Vote Confirmed] Your Organization Cast a Vote: '{$proposalTitle}'";
        $body = "Dear Members of {$orgName},\n\n"
              . "Your organization's vote for proposal '{$proposalTitle}' has been successfully cast by:\n\n"
              . "Representative: {$voterName}\n"
              . "Timestamp: {$timestamp} (UTC)\n\n"
              . "Assembly Proposals & Results:\n"
              . "{$proposalsUrl}\n\n"
              . "This notification is sent to all registered members of {$orgName} to ensure internal transparency.\n\n"
              . "Best regards,\nConvota Assembly System";

        return $this->send($memberEmails, $subject, $body, 'vote_cast_alert', $actorUserId, $orgId);
    }

    /**
     * Dispatch voting open alert
     */
    public function sendVotingStartedAlert(array $memberEmails, string $proposalTitle, string $votingLink, ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $link = $this->buildFullUrl($votingLink);
        $subject = "[Voting Open] General Assembly Proposal: '{$proposalTitle}'";
        $body = "Dear Member,\n\n"
              . "Voting has now opened for the following proposal:\n"
              . "'{$proposalTitle}'\n\n"
              . "To view the proposal and cast your vote, visit:\n"
              . "{$link}\n\n"
              . "Best regards,\nConvota Assembly Presidium";

        return $this->send($memberEmails, $subject, $body, 'voting_started_alert', $actorUserId, $orgId);
    }

    /**
     * Alert member participants of all organizations when an assembly meeting is scheduled
     */
    public function sendMeetingScheduledAlert(array $memberEmails, string $meetingTitle, string $scheduledAt, string $location, string $meetingUrl, ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $url = $this->buildFullUrl($meetingUrl);
        $subject = "[General Assembly Notice] Meeting Scheduled: '{$meetingTitle}'";
        $body = "Dear Assembly Member,\n\n"
              . "A new General Assembly meeting session has been scheduled in Convota:\n\n"
              . "Meeting Title: {$meetingTitle}\n"
              . "Scheduled Date & Time: {$scheduledAt} (UTC)\n"
              . "Location / Modality: {$location}\n\n"
              . "Please visit the following link to view meeting information and register your presence:\n"
              . "{$url}\n\n"
              . "Best regards,\nConvota Assembly Presidium";

        return $this->send($memberEmails, $subject, $body, 'meeting_scheduled_alert', $actorUserId, $orgId);
    }

    /**
     * Send attendance reminder link to members of an organization that has not configured its attending representative
     */
    public function sendPresenceReminder(
        array $memberEmails,
        string $orgName,
        string $meetingTitle,
        string $scheduledAt,
        string $location,
        string $presenceUrl,
        string $directAccessUrl = '',
        ?int $actorUserId = null,
        ?int $orgId = null
    ): bool {
        $pUrl = $this->buildFullUrl($presenceUrl);
        $dUrl = !empty($directAccessUrl) ? $this->buildFullUrl($directAccessUrl) : '';

        $subject = "[Action Required] Attendance Reminder: No Attending Representative Configured for {$orgName}";
        $body = "Dear Member of {$orgName},\n\n"
              . "This is an urgent reminder from the General Assembly Presidium regarding the following meeting session:\n\n"
              . "Meeting Title: {$meetingTitle}\n"
              . "Scheduled Date & Time: {$scheduledAt} (UTC)\n"
              . "Location / Modality: {$location}\n\n"
              . "IMPORTANT NOTICE:\n"
              . "No participating representative has been configured or checked in for '{$orgName}' yet.\n\n"
              . "To ensure your organization is represented and eligible to participate and vote in this assembly session, "
              . "please configure who is online and attending immediately.\n\n"
              . "Direct Assembly Presence & Roll-Call Link:\n"
              . "{$pUrl}\n\n";

        if (!empty($dUrl)) {
            $body .= "Direct Organization Delegation Access Link (using shared access key):\n"
                   . "{$dUrl}\n\n"
                   . "Any delegation member can use the direct link above to enter the portal and select your attending voter.\n\n";
        }

        $body .= "Best regards,\nConvota Assembly Presidium";

        return $this->send($memberEmails, $subject, $body, 'presence_reminder', $actorUserId, $orgId);
    }
}

