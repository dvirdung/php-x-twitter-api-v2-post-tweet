<?php
/**
 * Script to post a tweet using Twitter API v2 with OAuth 1.0a User Authentication.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Twitter API credentials
require_once('config.php');

// The tweet content variables
$tweet_text = 'Hello, world! This is a tweet from my PHP script using Twitter API v2 from https://github.com/dvirdung/php-x-twitter-api-v2-post-tweet.';
$tweet_hashtags = ['#PHP', '#TwitterAPI', '#OAuth', '#PhpXTwitterApiV2PostTweet'];

// Combine tweet text and hashtags
$fullTweet = $tweet_text . ' ' . implode(' ', $tweet_hashtags);

// Twitter's character limit:
// - For standard accounts: 280 characters
// - For Twitter Blue subscribers (premium accounts): 25,000 characters
// Set the character limit according to your account type
$max_tweet_length = 280; // Change to 25000 if you have a premium account

// Check if the tweet exceeds the character limit
if (mb_strlen($fullTweet) > $max_tweet_length) {
    // Option 1: Truncate the tweet
    $allowed_length = $max_tweet_length - mb_strlen(' ' . implode(' ', $tweet_hashtags));
    $tweet_text_truncated = mb_substr($tweet_text, 0, $allowed_length - 3) . '...';
    $fullTweet = $tweet_text_truncated . ' ' . implode(' ', $tweet_hashtags);

    // Option 2: Notify the user and exit
    /*
    echo "Tweet is too long by " . (mb_strlen($fullTweet) - $max_tweet_length) . " characters.";
    exit;
    */
}

// Twitter API endpoint for creating a tweet (API v2)
$url = 'https://api.twitter.com/2/tweets';

// OAuth parameters
$oauth = [
    'oauth_consumer_key' => API_KEY,
    'oauth_nonce' => bin2hex(random_bytes(16)),
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp' => time(),
    'oauth_token' => ACCESS_TOKEN,
    'oauth_version' => '1.0',
];

// Parameters for the POST request (tweet content)
$postfields = [
    'text' => $fullTweet
];

// The parameters for the signature base string include only the OAuth parameters
$signature_params = $oauth;

// Build the signature base string
$base_info = buildBaseString($url, 'POST', $signature_params);

// Generate the composite signing key
$composite_key = rawurlencode(API_SECRET_KEY) . '&' . rawurlencode(ACCESS_TOKEN_SECRET);

// Generate the OAuth signature and add it to the OAuth parameters
$oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
$oauth['oauth_signature'] = $oauth_signature;

// Build the Authorization header
$auth_header = buildAuthorizationHeader($oauth);

// Initialize cURL
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $auth_header,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($postfields, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
]);

// Execute the request
$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Close cURL
curl_close($ch);

// Decode the response
$response_data = json_decode($response, true);

// Handle the response
if ($http_status === 201 && isset($response_data['data']['id'])) {
    echo "Tweet posted successfully! Tweet ID: " . $response_data['data']['id'];
} else {
    echo "Failed to post tweet. HTTP Status Code: $http_status\n";

    // Enhanced error handling
    if (isset($response_data['errors'])) {
        foreach ($response_data['errors'] as $error) {
            echo "Error Code: " . $error['code'] . "\n";
            echo "Message: " . $error['message'] . "\n";
            if (isset($error['details'])) {
                echo "Details: " . implode('; ', $error['details']) . "\n";
            }
        }
    } else {
        echo "Response: $response\n";
    }
}

/**
 * Function to build the base string for the OAuth signature.
 *
 * @param string $baseURI
 * @param string $method
 * @param array $params
 * @return string
 */
function buildBaseString($baseURI, $method, $params)
{
    // Sort parameters alphabetically by key
    ksort($params);

    $r = [];
    foreach ($params as $key => $value) {
        // Percent-encode key and value as per RFC 3986
        $r[] = rawurlencode($key) . '=' . rawurlencode($value);
    }

    // Concatenate parameters with '&'
    $param_string = implode('&', $r);

    // Build the base string
    return $method . '&' . rawurlencode($baseURI) . '&' . rawurlencode($param_string);
}

/**
 * Function to build the Authorization header.
 *
 * @param array $oauth
 * @return string
 */
function buildAuthorizationHeader($oauth)
{
    $r = 'OAuth ';
    $values = [];
    foreach ($oauth as $key => $value) {
        // Percent-encode key and value
        $values[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
    }
    $r .= implode(', ', $values);
    return $r;
}
?>
