<?php
/**
 * This file is part of the Laravel social auto posting package.
 *
 * Copyright (c) 2016 Ali Hesari <alihesari.com@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Homepage:    https://github.com/alihesari/larasap
 * Version:     1.0
 */

namespace Toolkito\Larasap;

use Illuminate\Support\Facades\Config;
use Toolkito\Larasap\Facebook\Api as FacebookApi;
use Toolkito\Larasap\Telegram\Api as TelegramApi;
use Toolkito\Larasap\Twitter\Api as TwitterApi;

class SendTo
{
    /**
     * Sent to Telegram
     *
     * @param $text
     * @param string $attachment
     * @param string $inline_keyboard
     * @return bool|mixed
     */

    public $config = [];

    public function __construct($config = [])
    {
        $this->config['api_token'] = (isset($config['api_token'])) ? $config['api_token'] : '';
        $this->config['bot_username'] = (isset($config['bot_username'])) ? $config['bot_username'] : '';
        $this->config['channel_username'] = (isset($config['channel_username'])) ? $config['channel_username'] : '';
        $this->config['proxy'] = (isset($config['proxy'])) ? $config['proxy'] : '';

        $this->config['app_id'] = (isset($config['app_id'])) ? $config['app_id'] : '';
        $this->config['app_secret'] = (isset($config['app_secret'])) ? $config['app_secret'] : '';
        $this->config['default_graph_version'] = (isset($config['default_graph_version'])) ? $config['default_graph_version'] : '';
        $this->config['page_access_token'] = (isset($config['page_access_token'])) ? $config['page_access_token'] : '';

        $this->config['consumerKey'] = (isset($config['consumerKey'])) ? $config['consumerKey'] : '';
        $this->config['consumerSecret'] = (isset($config['consumerSecret'])) ? $config['consumerSecret'] : '';
        $this->config['accessToken'] = (isset($config['accessToken'])) ? $config['accessToken'] : '';
        $this->config['accessTokenSecret'] = (isset($config['accessTokenSecret'])) ? $config['accessTokenSecret'] : '';
    }

    public function telegram($text, $attachment = '', $inline_keyboard = '')
    {
        $telegram_api = new TelegramApi($this->config['api_token'], $this->config['bot_username'], $this->config['channel_username'], $this->config['proxy']);

        if (Config::get('larasap.telegram.channel_signature')) {
            $type = isset($attachment['type']) ? 'caption' : 'text';
            $text = $this->assignSignature($text, $type);
        }

        if ($attachment) {
            switch ($attachment['type']) {
                case 'photo':
                    $result = $telegram_api->sendPhoto(null, $attachment['file'], $text, $inline_keyboard);
                    break;
                case 'audio':
                    $duration = isset($attachment['duration']) ? $attachment['duration'] : '';
                    $performer = isset($attachment['performer']) ? $attachment['performer'] : '';
                    $title = isset($attachment['title']) ? $attachment['title'] : '';
                    $result = $telegram_api->sendAudio(null, $attachment['file'], $text, $duration, $performer, $title, $inline_keyboard);
                    break;
                case 'document':
                    $result = $telegram_api->sendDocument(null, $attachment['file'], $text, $inline_keyboard);
                    break;
                case 'video':
                    $duration = isset($attachment['duration']) ? $attachment['duration'] : '';
                    $width = isset($attachment['width']) ? $attachment['width'] : '';
                    $height = isset($attachment['height']) ? $attachment['height'] : '';
                    $result = $telegram_api->sendVideo(null, $attachment['file'], $duration, $width, $height, $text, $inline_keyboard);
                    break;
                case 'voice':
                    $duration = isset($attachment['duration']) ? $attachment['duration'] : '';
                    $result = $telegram_api->sendVoice(null, $attachment['file'], $text, $duration, $inline_keyboard);
                    break;
                case 'media_group':
                    $result = $telegram_api->sendMediaGroup(null, json_encode($attachment['files']));
                    break;
                case 'location':
                    $live_period = isset($attachment['live_period']) ? $attachment['live_period'] : '';
                    $result = $telegram_api->sendLocation(null, $attachment['latitude'], $attachment['longitude'], $live_period, $inline_keyboard);
                    break;
                case 'venue':
                    $foursquare_id = isset($attachment['foursquare_id']) ? $attachment['foursquare_id'] : '';
                    $result = $telegram_api->sendVenue(null, $attachment['latitude'], $attachment['longitude'], $attachment['title'], $attachment['address'], $foursquare_id, $inline_keyboard);
                    break;
                case 'contact':
                    $last_name = isset($attachment['last_name']) ? $attachment['last_name'] : '';
                    $result = $telegram_api->sendContact(null, $attachment['phone_number'], $attachment['first_name'], $last_name, $inline_keyboard);
                    break;
            }
        } else {
            $result = $telegram_api->sendMessage(null, $text, $inline_keyboard);
        }

        return $result;
    }

    /**
     * Send message to Twitter
     *
     * @param $message
     * @param null $media
     * @param array $options
     * @return Twitter\stdClass
     */
    public function twitter($message, $media = [], $options = [])
    {
        $twitter_api = new TwitterApi($this->config['consumerKey'], $this->config['consumerSecret'], $this->config['accessToken'], $this->config['accessTokenSecret']);

        return TwitterApi::sendMessage($message, $media, $options);
    }

    /**
     * Send message to Facebook page
     *
     * @param $type
     * @param $data
     * @return bool
     */
    public function facebook($type, $data)
    {
        $facebook_api = new FacebookApi($this->config['app_id'], $this->config['app_secret'], $this->config['default_graph_version'], $this->config['page_access_token']);

        switch ($type) {
            case 'link':
                $message = isset($data['message']) ? $data['message'] : '';
                $result = $facebook_api->sendLink($data['link'], $data['message']);
                break;
            case 'photo':
                $message = isset($data['message']) ? $data['message'] : '';
                $result = $facebook_api->sendPhoto($data['photo'], $message);
                break;
            case 'photos':
                $message = isset($data['message']) ? $data['message'] : '';
                $photo_messages = isset($data['photo_messages']) ? $data['photo_messages'] : '';
                $result = $facebook_api->sendPhoto($data['photos'], $message, $photo_messages);
                break;
            case 'video':
                $description = isset($data['description']) ? $data['description'] : '';
                $result = $facebook_api->sendVideo($data['video'], $data['title'], $description);
                break;
        }

        return ($result > 0) ? true : false;
    }

    /**
     * Assign channel signature in the footer of message
     *
     * @param $text
     * @param $text_type
     */
    public function assignSignature($text, $type)
    {
        $telegram_api = new TelegramApi($this->config['api_token'], $this->config['bot_username'], $this->config['channel_username'], $this->config['proxy']);

        $signature = "\n" . Config::get('larasap.telegram.channel_signature');
        $signature_length = strlen($signature);
        $text_length = strlen($text);
        $max_length = ($type == 'text') ? $telegram_api->TEXT_LENGTH : $telegram_api->CAPTION_LENGTH;
        if ($signature_length + $text_length <= $max_length || $signature_length > $text_length) {
            return $text . $signature;
        }

        return substr($text, 0, $max_length - $signature_length) . $signature;
    }
}
