<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Sends a plain-text email. Uses PHP's mail() (works out of the box on
 * the target shared hosting, which provides an SMTP relay for it).
 * While MAIL_DEV_MODE is true, messages are appended to data/mail.log
 * instead of actually being sent, so you can verify content before
 * mail() is confirmed working on the live host.
 */
function send_mail(string $toEmail, string $toName, string $subject, string $body): bool
{
    if (MAIL_DEV_MODE) {
        $line = sprintf(
            "[%s] To: %s <%s>\nSubject: %s\n\n%s\n\n---\n\n",
            date('Y-m-d H:i:s'),
            $toName,
            $toEmail,
            $subject,
            $body
        );
        file_put_contents(DATA_DIR . '/mail.log', $line, FILE_APPEND | LOCK_EX);
        return true;
    }

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'Reply-To: ' . MAIL_FROM_ADDRESS,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return mail($toEmail, $encodedSubject, $body, implode("\r\n", $headers));
}
