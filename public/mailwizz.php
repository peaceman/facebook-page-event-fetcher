<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotEnv = new \Dotenv\Dotenv(__DIR__ . '/..');
$dotEnv->load();

$fb = new Facebook\Facebook([
    'default_access_token' => sprintf('%s|%s', getenv('FACEBOOK_APP_ID'), getenv('FACEBOOK_APP_SECRET')),
]);

$pageIds = getenv('PAGE_IDS');

// define the path and name of cached file
$cacheFile =  __DIR__ . '/../storage/events.json';
// define how long we want to keep the file in seconds. I set mine to 5 hours.
$cacheTime = 5 * 60 * 60;
// Check if the cached file is still fresh. If it is, serve it up and exit.
if (isset($_GET['force-refresh']) || !file_exists($cacheFile) || time() - $cacheTime < filemtime($cacheFile)) {
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
                'content' => 'https://images1-focus-opensocial.googleusercontent.com/gadgets/proxy?url=' . urlencode($event['cover']['source']) . '&container=focus&resize_w=720&resize_h=212&refresh=2592000',
                'image' => $event['cover']['source'],
                'link' => 'http://' . parse_url($event['ticket_uri'], PHP_URL_HOST),
                'pubDate' => 'am ' . $startDateTime->format('d.m.Y') . ' um ' . $startDateTime->format('H:i') . ' @ ' . $event['place']['name'],
                'guid' => $event['id'],
            ];

            return $toReturn;
        }, array_filter($events['data'], $filterEvents));
    }, $eventsPerPage);

    $data = array_reduce($data, function ($result, $eventsPerPage) {
        return array_merge($result, $eventsPerPage);
    }, []);

    $jsonData = json_encode($data, JSON_PRETTY_PRINT);
    file_put_contents($cacheFile, $jsonData, LOCK_EX);
} else {
    $jsonData = file_get_contents($cacheFile, LOCK_SH);
}

header('Content-Type: application/json');
echo $jsonData;

