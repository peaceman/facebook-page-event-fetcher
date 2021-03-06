<?php
setlocale(LC_ALL, 'de_DE.utf8');
require_once __DIR__ . '/../vendor/autoload.php';

$dotEnv = new \Dotenv\Dotenv(__DIR__ . '/..', 'env_u18');
$dotEnv->load();

$fb = new Facebook\Facebook([
    'default_access_token' => sprintf('%s|%s', getenv('FACEBOOK_APP_ID'), getenv('FACEBOOK_APP_SECRET')),
]);

$pageIds = getenv('PAGE_IDS');

// define the path and name of cached file
$cacheFile = __DIR__ . '/../storage/' . basename(__FILE__, '.php') . 'events.json';
// define how long we want to keep the file in seconds. I set mine to 5 hours. * 60 * 60
$cacheTime = 5 * 60 * 60;
// Check if the cached file is still fresh. If it is, serve it up and exit.
if (isset($_GET['force-refresh']) || !file_exists($cacheFile) || time() - $cacheTime > filemtime($cacheFile)) {
    $response = $fb->sendRequest('GET', '/events', [
        'ids' => $pageIds,
        'fields' => 'id,cover,name,description,place,ticket_uri,start_time,end_time'
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
            $endDateTime = DateTime::createFromFormat(DateTime::ISO8601, $event['end_time']);

            $interval = new DateInterval('P1D');
            $dateRange = new DatePeriod($startDateTime, $interval, $endDateTime);

            $downloadLinks = [];
            foreach ($dateRange as $date) {
                $dmy = strftime("%d.%m.%Y", $date->getTimestamp());
                $queryParams = [
                    'date' => strftime("%A, %d. %B %Y", $date->getTimestamp()),
                    'event' => $event['name'],
                    'filename' => $dmy,
                ];

                $downloadLinks[] = ['date' => $dmy, 'url' => getenv('DOWNLOAD_BASE_URL') . '/?' . http_build_query($queryParams)];
            }

            $toReturn = [
                'name' => $event['name'],
                'downloadLinks' => $downloadLinks,
                'date' => strftime("%A, %d. %B %Y", $startDateTime->getTimestamp()) . ' | ' . $startDateTime->format('H:i') . ' | ' . $event['place']['name'],
                'guid' => $event['id'],
                'startDateTime' => $startDateTime,
            ];

            return $toReturn;
        }, array_filter($events['data'], $filterEvents));
    }, $eventsPerPage);

    $data = array_reduce($data, function ($result, $eventsPerPage) {
        return array_merge($result, $eventsPerPage);
    }, []);

    usort($data, function ($x, $y) {
        return $x['startDateTime'] <=> $y['startDateTime'];
    });

    $data = array_map(function ($event) {
        unset($event['startDateTime']);
        return $event;
    }, $data);

    $jsonData = json_encode($data, JSON_PRETTY_PRINT);
    file_put_contents($cacheFile, $jsonData, LOCK_EX);
} else {
    $jsonData = file_get_contents($cacheFile, LOCK_SH);
}

header('Content-Type: application/json');
echo $jsonData;

