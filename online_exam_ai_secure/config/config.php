<?php

declare(strict_types=1);

const APP_NAME = 'AI Online Examination System';
const BASE_URL = '/online_exam_ai_secure';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'online_exam';
const DB_USER = 'root';
const DB_PASS = '';
const AI_API_URL = 'http://127.0.0.1:5000';
const AI_API_TOKEN = 'CHANGE_THIS_RANDOM_LONG_TOKEN';

date_default_timezone_set('Asia/Kolkata');

// Session Security Configuration
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

// Enable secure cookies dynamically if running over HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', '1');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';