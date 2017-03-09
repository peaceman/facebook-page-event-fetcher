<?php
	// define the path and name of cached file
	$cachefile = 'cached-files/'.date('M-d-Y').'.php';
	// define how long we want to keep the file in seconds. I set mine to 5 hours.
	$cachetime = 18000;
	// Check if the cached file is still fresh. If it is, serve it up and exit.
	if (file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
   	include($cachefile);
    	exit;
	}

require_once __DIR__ . '/../vendor/autoload.php';

$dotEnv = new \Dotenv\Dotenv(__DIR__ . '/..');
$dotEnv->load();

$fb = new Facebook\Facebook([
    'default_access_token' => sprintf('%s|%s', getenv('FACEBOOK_APP_ID'), getenv('FACEBOOK_APP_SECRET')),
]);

$pageIds = getenv('PAGE_IDS');

$response = $fb->sendRequest('GET', '/events', [
    'ids' => $pageIds,
    'fields' => 'id,cover,name,description,place,ticket_uri,start_time'
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
			'title' => $startDateTime->format('d.m.Y') . ' - ' . $event['name'],
			'description' => substr($event['description'], 0, strpos(wordwrap($event['description'], 250), "\n")),
			'content' => 'https://images1-focus-opensocial.googleusercontent.com/gadgets/proxy?url=' . $event['cover']['source'] . '&container=focus&resize_w=720&resize_h=212&refresh=2592000',
			'image' => $event['cover']['source'],
			'link' => 'http://' . parse_url($event['ticket_uri'], PHP_URL_HOST),
			'pubDate' => 'am ' . $startDateTime->format('d.m.Y') . ' um ' . $startDateTime->format('H:i') . ' @ ' .  $event['place']['name'],
			'guid' => $event['id'],
		];
        
		return $toReturn;
    }, array_filter($events['data'], $filterEvents));
}, $eventsPerPage);

$data = array_reduce($data, function ($result, $eventsPerPage){
	return array_merge($result, $eventsPerPage);
}, []);

header('Content-Type: application/json');



	// if there is either no file OR the file to too old, render the page and capture the HTML.
	ob_start();


echo json_encode($data);

	// We're done! Save the cached content to a file
	$fp = fopen($cachefile, 'w');
	fwrite($fp, ob_get_contents());
	fclose($fp);
	// finally send browser output
	ob_end_flush();



