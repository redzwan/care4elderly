<?php
// cron/send_reminders.php
// Run this file once a day via Cron Job

include __DIR__ . '/../config/db.php';
include __DIR__ . '/../includes/mailer.php'; // Reusing your existing mailer

// 1. Find appointments happening TOMORROW (and reminder not sent yet)
$sql = "SELECT a.id, a.title, a.appointment_date, a.location, p.full_name, u.name as user_name, u.email 
        FROM appointments a
        JOIN parents p ON a.parent_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE a.appointment_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
        AND a.reminder_sent = 0";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // 2. Fetch Template
    $tSql = "SELECT * FROM email_templates WHERE trigger_key = 'appointment_reminder'";
    $template = $conn->query($tSql)->fetch_assoc();
    $smtp = $conn->query("SELECT * FROM smtp_settings WHERE id=1")->fetch_assoc();

    while ($row = $result->fetch_assoc()) {
        // Prepare Email Content
        $placeholders = [
            '{user_name}' => $row['user_name'],
            '{parent_name}' => $row['full_name'],
            '{title}' => $row['title'],
            '{date_time}' => date('d M Y, h:i A', strtotime($row['appointment_date'])),
            '{location}' => $row['location']
        ];

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $template['subject']);
        $body = str_replace(array_keys($placeholders), array_values($placeholders), $template['body']);

        // Send Email (using your existing logic)
        if (send_socket_mail($smtp, $row['email'], $subject, $body)) {
            // Mark as sent so we don't spam
            $updateStmt = $conn->prepare("UPDATE appointments SET reminder_sent = 1 WHERE id = ?"); $updateStmt->bind_param("i", $row['id']); $updateStmt->execute();
            echo "Reminder sent to " . $row['email'] . "<br>";
        } else {
            echo "Failed to send to " . $row['email'] . "<br>";
        }
    }
} else {
    echo "No appointments found for tomorrow.";
}
?>
