<?php
require_once "db.php";

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.";
    exit;
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS absence_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employe_id INT NOT NULL,
    month_key VARCHAR(7) NOT NULL,
    days_absent INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_alert (employe_id, month_key),
    INDEX idx_alert_month (month_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$adminRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT matricule FROM users WHERE role='admin' ORDER BY matricule ASC LIMIT 1"));
if (!$adminRow) {
    echo "No admin found.\n";
    exit;
}
$adminId = (int) $adminRow['matricule'];

$monthStart = date('Y-m-01');
$todayDate = date('Y-m-d');
$monthKey = date('Y-m');
$daysElapsed = (int) date('j');

$monthlyPresence = mysqli_fetch_all(mysqli_query($conn, "SELECT u.matricule,u.prenom,u.nom,COALESCE(p.present_days,0) present_days
    FROM users u
    LEFT JOIN (
        SELECT employe_id, COUNT(*) present_days
        FROM employe_presence
        WHERE presence_date BETWEEN '$monthStart' AND '$todayDate'
        GROUP BY employe_id
    ) p ON p.employe_id = u.matricule
    WHERE u.role='employe'
    ORDER BY u.nom, u.prenom"), MYSQLI_ASSOC);

$sent = 0;
foreach ($monthlyPresence as $row) {
    $empId = (int) $row['matricule'];
    $absentDays = max(0, $daysElapsed - (int) ($row['present_days'] ?? 0));
    if ($absentDays <= 5) {
        continue;
    }

    $check = mysqli_prepare($conn, "SELECT id FROM absence_alerts WHERE employe_id=? AND month_key=? LIMIT 1");
    if (!$check) {
        continue;
    }
    mysqli_stmt_bind_param($check, "is", $empId, $monthKey);
    mysqli_stmt_execute($check);
    $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
    mysqli_stmt_close($check);
    if ($exists) {
        continue;
    }

    $alertMessage = "Alerte absence: vous avez {$absentDays} jours d'absence ce mois-ci. Merci de contacter l'administration.";
    $msgStmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    if ($msgStmt) {
        mysqli_stmt_bind_param($msgStmt, "iis", $adminId, $empId, $alertMessage);
        mysqli_stmt_execute($msgStmt);
        mysqli_stmt_close($msgStmt);
    }

    $ins = mysqli_prepare($conn, "INSERT INTO absence_alerts (employe_id, month_key, days_absent) VALUES (?, ?, ?)");
    if ($ins) {
        mysqli_stmt_bind_param($ins, "isi", $empId, $monthKey, $absentDays);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
    }
    $sent++;
}

echo "Alerts sent: {$sent}\n";

