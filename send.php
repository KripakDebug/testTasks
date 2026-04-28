<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendLead(string $url, array $data): bool
{
    $body = http_build_query($data);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
        ]);

        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hasError = curl_errno($ch) !== 0;
        curl_close($ch);

        return !$hasError && $result !== false && $httpCode >= 200 && $httpCode < 300;
    }

    $context = stream_context_create([
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => $body,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        return false;
    }

    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    $httpCode = isset($matches[1]) ? (int) $matches[1] : 0;

    return $httpCode >= 200 && $httpCode < 300;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

if (!empty($_POST['company'])) {
    respond(200, ['success' => false, 'message' => 'Spam detected']);
}

$name = trim((string) ($_POST['name'] ?? ''));
$lastname = trim((string) ($_POST['lastname'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));

if ($name === '' || $lastname === '' || $email === '' || $phone === '') {
    respond(422, ['success' => false, 'message' => 'Missing required fields']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['success' => false, 'message' => 'Invalid email']);
}

$url = 'https://tracker.my-live-tracker.com/api/';

$data = [
    'api_key' => 'TVRjNU56aGZOelkyWHpFM09UYzRYdz09',
    'pass' => 'DVc4pw2xlm',
    'campaign_id' => '22909',
    'name' => $name,
    'lastname' => $lastname,
    'email' => $email,
    'phone' => $phone,
    'action' => 'lead',
];

if (!sendLead($url, $data)) {
    respond(502, ['success' => false, 'message' => 'Lead provider request failed']);
}

respond(200, ['success' => true]);
