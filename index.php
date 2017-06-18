<?php
require_once 'vendor/autoload.php';

$config = require_once 'config.php';

$client = new \Github\Client();
$client->authenticate($config['access_token'], '', Github\Client::AUTH_HTTP_TOKEN);
$lgtmObj = new \LGTMBot\LGTM($client, $config);
$lgtmObj->retrievePullRequests()
    ->filterWorkInProgressPr()
    ->filterDoNotMergePr()
    ->filterUserPr()
    ->filterApprovedPr()
    ->approveAll();
