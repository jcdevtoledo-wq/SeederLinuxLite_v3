<?php
/**
 * Session Debug Tool
 * Use this to test if PHP sessions are working correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$result = [
    'session_id' => session_id(),
    'session_status' => session_status() === PHP_ACTIVE_SESSION ? 'active' : 'none',
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'server' => [
        'HTTPS' => isset($_SERVER['HTTPS']) ? 'on' : 'off',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'none',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'none',
        'PHP_VERSION' => PHP_VERSION,
    ],
    'session_config' => [
        'save_path' => session_save_path(),
        'cookie_params' => session_get_cookie_params(),
    ]
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
