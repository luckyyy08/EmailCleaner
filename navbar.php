<!-- navbar.php -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="dashboard.php">
            <i class="bi bi-envelope-check-fill fs-4 me-2 text-primary"></i>
            CleanBox AI
        </a>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-light btn-sm" id="theme-toggle">
                <i class="bi bi-sun-fill"></i>
            </button>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="<?php echo htmlspecialchars($user['picture']); ?>" alt="" width="32" height="32" class="rounded-circle me-2">
                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
