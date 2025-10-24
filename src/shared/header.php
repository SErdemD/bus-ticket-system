<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yavuzlar Bus Ticket</title>
    <!-- Link to Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"/>
    <link href="/css/custom.css" rel="stylesheet"> <!-- this didnt work, check later -->
</head>
<body>
    <?php
    $is_logged_in = is_logged_in();
    $role = $is_logged_in ? ($_SESSION['user_role'] ?? 'user') : null;
    $username = $is_logged_in ? htmlspecialchars($_SESSION['user_name']) : null;
    $balance = $is_logged_in ? htmlspecialchars($_SESSION['user_balance']) : null;
    ?>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="/home">
                <b>Yavuzlar Bus Ticket</b>
                <?php if ($role === 'admin' || $role === 'company'): ?>
                    <span class="mt-0 ms-2 badge bg-primary text-light" style="font-size: 0.8rem;">
                        <?= $role === 'company' ? 'Company Admin' : 'Admin' ?>
                    </span>
                <?php endif; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarNav" aria-controls="navbarNav"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">

                    <?php if (!$is_logged_in || $role === 'user'): ?>
                        <li class="nav-item"><a class="nav-link" href="/home">Trips</a></li>
                    <?php endif; ?>

                    <?php if ($is_logged_in && $role === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                    <?php endif; ?>
                    
                    <?php if ($is_logged_in && $role === 'company'): ?>
                        <li class="nav-item"><a class="nav-link" href="/company/dashboard"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                    <?php endif; ?>

                    <?php if ($is_logged_in): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown"
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Hello, <?= $username ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                                <?php if ($role === 'user'): ?>
                                    <li><a class="dropdown-item" href="/my-tickets">My Tickets</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>

                                <?php if ($role !== 'admin'): ?>
                                    <li><a class="dropdown-item" href="/profile">Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>

                                <li><a class="dropdown-item" href="/logout">Logout</a></li>
                            </ul>
                        </li>

                        <?php if ($role === 'user'): ?>
                            <li class="nav-item">
                                <a class="nav-link">Credit: <?= $balance ?>$</a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-nav-custom btn-primary ms-lg-2" href="/login">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-nav-custom btn-primary ms-lg-2" href="/signup">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>


    <style>

        .navbar-brand .badge {
        background-color: #3b82f6 !important;
        font-weight: 500;
        text-transform: capitalize;
        }
    </style>
    <!-- Main content container. -->
    <main class="container mt-4">



