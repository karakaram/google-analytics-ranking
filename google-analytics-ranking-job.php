<?php

/*
 * This file is part of the Google Analytics Ranking
 */

if (php_sapi_name() != 'cli') {
    exit;
}

require_once dirname(__FILE__) . '/../../../wp-load.php';

$googleAnalyticsRanking = GoogleAnalyticsRanking::getInstance();
$googleAnalyticsRanking->requestGoogleAnalytics();