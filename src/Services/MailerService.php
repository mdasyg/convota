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
        $driver = $this->config['driver'] ?? 'log';
        $logFile = $this->config['log_file'] ?? (__DIR__ . '/../../data/mail.log');

        $headers = [
            'From' => "{$fromName} <{$from}>",
            'Reply-To' => $from,
            'X-Mailer' => 'Convota/1.0',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8'
        ];

        $success = true;

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (empty($recipient)) {
                continue;
            }

            if ($driver === 'mail' && function_exists('mail')) {
                $headerStr = '';
                foreach ($headers as $k => $v) {
                    $headerStr .= "{$k}: {$v}\r\n";
                }
                $sent = @mail($recipient, $subject, $bodyText, $headerStr);
                if (!$sent) {
                    $success = false;
                }
            } else {
                // Log driver (default for reliability, tests, and audit trail)
                $dir = dirname($logFile);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0750, true);
                }
                $entry = sprintf(
                    "[%s] TO: %s | SUBJECT: %s\n%s\n%s\n\n",
                    gmdate('Y-m-d H:i:s'),
                    $recipient,
                    $subject,
                    str_repeat('-', 40),
                    $bodyText
                );
                @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            }

            // Cryptographic audit log for email sent events
            if ($this->audit !== null) {
                $this->audit->log('email_sent', $actorUserId, $orgId, [
                    'event_type' => $eventType,
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'driver' => $driver
                ]);
            }
        }

        return $success;
    }

    /**
     * Send new member welcome & password setup link
     */
    public function sendPasswordSetup(string $email, string $name, string $resetToken, string $baseUrl = '', ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $link = rtrim($baseUrl, '/') . '/auth/reset-password?token=' . urlencode($resetToken);
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
        $link = rtrim($baseUrl, '/') . '/auth/reset-password?token=' . urlencode($resetToken);
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
    public function sendHashLoginAlert(array $memberEmails, string $orgName, string $ip, ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $subject = "[Security Alert] Organization Access Key Used for {$orgName}";
        $body = "Dear Member,\n\n"
              . "The shared Organization Access Key for '{$orgName}' was just used to log into Convota.\n\n"
              . "Time (UTC): " . gmdate('Y-m-d H:i:s') . "\n"
              . "IP Address: {$ip}\n\n"
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
        $loginUrl = rtrim($baseUrl, '/') . '/auth/hash-login?hash=' . urlencode($hash);

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
    public function sendVoteCastAlert(array $memberEmails, string $orgName, string $proposalTitle, string $voterName, string $timestamp, ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $subject = "[Vote Confirmed] Your Organization Cast a Vote: '{$proposalTitle}'";
        $body = "Dear Members of {$orgName},\n\n"
              . "Your organization's vote for proposal '{$proposalTitle}' has been successfully cast by:\n\n"
              . "Representative: {$voterName}\n"
              . "Timestamp: {$timestamp} (UTC)\n\n"
              . "This notification is sent to all registered members of {$orgName} to ensure internal transparency.\n\n"
              . "Best regards,\nConvota Assembly System";

        return $this->send($memberEmails, $subject, $body, 'vote_cast_alert', $actorUserId, $orgId);
    }

    /**
     * Dispatch voting open alert
     */
    public function sendVotingStartedAlert(array $memberEmails, string $proposalTitle, string $votingLink, ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $subject = "[Voting Open] General Assembly Proposal: '{$proposalTitle}'";
        $body = "Dear Member,\n\n"
              . "Voting has now opened for the following proposal:\n"
              . "'{$proposalTitle}'\n\n"
              . "To view the proposal and cast your vote, visit:\n"
              . "{$votingLink}\n\n"
              . "Best regards,\nConvota Assembly Presidium";

        return $this->send($memberEmails, $subject, $body, 'voting_started_alert', $actorUserId, $orgId);
    }

    /**
     * Alert member participants of all organizations when an assembly meeting is scheduled
     */
    public function sendMeetingScheduledAlert(array $memberEmails, string $meetingTitle, string $scheduledAt, string $location, string $meetingUrl, ?int $actorUserId = null, ?int $orgId = null): bool
    {
        $subject = "[General Assembly Notice] Meeting Scheduled: '{$meetingTitle}'";
        $body = "Dear Assembly Member,\n\n"
              . "A new General Assembly meeting session has been scheduled in Convota:\n\n"
              . "Meeting Title: {$meetingTitle}\n"
              . "Scheduled Date & Time: {$scheduledAt} (UTC)\n"
              . "Location / Modality: {$location}\n\n"
              . "Please visit the following link to view meeting information and register your presence:\n"
              . "{$meetingUrl}\n\n"
              . "Best regards,\nConvota Assembly Presidium";

        return $this->send($memberEmails, $subject, $body, 'meeting_scheduled_alert', $actorUserId, $orgId);
    }
}
