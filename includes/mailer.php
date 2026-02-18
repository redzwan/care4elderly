<?php
// includes/mailer.php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/pdf_generator.php'; // <--- LOAD GENERATOR

function sendSystemEmail($booking_id, $trigger_key) {
    global $conn;

    // 1. Fetch Booking Details (Join with users to get customer email/name)
    $sql = "SELECT b.*, u.email, u.name as user_full_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking) return false;

    // 2. Fetch Email Template
    $tSql = "SELECT * FROM email_templates WHERE trigger_key = ?";
    $tStmt = $conn->prepare($tSql);
    $tStmt->bind_param("s", $trigger_key);
    $tStmt->execute();
    $template = $tStmt->get_result()->fetch_assoc();
    if (!$template) return false;

    // 3. Fetch SMTP Settings
    $smtp = $conn->query("SELECT * FROM smtp_settings WHERE id=1")->fetch_assoc();

    // 4. Parse Placeholders
    $placeholders = [
        '{customer_name}' => $booking['sender_name'],
        '{booking_reference}' => '#' . $booking['id'],
        '{tracking_number}' => $booking['tracking_number'],
        '{service_type}' => str_replace('_', ' ', $booking['service_type']),
        '{weight}' => $booking['weight_kg'],
        '{price}' => number_format($booking['final_price'], 2),
        '{status}' => strtoupper(str_replace('_', ' ', $booking['status']))
    ];

    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $template['subject']);
    $body = str_replace(array_keys($placeholders), array_values($placeholders), $template['body']);

    // 5. Generate Attachments (PDFs)
    $attachments = [];

    if ($trigger_key === 'price_finalized') {
        $pdf = get_label_pdf($booking_id, $conn);
        if($pdf) $attachments[] = ['data' => $pdf->Output('S'), 'name' => 'Label_'.$booking['tracking_number'].'.pdf'];

        $pdf2 = get_invoice_pdf($booking_id, $conn);
        if($pdf2) $attachments[] = ['data' => $pdf2->Output('S'), 'name' => 'Invoice_'.$booking['tracking_number'].'.pdf'];
    }

    if ($trigger_key === 'payment_received') {
        $pdf = get_invoice_pdf($booking_id, $conn);
        if($pdf) $attachments[] = ['data' => $pdf->Output('S'), 'name' => 'Receipt_'.$booking['tracking_number'].'.pdf'];
    }

    // 6. SEND LOGIC (Admin vs Customer)
    if ($trigger_key === 'admin_new_booking') {
        // Fetch ALL Admin Emails
        $adminResult = $conn->query("SELECT email FROM users WHERE role = 'admin'");
        $sentCount = 0;
        if ($adminResult->num_rows > 0) {
            while ($admin = $adminResult->fetch_assoc()) {
                // Send to each admin individually
                if(send_socket_mail($smtp, $admin['email'], $subject, $body, $attachments)) {
                    $sentCount++;
                }
            }
        }
        return ($sentCount > 0);
    } else {
        // Standard Email to Customer
        // Use $booking['email'] (from users table join) or $booking['sender_email'] (from booking form)
        // Usually, the account email ($booking['email']) is more reliable for notifications.
        return send_socket_mail($smtp, $booking['email'], $subject, $body, $attachments);
    }
}

// --- SMTP SOCKET SENDER (Optimized for shared hosts) ---
function send_socket_mail($smtp, $to, $subject, $body, $attachments=[]) {
    $boundary = md5(uniqid(time()));
    $eol = "\r\n";

    $headers = "From: {$smtp['from_name']} <{$smtp['from_email']}>" . $eol;
    $headers .= "To: $to" . $eol;
    $headers .= "Subject: $subject" . $eol;
    $headers .= "MIME-Version: 1.0" . $eol;
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"" . $eol;

    $message = "--$boundary" . $eol;
    $message .= "Content-Type: text/html; charset=\"utf-8\"" . $eol;
    $message .= "Content-Transfer-Encoding: 7bit" . $eol . $eol;
    $message .= $body . $eol;

    foreach ($attachments as $att) {
        $message .= "--$boundary" . $eol;
        $message .= "Content-Type: application/pdf; name=\"{$att['name']}\"" . $eol;
        $message .= "Content-Transfer-Encoding: base64" . $eol;
        $message .= "Content-Disposition: attachment; filename=\"{$att['name']}\"" . $eol . $eol;
        $message .= chunk_split(base64_encode($att['data'])) . $eol;
    }
    $message .= "--$boundary--" . $eol;

    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $proto = ($smtp['encryption'] == 'ssl') ? 'ssl://' : 'tcp://';
    $conn = @stream_socket_client($proto . $smtp['host'] . ':' . $smtp['port'], $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);

    if (!$conn) return false;

    $ehloHost = $_ENV['APP_DOMAIN'] ?? 'localhost';

    fgets($conn);
    fputs($conn, "EHLO " . $ehloHost . $eol);
    while(substr(fgets($conn), 3, 1) != ' ');

    if ($smtp['encryption'] == 'tls') {
        fputs($conn, "STARTTLS" . $eol);
        fgets($conn);
        stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fputs($conn, "EHLO " . $ehloHost . $eol);
        while(substr(fgets($conn), 3, 1) != ' ');
    }

    fputs($conn, "AUTH LOGIN" . $eol);
    fgets($conn);
    fputs($conn, base64_encode($smtp['username']) . $eol);
    fgets($conn);
    fputs($conn, base64_encode($smtp['password']) . $eol);
    fgets($conn);

    fputs($conn, "MAIL FROM: <{$smtp['from_email']}>" . $eol);
    fgets($conn);
    fputs($conn, "RCPT TO: <$to>" . $eol);
    fgets($conn);
    fputs($conn, "DATA" . $eol);
    fgets($conn);
    fputs($conn, $headers . $eol . $message . $eol . "." . $eol);
    fgets($conn);
    fputs($conn, "QUIT" . $eol);
    fclose($conn);
    return true;
}

// --- VERIFICATION EMAIL HANDLER ---
function sendVerificationEmail($user_id, $token) {
    global $conn;

    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) return false;

    $tSql = "SELECT * FROM email_templates WHERE trigger_key = 'email_verification'";
    $template = $conn->query($tSql)->fetch_assoc();
    if (!$template) return false;

    $smtp = $conn->query("SELECT * FROM smtp_settings WHERE id=1")->fetch_assoc();

    $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_ENV['APP_DOMAIN'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
    $base_url = "http://" . $host . dirname($_SERVER['PHP_SELF']) . "/../auth/verify.php";
    $verify_link = str_replace('/includes/../auth', '/modules/auth', $base_url) . "?token=" . $token . "&email=" . urlencode($user['email']);

    $placeholders = [
        '{customer_name}' => $user['name'],
        '{verification_link}' => $verify_link
    ];

    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $template['subject']);
    $body = str_replace(array_keys($placeholders), array_values($placeholders), $template['body']);

    return send_socket_mail($smtp, $user['email'], $subject, $body, []);
}
?>
