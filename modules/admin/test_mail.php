<?php
/**
 * AJAX endpoint to send a test email using current SMTP settings.
 * Returns JSON response with success/failure and diagnostic details.
 */
header('Content-Type: application/json');

include '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit();
}

$test_email = trim($_POST['test_email'] ?? '');
if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit();
}

// Fetch SMTP Settings
$smtp = $conn->query("SELECT * FROM smtp_settings WHERE id=1")->fetch_assoc();
if (!$smtp) {
    echo json_encode(['success' => false, 'message' => 'No SMTP settings found. Please save your configuration first.']);
    exit();
}

// Build test email
$subject = "Test Email from Care4Elderly";
$body = '
<div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px;">
    <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 20px; border-radius: 12px 12px 0 0; text-align: center;">
        <h2 style="color: #fff; margin: 0;">✅ SMTP Test Successful</h2>
    </div>
    <div style="background: #ffffff; padding: 25px; border: 1px solid #e0e0e0; border-radius: 0 0 12px 12px;">
        <p style="color: #333; font-size: 15px; line-height: 1.6;">
            This is a test email from your <strong>Care4Elderly</strong> system.
            If you are reading this, your SMTP email settings are configured correctly!
        </p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <table style="width: 100%; font-size: 13px; color: #666;">
            <tr><td style="padding: 4px 0;"><strong>Host:</strong></td><td>' . htmlspecialchars($smtp['host']) . '</td></tr>
            <tr><td style="padding: 4px 0;"><strong>Port:</strong></td><td>' . htmlspecialchars($smtp['port']) . '</td></tr>
            <tr><td style="padding: 4px 0;"><strong>Encryption:</strong></td><td>' . strtoupper(htmlspecialchars($smtp['encryption'])) . '</td></tr>
            <tr><td style="padding: 4px 0;"><strong>From:</strong></td><td>' . htmlspecialchars($smtp['from_email']) . '</td></tr>
            <tr><td style="padding: 4px 0;"><strong>Sent at:</strong></td><td>' . date('Y-m-d H:i:s') . '</td></tr>
        </table>
    </div>
</div>';

// Send using the detailed test sender (with diagnostic logging)
$result = send_test_mail($smtp, $test_email, $subject, $body);

echo json_encode($result);
exit();


/**
 * Send a test email with detailed SMTP conversation logging for diagnostics.
 */
function send_test_mail($smtp, $to, $subject, $body) {
    $eol = "\r\n";
    $log = [];

    // Build email content
    $headers  = "From: {$smtp['from_name']} <{$smtp['from_email']}>" . $eol;
    $headers .= "To: $to" . $eol;
    $headers .= "Subject: $subject" . $eol;
    $headers .= "MIME-Version: 1.0" . $eol;
    $headers .= "Content-Type: text/html; charset=\"utf-8\"" . $eol;
    $headers .= "Content-Transfer-Encoding: 7bit" . $eol;

    $message = $body;

    // Connect
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $proto = ($smtp['encryption'] == 'ssl') ? 'ssl://' : 'tcp://';
    $address = $proto . $smtp['host'] . ':' . $smtp['port'];

    $log[] = "Connecting to $address ...";

    $conn = @stream_socket_client($address, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);

    if (!$conn) {
        $log[] = "❌ Connection failed: $errstr ($errno)";
        return ['success' => false, 'message' => "Connection failed: $errstr", 'log' => $log];
    }

    $log[] = "✓ Connected";

    // Helper to send/receive SMTP commands
    $smtpSend = function($command, $expectCode = null) use ($conn, $eol, &$log) {
        if ($command !== null) {
            // Mask password in logs
            if (stripos($command, 'AUTH') === false && !preg_match('/^[A-Za-z0-9+\/=]+$/', trim($command))) {
                $log[] = "→ " . trim($command);
            } else {
                $log[] = "→ [AUTH DATA]";
            }
            fputs($conn, $command . $eol);
        }
        $response = '';
        while (true) {
            $line = fgets($conn, 512);
            if ($line === false) break;
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = intval(substr($response, 0, 3));
        $log[] = "← $code " . substr(trim($response), 4);

        if ($expectCode && $code !== $expectCode) {
            return ['ok' => false, 'code' => $code, 'response' => trim($response)];
        }
        return ['ok' => true, 'code' => $code, 'response' => trim($response)];
    };

    // SMTP Conversation
    $greeting = $smtpSend(null); // Read server greeting
    if (!$greeting['ok'] || $greeting['code'] !== 220) {
        // Still try, greeting might be unexpected code
    }

    $ehloHost = $_ENV['APP_DOMAIN'] ?? 'localhost';

    $r = $smtpSend("EHLO $ehloHost");
    if (!$r['ok']) {
        fclose($conn);
        $log[] = "❌ EHLO failed";
        return ['success' => false, 'message' => "EHLO failed: " . $r['response'], 'log' => $log];
    }
    // Read all EHLO response lines
    // (already handled by the smtpSend helper)

    // STARTTLS if needed
    if ($smtp['encryption'] == 'tls') {
        $r = $smtpSend("STARTTLS");
        if ($r['code'] !== 220) {
            fclose($conn);
            $log[] = "❌ STARTTLS failed";
            return ['success' => false, 'message' => "STARTTLS failed: " . $r['response'], 'log' => $log];
        }
        $log[] = "✓ TLS negotiation...";
        $crypto = @stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$crypto) {
            fclose($conn);
            $log[] = "❌ TLS handshake failed";
            return ['success' => false, 'message' => "TLS handshake failed.", 'log' => $log];
        }
        $log[] = "✓ TLS established";
        $smtpSend("EHLO $ehloHost");
    }

    // AUTH LOGIN
    $r = $smtpSend("AUTH LOGIN");
    if ($r['code'] !== 334) {
        fclose($conn);
        $log[] = "❌ AUTH LOGIN not accepted";
        return ['success' => false, 'message' => "Server rejected AUTH LOGIN.", 'log' => $log];
    }

    $r = $smtpSend(base64_encode($smtp['username']));
    if ($r['code'] !== 334) {
        fclose($conn);
        $log[] = "❌ Username rejected";
        return ['success' => false, 'message' => "Username was rejected by the server.", 'log' => $log];
    }

    $r = $smtpSend(base64_encode($smtp['password']));
    if ($r['code'] !== 235) {
        fclose($conn);
        $log[] = "❌ Authentication failed";
        return ['success' => false, 'message' => "Authentication failed. Please check your username and password.", 'log' => $log];
    }
    $log[] = "✓ Authenticated";

    // MAIL FROM
    $r = $smtpSend("MAIL FROM: <{$smtp['from_email']}>");
    if ($r['code'] !== 250) {
        fclose($conn);
        $log[] = "❌ MAIL FROM rejected";
        return ['success' => false, 'message' => "MAIL FROM rejected: " . $r['response'], 'log' => $log];
    }

    // RCPT TO
    $r = $smtpSend("RCPT TO: <$to>");
    if ($r['code'] !== 250) {
        fclose($conn);
        $log[] = "❌ RCPT TO rejected";
        return ['success' => false, 'message' => "Recipient rejected: " . $r['response'], 'log' => $log];
    }

    // DATA
    $r = $smtpSend("DATA");
    if ($r['code'] !== 354) {
        fclose($conn);
        $log[] = "❌ DATA command rejected";
        return ['success' => false, 'message' => "DATA command rejected: " . $r['response'], 'log' => $log];
    }

    // Send email content
    fputs($conn, $headers . $eol . $message . $eol . "." . $eol);
    $response = fgets($conn, 512);
    $code = intval(substr($response, 0, 3));
    $log[] = "← $code " . substr(trim($response), 4);

    if ($code !== 250) {
        fclose($conn);
        $log[] = "❌ Message not accepted";
        return ['success' => false, 'message' => "Server did not accept the message.", 'log' => $log];
    }

    $log[] = "✓ Message accepted for delivery";

    // QUIT
    fputs($conn, "QUIT" . $eol);
    fclose($conn);

    $log[] = "✓ Connection closed";

    return ['success' => true, 'message' => "Test email sent successfully to $to!", 'log' => $log];
}
