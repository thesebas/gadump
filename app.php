<?php
/**
 * Created by IntelliJ IDEA.
 * User: thesebas
 * Date: 2015-11-30
 * Time: 13:55
 */

require_once __DIR__ . '/vendor/autoload.php';

function getService() {
    $service_account_email = '<account>';
    $key_file_location = __DIR__ . '<p12key>';

    $client = new Google_Client();
    $client->setApplicationName("dump_data");
    $analytics = new Google_Service_Analytics($client);

    $key = file_get_contents($key_file_location);
    $cred = new Google_Auth_AssertionCredentials(
        $service_account_email,
        array(Google_Service_Analytics::ANALYTICS_READONLY),
        $key
    );
    $client->setAssertionCredentials($cred);
    if ($client->getAuth()->isAccessTokenExpired()) {
        $client->getAuth()->refreshTokenWithAssertion($cred);
    }

    return $analytics;
}


$service = getService();
$accounts = $service->management_accounts->listManagementAccounts();

$profiles = $service->management_profiles->listManagementProfiles('~all', '~all');

$getData = function ($id, $start, $maxres = 1000, $dateFrom, $dateTo) use ($service) {
    $data = $service->data_ga->get("ga:{$id}", $dateFrom, $dateTo, 'ga:pageviews', [
        'start-index' => $start,
        'max-results' => $maxres,
        'dimensions' => 'ga:pagePath,ga:hostname',
        'sort' => '-ga:pageviews'
    ]);

    $rows = $data->getRows();
    return [$rows, count($rows) >= $maxres];
};
// mock
$_getData = function ($id, $start, $maxres = 1000, $dateFrom, $dateTo) {
    echo "$id, $start, $maxres, $dateFrom, $dateTo", PHP_EOL;
    return [[], false];
};

$excludeIds = [
    6660064,
    105970498,
    12769295,
    102564109,
];
$startDate = strtotime('2015-06-08');
$endDate = strtotime('2015-09-06');

foreach ($profiles as $profile) {
    /** @var $profile Google_Service_Analytics_Profile */

//    echo $profile->getName(), PHP_EOL;

    $perPage = 10000;


    $profileId = $profile->getId();

    if (in_array($profileId, $excludeIds)) {
        continue;
    }

    $subStartDate = $startDate;
    do {
        $subEndDate = strtotime('+1day', $subStartDate);
        $from = 1;
        do {
            list($rows, $hasMore) = $getData($profileId, $from, $perPage, date('Y-m-d', $subStartDate), date('Y-m-d', $subEndDate));
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    list($path, $domain, $pv) = $row;

                    echo $domain, "\t", $path, "\t", $pv, "\t", date('Y-m-d', $subStartDate), PHP_EOL;
                }
            }

            $from += $perPage;
        } while ($hasMore);
        $subStartDate = $subEndDate;
    } while ($subEndDate <= $endDate);
}
