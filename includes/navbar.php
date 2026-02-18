<?php
// AUTO-DETECT PATH CONTEXT
$isRoot = file_exists('config/db.php');
$basePath = $isRoot ? 'modules/' : '../';
?>

<nav class="navbar navbar-expand-lg navbar-glass fixed-top">
    <div class="container">
        <a class="navbar-brand" href="../dashboard/index.php">
            <img src="../../assets/icons/icon-512x512.png" alt="Care4TheLove1 Logo" height="60" class="d-inline-block align-text-top">
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav ms-auto align-items-center gap-2">

                <li class="nav-item">
                    <a class="nav-link text-dark" href="<?= $basePath ?>dashboard/index.php">Dashboard</a>
                </li>

                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link fw-bold text-primary" href="<?= $basePath ?>admin/users.php">
                            <i class="fas fa-users-cog me-1"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold text-primary" href="<?= $basePath ?>admin/settings.php">
                            <i class="fas fa-cogs me-1"></i> Settings
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['family_role']) && $_SESSION['family_role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $basePath ?>family/settings.php">Family Settings</a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a href="<?= $basePath ?>auth/logout.php" class="btn btn-sm btn-outline-danger rounded-pill px-3">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
