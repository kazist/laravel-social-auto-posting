<?php
/**
 * This is a clone of the Twitter for PHP - library for sending messages to Twitter and receiving status updates.
 *
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 * This software is licensed under the New BSD License.
 *
 * Homepage:    https://phpfashion.com/twitter-for-php
 * Github: https://github.com/dg/twitter-php
 * Twitter API: https://dev.twitter.com/rest/public
 * Version:     3.6
 */

namespace Toolkito\Larasap\Twitter;

require_once __DIR__ . '/OAuth.php';
use Illuminate\Support\Facades\Config;

/**
 * Twitter API.
 */
class Api
{
    public $API_URL = 'https://api.twitter.com/1.1/';

    /** @var array */
    public $httpOptions = [
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTPHEADER => ['Expect:'],
        CURLOPT_USERAGENT => 'Twitter for PHP',
    ];

    /** @var Twitter_OAuthConsumer */
    public $consumer;

    /** @var Twitter_OAuthConsumer */
    public $token;

    public $consumerKey;
    public $consumerSecret;
    public $accessToken = null;
    public $accessTokenSecret = null;

    /**
     * Initialize
     */
    public function __construct($consumerKey = '', $consumerSecret = '', $accessToken = '', $accessTokenSecret = '')
    {
        if (!extension_loaded('curl')) {
            throw new TwitterException('PHP extension CURL is not loaded.');
        }

        $this->consumerKey = ($consumerKey !== '') ? $consumerKey : Config::get('larasap.twitter.consurmer_key');
        $this->consumerSecret = ($consumerSecret !== '') ? $consumerSecret : Config::get('larasap.twitter.consurmer_secret');
        $this->accessToken = ($accessToken !== '') ? $accessToken : Config::get('larasap.twitter.access_token');
        $this->accessTokenSecret = ($accessTokenSecret !== '') ? $accessTokenSecret : Config::get('larasap.twitter.access_token_secret');

        $this->consumer = new Twitter_OAuthConsumer($this->consumerKey, $this->consumerSecret);
        $this->token = new Twitter_OAuthConsumer($this->accessToken, $this->accessTokenSecret);
    }

    /**
     * Sends message to the Twitter.
     * @param  string   message encoded in UTF-8
     * @param  string  path to local media file to be uploaded
     * @param  array  additional options to send to statuses/update
     * @return stdClass  see https://dev.twitter.com/rest/reference/post/statuses/update
     * @throws TwitterException
     */
    public function sendMessage($message, $media = [], $options = [])
    {

        $mediaIds = [];
        foreach ($media as $item) {
            $res = $this->request(
                'https://upload.twitter.com/1.1/media/upload.json',
                'POST',
                null,
                ['media' => $item]
            );
            $mediaIds[] = $res->media_id_string;
        }
        return $this->request(
            'statuses/update',
            'POST',
            $options + ['status' => $message, 'media_ids' => implode(',', $mediaIds) ?: null]
        );
    }

    /**
     * Process HTTP request.
     * @param  string  URL or twitter command
     * @param  string  HTTP method GET or POST
     * @param  array   data
     * @param  array   uploaded files
     * @return stdClass|stdClass[]
     * @throws TwitterException
     */
    public function request($resource, $method, array $data = null, array $files = null)
    {
        if (!strpos($resource, '://')) {
            if (!strpos($resource, '.')) {
                $resource .= '.json';
            }
            $resource = $this->API_URL . $resource;
        }

        $hasCURLFile = class_exists('CURLFile', false) && defined('CURLOPT_SAFE_UPLOAD');

        foreach ((array) $data as $key => $val) {
            if ($val === null) {
                unset($data[$key]);
            } elseif ($files && !$hasCURLFile && substr($val, 0, 1) === '@') {
                throw new TwitterException('Due to limitation of cURL it is not possible to send message starting with @ and upload file at the same time in PHP < 5.5');
            }
        }

        foreach ((array) $files as $key => $file) {
            if (!is_file($file)) {
                throw new TwitterException("Cannot read the file $file. Check if file exists on disk and check its permissions.");
            }
            $data[$key] = $hasCURLFile ? new \CURLFile($file) : '@' . $file;
        }

        $request = Twitter_OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $resource, $files ? [] : $data);
        $request->sign_request(new Twitter_OAuthSignatureMethod_HMAC_SHA1, $this->consumer, $this->token);

        $options = [
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
        ] + ($method === 'POST' ? [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $files ? $data : $request->to_postdata(),
            CURLOPT_URL => $files ? $request->to_url() : $request->get_normalized_http_url(),
        ] : [
            CURLOPT_URL => $request->to_url(),
        ]) + $this->httpOptions;

        if ($method === 'POST' && $hasCURLFile) {
            $options[CURLOPT_SAFE_UPLOAD] = true;
        }

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $result = curl_exec($curl);
        if (curl_errno($curl)) {
            throw new TwitterException('Server error: ' . curl_error($curl));
        }

        $payload = defined('JSON_BIGINT_AS_STRING')
        ? @json_decode($result, false, 128, JSON_BIGINT_AS_STRING)
        : @json_decode($result); // intentionally @

        if ($payload === false) {
            throw new TwitterException('Invalid server response');
        }

        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($code >= 400) {
            throw new TwitterException(isset($payload->errors[0]->message)
                ? $payload->errors[0]->message
                : "Server error #$code with answer $result",
                $code
            );
        }

        return $payload;
    }
}

/**
 * An exception generated by Twitter.
 */
class TwitterException extends \Exception
{
}
