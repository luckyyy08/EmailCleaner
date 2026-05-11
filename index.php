<?php
require_once 'config.php';
if(isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Cleaner - Smart Inbox Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-body-tertiary">

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="#">
            <i class="bi bi-envelope-check-fill fs-3 me-2"></i>
            CleanBox AI
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#how-it-works">How it Works</a>
                </li>
                <li class="nav-item ms-3">
                    <a href="auth.php" class="btn btn-light rounded-pill px-4 fw-semibold text-primary shadow-sm">
                        <i class="bi bi-google me-2"></i> Sign in with Google
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section text-center py-5">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-extrabold mb-4 text-gradient">Reclaim Your Inbox <br>with Smart Automation</h1>
                <p class="lead text-muted mb-5">Automatically detect, categorize, and bulk-delete unwanted emails, newsletters, and promotions. Keep what matters, trash the rest.</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="auth.php" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow">
                        <i class="bi bi-rocket-takeoff me-2"></i> Get Started for Free
                    </a>
                </div>
                <div class="mt-5">
                    <img src="https://images.unsplash.com/photo-1555421689-491a97ff2040?auto=format&fit=crop&w=1200&q=80" alt="Dashboard Preview" class="img-fluid rounded-4 shadow-lg border border-secondary" style="max-height: 400px; object-fit: cover;">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-5 bg-body">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Why Choose CleanBox AI?</h2>
            <p class="text-muted">Powerful features to keep your inbox pristine.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm feature-card p-4 rounded-4">
                    <div class="feature-icon bg-primary-subtle text-primary rounded-circle mb-4 d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="bi bi-magic fs-3"></i>
                    </div>
                    <h4 class="fw-bold">Smart Categorization</h4>
                    <p class="text-muted">We automatically sort emails into Promotions, Social, Newsletters, and Spam-like categories.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm feature-card p-4 rounded-4">
                    <div class="feature-icon bg-danger-subtle text-danger rounded-circle mb-4 d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="bi bi-trash3-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold">Bulk Cleanup</h4>
                    <p class="text-muted">Delete thousands of useless emails in one click without affecting your important messages.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm feature-card p-4 rounded-4">
                    <div class="feature-icon bg-success-subtle text-success rounded-circle mb-4 d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="bi bi-shield-check fs-3"></i>
                    </div>
                    <h4 class="fw-bold">Safe & Secure</h4>
                    <p class="text-muted">We only move emails to trash. Starred and important emails are never touched.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-white py-4 mt-auto">
    <div class="container text-center">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> CleanBox AI. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
