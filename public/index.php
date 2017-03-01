<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotEnv = new \Dotenv\Dotenv(__DIR__ . '/..');
$dotEnv->load();

$fb = new Facebook\Facebook([
    'default_access_token' => sprintf('%s|%s', getenv('FACEBOOK_APP_ID'), getenv('FACEBOOK_APP_SECRET')),
]);

$pageIds = getenv('PAGE_IDS');

$response = $fb->sendRequest('GET', '/events', ['ids' => $pageIds, 'fields' => 'cover,name,description,place,ticket_uri,start_time,end_time']);
$data = $response->getDecodedBody();

$filterEvents = function ($event) {
    $startTime = DateTime::createFromFormat(DateTime::ISO8601, $event['start_time']);
    return $startTime > (new DateTime());
};

$data = array_map(function ($events) use ($filterEvents) {
    return array_filter($events['data'], $filterEvents);
}, $data);

header('Content-Type: application/json');
echo json_encode(['root' => $data]);
