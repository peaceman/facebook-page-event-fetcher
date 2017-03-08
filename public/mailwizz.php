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
    'fields' => 'id,cover,name,description,place,start_time'
]);
$data = $response->getDecodedBody();

$filterEvents = function ($event) {
    $startTime = DateTime::createFromFormat(DateTime::ISO8601, $event['start_time']);
    return $startTime > (new DateTime());
};

$eventsPerPage = array_values($data);

$data = array_map(function ($events) use ($filterEvents) {
    return array_map(function ($event) {
		
		$startDateTime = DateTime::createFromFormat(DateTime::ISO8601, $event['start_time']);
        unset($event['start_time']);
		
		$toReturn = [
			'title' => $event['name'],
			'description' => $event['description'],
			'content' => $event['description'],
			'image' => $event['cover']['source'],
			'link' => $event['description'],
			'pubDate' => $startDateTime->format('d.m.Y H:i'),
			'guid' => $event['id'],
		];
        
		return $toReturn;
    }, array_filter($events['data'], $filterEvents));
}, $eventsPerPage);

$data = array_reduce($data, function ($result, $eventsPerPage){
	return array_merge($result, $eventsPerPage);
}, []);

header('Content-Type: application/json');
echo json_encode($data);
