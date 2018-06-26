<?php
/*
Plugin Name: Google Analytics Ranking
Description: This plugin to get the ranking from google analytics api 3.0
Version: 0.1
Author: Karakaram
Author URI: http://www.karakaram.com
License: GPL2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

require_once dirname(__FILE__) . '/lib/google-api-php-client/src/Google_Client.php';
require_once dirname(__FILE__) . '/lib/google-api-php-client/src/contrib/Google_AnalyticsService.php';
require_once dirname(__FILE__) . '/parameter.php';

class GoogleAnalyticsRanking
{
    const DB_VERSION = '1';
    const DB_VERSION_OPTION = 'google-analytics-ranking-db-version';
    const POST_META_1 = 'google-analytics-ranking-1';

    /**
     * @var GoogleAnalyticsRanking
     */
    private static $instance = null;

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('save_post', array($this, 'savePost'));
        add_action('delete_post', array($this, 'deletePost'));
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new GoogleAnalyticsRanking();
        }
        return self::$instance;
    }

    public function activate()
    {
        /** @var WP_Post */
        global $post;

        if (self::DB_VERSION == get_option(self::DB_VERSION_OPTION)) {
            return;
        }

        $query = new WP_Query();
        $query->query(array('post_type' => 'post', 'posts_per_page' => -1, 'order' => 'ASC'));

        while ($query->have_posts()) {
            $query->the_post();
            add_post_meta(
                $post->ID,
                self::POST_META_1,
                '0',
                true
            );
        }
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);

    }

    public function deactivate()
    {
        /** @var WP_Post $post */
        global $post;

        $query = new WP_Query();
        $query->query(array('post_type' => 'post', 'posts_per_page' => -1, 'order' => 'ASC'));

        while ($query->have_posts()) {
            $query->the_post();
            delete_post_meta($post->ID, self::POST_META_1);
        }
        delete_option(self::DB_VERSION_OPTION);
    }

    public function savePost($postId)
    {
        add_post_meta($postId, self::POST_META_1, '0', true);
    }

    public function deletePost($postId)
    {
        delete_post_meta($postId, self::POST_META_1);
    }

    public function requestGoogleAnalytics()
    {
        /** @var WP_Post */
        global $post;

        $query = new WP_Query();
        $query->query(array('post_type' => 'post', 'posts_per_page' => -1, 'order' => 'ASC'));
        while ($query->have_posts()) {
            $query->the_post();
            update_post_meta($post->ID, self::POST_META_1, '0');
        }

        $client = new Google_Client();
        $client->setApplicationName("Google Analytics PHP Application");
        $client->setClientId(CLIENT_ID);
        $client->setAssertionCredentials(new Google_AssertionCredentials(
            SERVICE_ACCOUNT_NAME,
            array('https://www.googleapis.com/auth/analytics'),
            file_get_contents(KEY_FILE)
        ));

        $yesterday = date_i18n('Y-m-d', strtotime('-1 day', current_time('timestamp')));

        $service = new Google_AnalyticsService($client);
        $result = $service->data_ga->get(
            'ga:' . PROFILE_ID,
            $yesterday,
            $yesterday,
            'ga:pageviews',
            array(
                'dimensions'  => 'ga:pagePath',
            )
        );

        foreach ($result['rows'] as $rows) {
            $url = $rows[0];
            $postId = url_to_postid($url);
            if ($postId === 0) {
                continue;
            }

            $pageView = $rows[1];
            update_post_meta($postId, self::POST_META_1, $pageView);
        }
    }
}
$googleAnalyticsRanking = GoogleAnalyticsRanking::getInstance();
