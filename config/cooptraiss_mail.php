<?php
// config/cooptraiss_mail.php
// Wrapper único para envío de correos (PHPMailer + config/mail.php)

require_once __DIR__ . '/../clases/PHPMailer/PHPMailerAutoload.php';
require_once __DIR__ . '/mail.php';

function send_mail(string $to, string $subject, string $htmlBody, array $cc = [], array $bcc = [], array $attachments = []): void
{
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';

    // SMTP
    $mail->isSMTP();
    $mail->Host       = MAIL_SMTP_HOST;
    $mail->Port       = (int)MAIL_SMTP_PORT;
    $mail->SMTPAuth   = (bool)MAIL_SMTP_AUTH;

    // Seguridad
    if (MAIL_SMTP_SECURE === 'tls' || MAIL_SMTP_SECURE === 'ssl') {
        $mail->SMTPSecure = MAIL_SMTP_SECURE;
    }

    $mail->Username   = MAIL_SMTP_USER;
    $mail->Password   = MAIL_SMTP_PASS;

    // Remitente
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

    // Para evitar “header injection”
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Correo del asociado inválido o vacío.');
    }

    $mail->addAddress($to);

    // CC
    foreach ($cc as $addr) {
        $addr = trim((string)$addr);
        if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            $mail->addCC($addr);
        }
    }

    // BCC (si el config trae uno)
    if (defined('MAIL_BCC') && trim(MAIL_BCC) !== '' && filter_var(MAIL_BCC, FILTER_VALIDATE_EMAIL)) {
        $mail->addBCC(MAIL_BCC);
    }
    foreach ($bcc as $addr) {
        $addr = trim((string)$addr);
        if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            $mail->addBCC($addr);
        }
    }

    // Adjuntos opcionales
    foreach ($attachments as $a) {
        // $a = ['path' => '/ruta/archivo.pdf', 'name' => 'archivo.pdf']
        if (!empty($a['path']) && file_exists($a['path'])) {
            $mail->addAttachment($a['path'], $a['name'] ?? '');
        }
    }

    // Contenido
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;

    // Alternativo texto plano (simple)
    $mail->AltBody = strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $htmlBody));

    if (!$mail->send()) {
        throw new Exception('No fue posible enviar el correo.');
    }
}
