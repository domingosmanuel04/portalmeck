<?php
// api/ice_config.php — STUN/TURN a partir de .env
session_start();
require_once 'db.php';
require_once 'security_helpers.php';

header('Content-Type: application/json; charset=utf-8');
meckRequireLogin(true);

$ice = [
    ['urls' => 'stun:stun.l.google.com:19302'],
    ['urls' => 'stun:stun1.l.google.com:19302'],
];

$turnUrl = getenv('TURN_URL') ?: '';
$turnUser = getenv('TURN_USERNAME') ?: '';
$turnPass = getenv('TURN_PASSWORD') ?: '';

if ($turnUrl !== '') {
    $ice[] = [
        'urls' => $turnUrl,
        'username' => $turnUser,
        'credential' => $turnPass,
    ];
} else {
    // fallback público (desenvolvimento)
    $ice[] = [
        'urls' => 'turn:openrelay.metered.ca:80',
        'username' => 'openrelayproject',
        'credential' => 'openrelayproject',
    ];
    $ice[] = [
        'urls' => 'turn:openrelay.metered.ca:443',
        'username' => 'openrelayproject',
        'credential' => 'openrelayproject',
    ];
}

echo json_encode(['iceServers' => $ice], JSON_UNESCAPED_UNICODE);
