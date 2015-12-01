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

$getData = function ($id, $start, $maxres = 1000) use ($service) {
    $data = $service->data_ga->get("ga:{$id}", '2015-06-08', '2015-09-06', 'ga:pageviews', [
        'start-index' => $start,
        'max-results' => $maxres,
        'dimensions' => 'ga:pagePath',
        'sort' => '-ga:pageviews'
    ]);

    $rows = $data->getRows();
    return [$rows, count($rows) >= $maxres];
};

foreach ($profiles as $profile) {
    /** @var $profile Google_Service_Analytics_Profile */

    echo $profile->getName(), PHP_EOL;

    $perPage = 1000;
    $from = 1;

    do {
        list($rows, $hasMore) = $getData($profile->getId(), $from, $perPage);
        foreach ($rows as $row) {
            list($path, $pv) = $row;

            echo "\t", $path, "\t", $pv, PHP_EOL;
        }

        $from += $perPage;
    } while ($hasMore);
}
