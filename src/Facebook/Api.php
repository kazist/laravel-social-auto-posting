<?php

namespace Toolkito\Larasap\Facebook;

use Facebook;
use Illuminate\Support\Facades\Config;

class Api
{
    /**
     * App ID
     *
     * @var integer
     */
    public $app_id;

    /**
     * App Secret
     *
     * @var string
     */
    public $app_secret;

    /**
     * API Version
     *
     * @var string
     */
    public $default_graph_version;

    /**
     * Page access Token
     *
     * @var string
     */
    public $page_access_token;

    public $fb;

    public function __construct($app_id = "", $app_secret = "", $default_graph_version = "", $page_access_token = "")
    {
        $this->app_id = ($app_id !== '') ? $app_id : Config::get('larasap.facebook.app_id');
        $this->app_secret = ($app_secret !== '') ? $app_secret : Config::get('larasap.facebook.app_secret');
        $this->default_graph_version = ($default_graph_version !== '') ? $default_graph_version : Config::get('larasap.facebook.default_graph_version');
        $this->page_access_token = ($page_access_token !== '') ? $page_access_token : Config::get('larasap.facebook.page_access_token');

        $this->fb = new \Facebook\Facebook([
            'app_id' => $this->app_id,
            'app_secret' => $this->app_secret,
            'default_graph_version' => $this->default_graph_version,
        ]);
    }

    /**
     * Send link and text message
     *
     * @param $link
     * @param $message
     * @return bool
     * @throws \Exception
     */
    public function sendLink($link, $message = '')
    {

        $data = compact('link', 'message');
        try {
            $response = $this->fb->post('/me/feed', $data, $this->page_access_token);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            throw new \Exception('Graph returned an error: ' . $e->getMessage());
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            throw new \Exception('Facebook SDK returned an error: ' . $e->getMessage());
        }
        $graphNode = $response->getGraphNode();

        return $graphNode['id'];
    }

    /**
     * Send photo and text message
     *
     * @param $photo
     * @param string $message
     * @return bool
     * @throws \Exception
     */
    public function sendPhoto($photo, $message = '', $published = true)
    {

        $data = [
            'message' => $message,
            'source' => $this->fb->fileToUpload($photo),
            "published" => $published,
        ];
        try {
            $response = $this->fb->post('/me/photos', $data, $this->page_access_token);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            throw new \Exception('Graph returned an error: ' . $e->getMessage());
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            throw new \Exception('Facebook SDK returned an error: ' . $e->getMessage());
        }
        $graphNode = $response->getGraphNode();

        return $graphNode['id'];
    }

    public function sendPhotos($photos, $message = '', $photo_messages = array())
    {

        $photo_ids = [];
        $data = array("message" => $message);

        foreach ($photos as $key => $photo) {

            $tmp_message = '';

            if (is_array($photo_messages) && isset($photo_messages[$key])) {
                $tmp_message = $photo_messages[$key];
            }

            $photo_ids[] = $this->sendPhoto($photo, $tmp_message, false);
        }

        foreach ($photo_ids as $k => $photo_id) {
            $data["attached_media"][$k] = '{"media_fbid":"' . $photo_id . '"}';
        }

        try {
            $response = $this->fb->post("/me/feed", $data, $this->page_access_token);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            throw new \Exception('Graph returned an error: ' . $e->getMessage());
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            throw new \Exception('Facebook SDK returned an error: ' . $e->getMessage());
        }

        $graphNode = $response->getGraphNode();

        return $graphNode['id'];
    }

    /**
     * Send video
     *
     * @param $video
     * @param string $title
     * @param string $description
     * @return mixed
     * @throws \Exception
     */
    public function sendVideo($video, $title = '', $description = '')
    {

        $data = compact('title', 'description');
        try {
            $response = $this->fb->uploadVideo('me', $video, $data, $this->page_access_token);
        } catch (Facebook\Exceptions\FacebookResponseException $e) {
            throw new \Exception('Graph returned an error: ' . $e->getMessage());
        } catch (Facebook\Exceptions\FacebookSDKException $e) {
            throw new \Exception('Facebook SDK returned an error: ' . $e->getMessage());
        }
        $graphNode = $response->getGraphNode();

        return $graphNode['id'];
    }
}
