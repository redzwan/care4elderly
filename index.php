<?php
session_start();
// If logged in, go to dashboard. If not, go to login.
if (isset($_SESSION['user_id'])) {
    header("Location: modules/dashboard/index.php");
} else {
    header("Location: modules/auth/login.php");
}
exit();
?>
