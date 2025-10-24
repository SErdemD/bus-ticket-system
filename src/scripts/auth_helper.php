<?php

//Check if user is logged in
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

//if that page require login use that function on top of it
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /login');
        exit();
    }
}

//check if user is admin
function is_admin(): bool
{
    return is_logged_in() && $_SESSION['user_role'] === 'admin';
}

//check if user is company admin
function is_company(): bool
{
    return is_logged_in() && $_SESSION['user_role'] === 'company';
}

//use this on admin pages
function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(404);
        require __DIR__ . '/../pages/404.php';
        exit();
    }
}

//use this on company admin pages
function require_company(): void
{
    if (!is_admin()) {
        http_response_code(404);
        require __DIR__ . '/../pages/404.php';
        exit();
    }
}