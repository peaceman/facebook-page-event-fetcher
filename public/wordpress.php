<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotEnv = new \Dotenv\Dotenv(__DIR__ . '/..');
$dotEnv->load();

$fb = new Facebook\Facebook([
    'default_access_token' => sprintf('%s|%s', getenv('FACEBOOK_APP_ID'), getenv('FACEBOOK_APP_SECRET')),
]);

$pageIds = getenv('PAGE_IDS');

$response = $fb->sendRequest('GET', '/events', [
    'ids' => $pageIds,
    'fields' => 'id,cover,name,description,place,ticket_uri,start_time,end_time'
]);
$data = $response->getDecodedBody();

$filterEvents = function ($event) {
    $startTime = DateTime::createFromFormat(DateTime::ISO8601, $event['start_time']);
    return $startTime > (new DateTime());
};

$pageIds = array_keys($data);
$eventsPerPage = array_values($data);

$data = array_combine($pageIds, array_map(function ($events, $pageId) use ($filterEvents) {
    return array_map(function ($event) use ($pageId) {
        $event['pageId'] = $pageId;

        $startDateTime = DateTime::createFromFormat(DateTime::ISO8601, $event['start_time']);
        $endDateTime = DateTime::createFromFormat(DateTime::ISO8601, $event['end_time']);
        unset($event['start_time'], $event['end_time']);

        $event['start'] = createDateTimeInfo($startDateTime);
        $event['end'] = createDateTimeInfo($endDateTime);

        return $event;
    }, array_filter($events['data'], $filterEvents));
}, $eventsPerPage, $pageIds));

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
echo json_encode(['root' => $data]);
