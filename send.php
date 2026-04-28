<?php
header('Content-Type: application/json');

// ── Helpers ──────────────────────────────────────────────────────────────────

function respond(bool $success, ?string $error = null, int $status = 200): void
{
    http_response_code($status);
    $body = ['success' => $success];
    if ($error !== null) {
        $body['error'] = $error;
    }
    echo json_encode($body);
    exit;
}

function logError(string $message): void
{
    error_log('[send.php] ' . $message);
}

// ── Honeypot ─────────────────────────────────────────────────────────────────

if (!empty($_POST['company'])) {
    respond(false, null, 200); // silent reject for bots
}

// ── Server-side validation ────────────────────────────────────────────────────

$name     = trim($_POST['name']     ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$email    = trim($_POST['email']    ?? '');
$phone    = trim($_POST['phone']    ?? '');

$errors = [];

if (mb_strlen($name) < 2) {
    $errors[] = "Введіть ім'я";
}

if (mb_strlen($lastname) < 2) {
    $errors[] = 'Введіть прізвище';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Невірний email';
}

if (!preg_match('/^\+?\d{10,15}$/', preg_replace('/\s+/', '', $phone))) {
    $errors[] = 'Невірний номер телефону';
}

if (!empty($errors)) {
    respond(false, implode('. ', $errors), 400);
}

// ── Credentials from environment variables ────────────────────────────────────

$apiKey     = getenv('TRACKER_API_KEY');
$apiPass    = getenv('TRACKER_API_PASS');
$campaignId = getenv('TRACKER_CAMPAIGN_ID');
$trackerUrl = getenv('TRACKER_URL') ?: 'https://tracker.my-live-tracker.com/api/';

if (!$apiKey || !$apiPass || !$campaignId) {
    logError('Missing required environment variables: TRACKER_API_KEY, TRACKER_API_PASS, TRACKER_CAMPAIGN_ID');
    respond(false, 'Помилка конфігурації сервера.', 500);
}

// ── Post to external tracker API ─────────────────────────────────────────────

$payload = http_build_query([
    'api_key'     => $apiKey,
    'pass'        => $apiPass,
    'campaign_id' => $campaignId,
    'name'        => $name,
    'lastname'    => $lastname,
    'email'       => $email,
    'phone'       => $phone,
    'action'      => 'lead',
]);

$context = stream_context_create([
    'http' => [
        'header'        => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'        => 'POST',
        'content'       => $payload,
        'timeout'       => 10,
        'ignore_errors' => true,
    ],
]);

$result = @file_get_contents($trackerUrl, false, $context);

if ($result === false) {
    logError('External API request failed: ' . $trackerUrl);
    respond(false, 'Помилка сервера. Спробуйте пізніше.', 500);
}

// Log the raw tracker response for debugging
logError('Tracker response: ' . $result);

$decoded = json_decode($result, true);

// Accept the lead if the tracker returned a truthy success field,
// or if it returned any non-error JSON (some trackers return an ID on success).
if (json_last_error() === JSON_ERROR_NONE && !empty($decoded['success'])) {
    respond(true);
}

// Fallback: treat a non-empty response without an explicit error as success
if ($result !== '' && (json_last_error() !== JSON_ERROR_NONE || empty($decoded['error']))) {
    respond(true);
}

logError('Tracker returned an error: ' . $result);
respond(false, 'Не вдалося зареєструватися. Спробуйте пізніше.', 502);
?>
