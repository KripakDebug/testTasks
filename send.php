<?php
header('Content-Type: application/json');

if (!empty($_POST['company'])) {
    echo json_encode(['success' => false]);
    exit;
}

$name = $_POST['name'] ?? '';
$lastname = $_POST['lastname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';

if (!$name || !$lastname || !$email || !$phone) {
    echo json_encode(['success' => false]);
    exit;
}

$url = "https://tracker.my-live-tracker.com/api/";

$data = [
    'api_key' => 'TVRjNU56aGZOelkyWHpFM09UYzRYdz09',
    'pass' => 'DVc4pw2xlm',
    'campaign_id' => '22909',
    'name' => $name,
    'lastname' => $lastname,
    'email' => $email,
    'phone' => $phone,
    'action' => 'lead' 
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
    ],
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    echo json_encode(['success' => false]);
} else {
    echo json_encode(['success' => true]);
}
?>
