<?php
include '../../config/db.php';

// Security Checks
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if (!isset($_SESSION['family_id'])) { header("Location: ../auth/logout.php"); exit(); }

$family_id = $_SESSION['family_id'];
$user_id = $_SESSION['user_id'];
$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
$err = isset($_GET['err']) ? htmlspecialchars($_GET['err']) : '';

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // 1. EDIT APPOINTMENT
    if ($_POST['action'] === 'edit_appointment') {
        $appt_id = intval($_POST['appt_id']);
        $title = trim($_POST['title']);
        $date = $_POST['appointment_date'];
        $location = trim($_POST['location']);

        if(empty($title) || empty($date) || empty($location)) {
            $err = "All fields are required.";
        } else {
            $checkStmt = $conn->prepare("SELECT a.id FROM appointments a JOIN parents p ON a.parent_id = p.id WHERE a.id = ? AND p.family_id = ?");
            $checkStmt->bind_param("ii", $appt_id, $family_id); $checkStmt->execute();
            if($checkStmt->get_result()->num_rows === 0) die("Access denied.");

            $updateStmt = $conn->prepare("UPDATE appointments SET title = ?, appointment_date = ?, location = ? WHERE id = ?");
            $updateStmt->bind_param("sssi", $title, $date, $location, $appt_id);
            if ($updateStmt->execute()) {
                header("Location: index.php?msg=" . urlencode("Appointment updated successfully!")); exit();
            } else { $err = "Error updating: " . $conn->error; }
        }
    }

    // 2. TOGGLE PARTICIPATION
    if ($_POST['action'] === 'toggle_participation') {
        $appt_id = intval($_POST['appt_id']);

        // Verify appointment belongs to family first
        $checkStmt = $conn->prepare("SELECT a.id FROM appointments a JOIN parents p ON a.parent_id = p.id WHERE a.id = ? AND p.family_id = ?");
        $checkStmt->bind_param("ii", $appt_id, $family_id);
        $checkStmt->execute();
        if($checkStmt->get_result()->num_rows === 0) die("Access denied.");

        // Check current status
        $statusStmt = $conn->prepare("SELECT appointment_id FROM appointment_participants WHERE appointment_id = ? AND user_id = ?");
        $statusStmt->bind_param("ii", $appt_id, $user_id);
        $statusStmt->execute();
        $isParticipating = $statusStmt->get_result()->num_rows > 0;

        if ($isParticipating) {
            // Leave
            $toggleStmt = $conn->prepare("DELETE FROM appointment_participants WHERE appointment_id = ? AND user_id = ?");
            $msgText = "You have left the appointment.";
        } else {
            // Join
            $toggleStmt = $conn->prepare("INSERT INTO appointment_participants (appointment_id, user_id) VALUES (?, ?)");
            $msgText = "You joined this appointment!";
        }
        $toggleStmt->bind_param("ii", $appt_id, $user_id);
        $toggleStmt->execute();

        header("Location: index.php?msg=" . urlencode($msgText));
        exit();
    }
}

// Fetch User Details
$userStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id); $userStmt->execute();
$userName = $userStmt->get_result()->fetch_assoc()['name'] ?? 'User';

// Fetch Parents List for Dashboard Display
$parentsListSql = "SELECT id, full_name, profile_photo FROM parents WHERE family_id = ? ORDER BY full_name ASC";
$parentsListStmt = $conn->prepare($parentsListSql);
$parentsListStmt->bind_param("i", $family_id);
$parentsListStmt->execute();
$parentsListRes = $parentsListStmt->get_result();
$parents = [];
while ($p = $parentsListRes->fetch_assoc()) {
    $parents[] = $p;
}

// Fetch Appointments (Updated query with participants)
$apptSql = "SELECT 
                a.id, a.title, a.appointment_date, a.location, 
                p.full_name as parent_name,
                GROUP_CONCAT(u.name SEPARATOR ', ') as participant_names,
                SUM(CASE WHEN ap.user_id = ? THEN 1 ELSE 0 END) as is_participating
            FROM appointments a 
            JOIN parents p ON a.parent_id = p.id 
            LEFT JOIN appointment_participants ap ON a.id = ap.appointment_id
            LEFT JOIN users u ON ap.user_id = u.id
            WHERE p.family_id = ? 
              AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) 
              AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            GROUP BY a.id
            ORDER BY a.appointment_date ASC";

$apptStmt = $conn->prepare($apptSql);
$apptStmt->bind_param("ii", $user_id, $family_id);
$apptStmt->execute();
$apptRes = $apptStmt->get_result();
$appointments = [];
while($row = $apptRes->fetch_assoc()) {
    $dateKey = date('Y-m-d', strtotime($row['appointment_date']));
    $appointments[$dateKey][] = $row;
}
$today = date('Y-m-d'); $tomorrow = date('Y-m-d', strtotime('+1 day'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard - Care4TheLove1</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
</head>
<body>

<?php include '../../includes/navbar.php'; ?>

<div class="container" style="margin-top: 100px; margin-bottom: 80px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 class="fw-black text-dark mb-1">Hello, <?= htmlspecialchars($userName) ?>! 👋</h2><p class="text-muted fw-bold mb-0">Here's your family care overview.</p></div>
        <a href="../family/settings.php" class="btn btn-light text-primary shadow-sm rounded-pill fw-bold px-3"><i class="fas fa-cog me-2"></i>Settings</a>
    </div>

    <?php if($msg): ?><div class="alert alert-success border-0 shadow-sm fw-bold"><i class="fas fa-check-circle me-2"></i><?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="alert alert-danger border-0 shadow-sm fw-bold"><i class="fas fa-exclamation-circle me-2"></i><?= $err ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                    <h5 class="fw-bold text-dark m-0"><i class="fas fa-users me-2 text-primary"></i>My Parents</h5>
                    <a href="../parents/add.php" class="btn btn-sm btn-primary-glass fw-bold shadow-sm"><i class="fas fa-user-plus me-2"></i>Add New</a>
                </div>
                <div class="row g-3">
                    <?php if (empty($parents)): ?>
                        <div class="col-12">
                            <div class="glass-card p-4 text-center text-muted opacity-75">
                                <i class="fas fa-user-slash fa-3x mb-3"></i><br>
                                <h6 class="fw-bold">No parents added yet.</h6>
                                <p class="small mb-3">Add a parent profile to start managing their care.</p>
                                <a href="../parents/add.php" class="btn btn-primary-glass fw-bold btn-sm">Add Parent Now</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($parents as $p): ?>
                            <div class="col-6 col-md-4">
                                <a href="../parents/view.php?id=<?= $p['id'] ?>" class="card-hover text-decoration-none">
                                    <div class="glass-card p-3 text-center h-100">
                                        <div class="mb-3 position-relative d-inline-block">
                                            <?php if (!empty($p['profile_photo'])): ?>
                                                <img src="../../<?= htmlspecialchars($p['profile_photo']) ?>" alt="<?= htmlspecialchars($p['full_name']) ?>" class="rounded-circle object-fit-cover shadow-sm border border-2 border-white" style="width: 80px; height: 80px;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center mx-auto shadow-sm border border-2 border-white" style="width: 80px; height: 80px;">
                                                    <i class="fas fa-user fa-3x text-primary opacity-50"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="position-absolute bottom-0 end-0 bg-white rounded-circle border border-light d-flex align-items-center justify-content-center shadow-sm" style="width: 28px; height: 28px;"><i class="fas fa-pencil-alt text-primary small"></i></div>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1 text-truncate"><?= htmlspecialchars($p['full_name']) ?></h6>
                                        <small class="text-primary fw-bold">View Profile</small>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="col-lg-4">
            <div class="glass-card h-100">
                <div class="d-flex justify-content-between align-items-center border-bottom border-light pb-3 mb-3">
                    <h5 class="fw-bold m-0 text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>Upcoming</h5>
                </div>
                <div class="d-flex flex-column gap-3" style="max-height: 600px; overflow-y: auto;">
                    <?php if(empty($appointments)): ?>
                        <div class="text-center text-muted small py-5 fw-bold opacity-75"><i class="far fa-calendar-check fa-3x mb-3 opacity-50"></i><br>No appointments coming up.</div>
                    <?php else: ?>
                        <?php foreach($appointments as $date => $dailyAppts):
                            $dayLabel = ($date === $today) ? 'Today' : (($date === $tomorrow) ? 'Tomorrow' : date('D, M j', strtotime($date)));
                            $isToday = ($date === $today);
                            ?>
                            <div>
                                <h6 class="small fw-bold text-uppercase ls-1 mb-2 <?= $isToday ? 'text-primary' : 'text-muted' ?>"><?= $dayLabel ?></h6>
                                <?php foreach($dailyAppts as $appt):
                                    $participants = $appt['participant_names'] ?: 'No participants yet';
                                    $amIParticipating = $appt['is_participating'] > 0;
                                    $cardBg = $amIParticipating ? 'bg-success bg-opacity-10 border-success border-opacity-25' : 'bg-light border-0';
                                    ?>
                                    <button type="button" class="btn <?= $cardBg ?> w-100 text-start p-3 rounded-4 shadow-sm mb-2 d-flex align-items-center card-hover appt-trigger border"
                                            data-bs-toggle="modal"
                                            data-bs-target="#appointmentModal"
                                            data-id="<?= $appt['id'] ?>"
                                            data-title="<?= htmlspecialchars($appt['title']) ?>"
                                            data-date="<?= $appt['appointment_date'] ?>"
                                            data-location="<?= htmlspecialchars($appt['location'] ?? 'No location set') ?>"
                                            data-parent="<?= htmlspecialchars($appt['parent_name']) ?>"
                                            data-participants="<?= htmlspecialchars($participants) ?>"
                                            data-is-participating="<?= $amIParticipating ? '1' : '0' ?>">
                                        <div class="bg-white rounded-3 text-center me-3 p-2 d-flex flex-column align-items-center justify-content-center shadow-sm" style="min-width: 55px; height: 55px;">
                                            <small class="d-block fw-bold text-muted" style="font-size: 0.7rem; line-height:1"><?= date('M', strtotime($appt['appointment_date'])) ?></small>
                                            <span class="d-block h4 mb-0 fw-black text-dark" style="line-height:1;"><?= date('d', strtotime($appt['appointment_date'])) ?></span>
                                        </div>
                                        <div class="overflow-hidden flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <div class="fw-bold text-dark text-truncate"><?= htmlspecialchars($appt['title']) ?></div>
                                                <small class="fw-bold text-primary"><?= date('g:i A', strtotime($appt['appointment_date'])) ?></small>
                                            </div>
                                            <small class="text-muted fw-bold text-truncate d-block"><i class="fas fa-user me-1 opacity-75"></i> <?= htmlspecialchars($appt['parent_name']) ?></small>
                                            <small class="text-muted fst-italic text-truncate d-block small"><i class="fas fa-users me-1 opacity-50"></i> <?= htmlspecialchars($participants) ?></small>
                                        </div>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0 shadow-lg">
            <div class="modal-header border-bottom border-light p-4">
                <h5 class="modal-title fw-bold text-dark" id="apptModalTitle">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="apptViewDetails">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3 text-primary"><i class="fas fa-calendar-day fa-2x"></i></div>
                    <div><h4 class="fw-black text-dark mb-1" id="viewTitle"></h4><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small px-3" id="viewParent"></span></div>
                </div>
                <div class="vstack gap-3 mb-4">
                    <div class="d-flex align-items-center"><i class="far fa-clock me-3 text-muted fa-lg" style="width: 20px; text-align:center;"></i><div><small class="text-muted fw-bold text-uppercase ls-1 d-block">When</small><span class="fw-bold text-dark" id="viewDateStr"></span></div></div>
                    <div class="d-flex align-items-center"><i class="fas fa-map-marker-alt me-3 text-danger fa-lg" style="width: 20px; text-align:center;"></i><div><small class="text-muted fw-bold text-uppercase ls-1 d-block">Where</small><span class="fw-bold text-dark" id="viewLocation"></span></div></div>
                </div>
                <div class="bg-light p-3 rounded-3">
                    <h6 class="fw-bold text-dark mb-2 small text-uppercase ls-1"><i class="fas fa-users me-2 text-primary"></i>Who's going?</h6>
                    <p class="mb-0 text-muted fw-bold small" id="viewParticipants"></p>
                </div>
            </div>
            <div class="modal-body p-4 d-none" id="apptEditForm">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_appointment">
                    <input type="hidden" name="appt_id" id="editApptId">
                    <div class="form-floating mb-2"><input type="text" name="title" class="form-control border-0 bg-white shadow-sm" id="editTitle" placeholder="Title" required><label for="editTitle">Title</label></div>
                    <div class="form-floating mb-2"><input type="datetime-local" name="appointment_date" class="form-control border-0 bg-white shadow-sm" id="editDate" required><label for="editDate">Date & Time</label></div>
                    <div class="form-floating mb-3"><input type="text" name="location" class="form-control border-0 bg-white shadow-sm" id="editLocation" placeholder="Location" required><label for="editLocation">Location</label></div>
                    <div class="d-flex gap-2"><button type="button" class="btn btn-light text-muted fw-bold w-50" id="cancelEditBtn">Cancel</button><button type="submit" class="btn btn-primary-glass fw-bold w-50">Save Changes</button></div>
                </form>
            </div>
            <div class="modal-footer border-top border-light p-3 bg-white justify-content-between align-items-center" id="apptFooter">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light text-primary btn-sm shadow-sm" id="startEditBtn" data-bs-toggle="tooltip" title="Edit Details"><i class="fas fa-edit"></i></button>
                    <div class="vr opacity-25"></div>
                    <a href="#" id="googleCalBtn" target="_blank" class="btn btn-light text-danger btn-sm shadow-sm" data-bs-toggle="tooltip" title="Add to Google Calendar"><i class="fab fa-google"></i></a>
                    <a href="#" id="iCalBtn" class="btn btn-light text-dark btn-sm shadow-sm" data-bs-toggle="tooltip" title="Download iCal"><i class="fab fa-apple"></i></a>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="toggle_participation">
                    <input type="hidden" name="appt_id" id="toggleApptId">
                    <button type="submit" class="btn btn-lg fw-bold shadow-sm ps-4 pe-4" id="toggleParticipationBtn"></button>
                </form>
            </div>
        </div>
    </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const apptModal = document.getElementById('appointmentModal');
        const viewDetailsDiv = document.getElementById('apptViewDetails');
        const editFormDiv = document.getElementById('apptEditForm');
        const modalFooter = document.getElementById('apptFooter');
        const modalTitle = document.getElementById('apptModalTitle');
        const toggleBtn = document.getElementById('toggleParticipationBtn');
        let currentApptData = {};

        apptModal.addEventListener('show.bs.modal', function(event) {
            viewDetailsDiv.classList.remove('d-none'); editFormDiv.classList.add('d-none'); modalFooter.classList.remove('d-none'); modalTitle.textContent = "Appointment Details";
            const button = event.relatedTarget;
            currentApptData = {
                id: button.getAttribute('data-id'), title: button.getAttribute('data-title'), dateRaw: button.getAttribute('data-date'),
                location: button.getAttribute('data-location'), parent: button.getAttribute('data-parent'),
                participants: button.getAttribute('data-participants'), isParticipating: button.getAttribute('data-is-participating') === '1'
            };

            const dateObj = new Date(currentApptData.dateRaw.replace(' ', 'T'));
            const dateStr = dateObj.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            const timeStr = dateObj.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

            // Populate View
            document.getElementById('viewTitle').textContent = currentApptData.title; document.getElementById('viewParent').textContent = 'For: ' + currentApptData.parent;
            document.getElementById('viewDateStr').textContent = `${dateStr} at ${timeStr}`; document.getElementById('viewLocation').textContent = currentApptData.location;
            document.getElementById('viewParticipants').textContent = currentApptData.participants;

            // Populate Edit Forms
            document.getElementById('editApptId').value = currentApptData.id; document.getElementById('editTitle').value = currentApptData.title;
            document.getElementById('editDate').value = currentApptData.dateRaw.substring(0, 16).replace(' ', 'T'); document.getElementById('editLocation').value = currentApptData.location;
            document.getElementById('toggleApptId').value = currentApptData.id;

            // Setup Toggle Button State
            if (currentApptData.isParticipating) {
                toggleBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>I\'m Going (Leave)';
                toggleBtn.className = 'btn btn-outline-danger btn-lg fw-bold shadow-sm ps-4 pe-4';
            } else {
                toggleBtn.innerHTML = '<i class="fas fa-plus-circle me-2"></i>Join Appointment';
                toggleBtn.className = 'btn btn-success btn-lg fw-bold shadow-sm ps-4 pe-4 text-white';
            }

            setupCalendarLinks(currentApptData, dateObj);
        });

        document.getElementById('startEditBtn').addEventListener('click', function() { viewDetailsDiv.classList.add('d-none'); modalFooter.classList.add('d-none'); editFormDiv.classList.remove('d-none'); modalTitle.textContent = "Edit Appointment"; });
        document.getElementById('cancelEditBtn').addEventListener('click', function() { editFormDiv.classList.add('d-none'); viewDetailsDiv.classList.remove('d-none'); modalFooter.classList.remove('d-none'); modalTitle.textContent = "Appointment Details"; });

        function setupCalendarLinks(data, startDateObj) {
            const endDateObj = new Date(startDateObj.getTime() + (60 * 60 * 1000)); // Assume 1 hr
            const googleBtn = document.getElementById('googleCalBtn'); const iCalBtn = document.getElementById('iCalBtn');
            const gTitle = encodeURIComponent(data.title + ' - ' + data.parent); const gLoc = encodeURIComponent(data.location);
            const toIsoStringNoMs = (d) => d.toISOString().replace(/\.\d{3}Z$/, 'Z').replace(/[-:]/g, '');
            const gDates = toIsoStringNoMs(startDateObj) + '/' + toIsoStringNoMs(endDateObj);
            googleBtn.href = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${gTitle}&dates=${gDates}&location=${gLoc}&details=Family%20appointment%20for%20${encodeURIComponent(data.parent)}`;

            const newICalBtn = iCalBtn.cloneNode(true); iCalBtn.parentNode.replaceChild(newICalBtn, iCalBtn);
            newICalBtn.addEventListener('click', function(e) { e.preventDefault(); downloadICal(data, startDateObj, endDateObj); });
        }

        function downloadICal(data, startObj, endObj) {
            const formatICalDate = (date) => date.getFullYear() + ('0' + (date.getMonth() + 1)).slice(-2) + ('0' + date.getDate()).slice(-2) + 'T' + ('0' + date.getHours()).slice(-2) + ('0' + date.getMinutes()).slice(-2) + ('0' + date.getSeconds()).slice(-2);
            const now = formatICalDate(new Date()); const start = formatICalDate(startObj); const end = formatICalDate(endObj);
            const icsContent = ['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Care4TheLove1//EN','BEGIN:VEVENT','UID:' + now + '-' + data.id + '@careApp','DTSTAMP:' + now,'DTSTART;TZID=' + Intl.DateTimeFormat().resolvedOptions().timeZone + ':' + start,'DTEND;TZID=' + Intl.DateTimeFormat().resolvedOptions().timeZone + ':' + end,'SUMMARY:' + data.title + ' - ' + data.parent,'LOCATION:' + data.location.replace(/,/g, '\\,'),'DESCRIPTION:Family appointment for ' + data.parent,'END:VEVENT','END:VCALENDAR'].join('\r\n');
            const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' }); const link = document.createElement('a'); link.href = window.URL.createObjectURL(blob); link.setAttribute('download', 'appointment.ics'); document.body.appendChild(link); link.click(); document.body.removeChild(link);
        }
    });
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]'); const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
</script>
</body>
</html>
