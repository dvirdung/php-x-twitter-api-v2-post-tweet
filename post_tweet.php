<?php
/**
 * Script to post a tweet using Twitter API v2 with OAuth 1.0a User Authentication.
 */

error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') ? '1' : '0');

// Optional file-based credentials. Environment variables override these values.
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$backend = strtolower(trim(getConfigValue('TWITTER_BACKEND', 'twitter')));
if ($backend !== 'twitter' && $backend !== 'xquik') {
    fwrite(STDERR, "Error: TWITTER_BACKEND must be twitter or xquik.\n");
    exit(1);
}

if ($backend === 'twitter') {
    validateCredentials([
        'API_KEY' => getConfigValue('API_KEY'),
        'API_SECRET_KEY' => getConfigValue('API_SECRET_KEY'),
        'ACCESS_TOKEN' => getConfigValue('ACCESS_TOKEN'),
        'ACCESS_TOKEN_SECRET' => getConfigValue('ACCESS_TOKEN_SECRET'),
    ]);
}

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

if ($backend === 'xquik') {
    postTweetWithXquik($fullTweet);
    exit(0);
}

// Twitter API endpoint for creating a tweet (API v2)
$url = 'https://api.twitter.com/2/tweets';

// OAuth parameters
$oauth = [
    'oauth_consumer_key' => getConfigValue('API_KEY'),
    'oauth_nonce' => bin2hex(random_bytes(16)),
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp' => time(),
    'oauth_token' => getConfigValue('ACCESS_TOKEN'),
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
$composite_key =
    rawurlencode(getConfigValue('API_SECRET_KEY')) .
    '&' .
    rawurlencode(getConfigValue('ACCESS_TOKEN_SECRET'));

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
$curl_err = $response === false ? curl_error($ch) : null;

// PHP 8 releases cURL handles automatically. PHP 8.5 deprecates curl_close().
if (PHP_VERSION_ID < 80000) {
    curl_close($ch);
}

if ($response === false) {
    $error_message = "Failed to post tweet. cURL error: $curl_err\n";
    echo $error_message;
    logTwitterResponse($http_status, $error_message);
    exit(1);
}

// Decode the response
$response_data = json_decode($response, true);

// Handle the response
if ($http_status === 201 && isset($response_data['data']['id'])) {
    $success_message = "Tweet posted successfully! Tweet ID: " . $response_data['data']['id'];
    echo $success_message;
    logTwitterResponse($http_status, $success_message);
} else {
    $error_message = "Failed to post tweet. HTTP Status Code: $http_status\n";

    // Enhanced error handling
    if (isset($response_data['errors'])) {
        foreach ($response_data['errors'] as $error) {
            $error_message .= "Error Code: " . $error['code'] . "\n";
            $error_message .= "Message: " . $error['message'] . "\n";
            if (isset($error['details'])) {
                $error_message .= "Details: " . implode('; ', $error['details']) . "\n";
            }
        }
    } else {
        $error_message .=
            "Response: " .
            ($response_data === null
                ? "Could not decode JSON response. Raw: $response"
                : $response) .
            "\n";
    }

    echo $error_message;
    logTwitterResponse($http_status, $error_message);
    exit(1);
}

/**
 * Reads a config value from the environment or config.php.
 *
 * @param string $name
 * @param string $default
 * @return string
 */
function getConfigValue($name, $default = '')
{
    $environmentValue = getenv($name);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    return defined($name) ? (string) constant($name) : $default;
}

/**
 * Validates required credentials.
 *
 * @param array $credentials
 * @return void
 */
function validateCredentials($credentials)
{
    foreach ($credentials as $name => $value) {
        if (empty($value) || stripos($value, 'your_') === 0) {
            fwrite(STDERR, "Error: $name is not configured.\n");
            exit(1);
        }
    }
}

/**
 * Posts a tweet through Xquik's REST API.
 *
 * @param string $fullTweet
 * @return void
 */
function postTweetWithXquik($fullTweet)
{
    $apiKey = getConfigValue('XQUIK_API_KEY');
    $account = getConfigValue('XQUIK_ACCOUNT');
    validateCredentials([
        'XQUIK_API_KEY' => $apiKey,
        'XQUIK_ACCOUNT' => $account,
    ]);

    $apiBase = rtrim(getConfigValue('XQUIK_API_BASE', 'https://xquik.com/api/v1'), '/');
    $postfields = json_encode([
        'account' => $account,
        'text' => $fullTweet,
    ], JSON_UNESCAPED_UNICODE);
    if ($postfields === false) {
        fwrite(STDERR, "Error: Could not encode the Xquik request.\n");
        exit(1);
    }

    $ch = curl_init();
    if ($ch === false) {
        fwrite(STDERR, "Error: Could not initialize cURL.\n");
        exit(1);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $apiBase . '/x/tweets',
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $postfields,
        CURLOPT_RETURNTRANSFER => true,
    ]);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = $response === false ? curl_error($ch) : null;
    if (PHP_VERSION_ID < 80000) {
        curl_close($ch);
    }

    if ($response === false) {
        $error_message = "Failed to post tweet with Xquik. cURL error: $curl_err\n";
        fwrite(STDERR, $error_message);
        logTwitterResponse($http_status, $error_message);
        exit(1);
    }

    $response_data = json_decode($response, true);
    if ($http_status >= 200 && $http_status < 300 && isset($response_data['tweetId'])) {
        $success_message = "Tweet posted successfully with Xquik! Tweet ID: " . $response_data['tweetId'];
        echo $success_message;
        logTwitterResponse($http_status, $success_message);
        return;
    }

    $error_message = "Failed to post tweet with Xquik. HTTP Status Code: $http_status\n";
    if (is_array($response_data) && isset($response_data['message'])) {
        $error_message .= "Message: " . $response_data['message'] . "\n";
    }
    fwrite(STDERR, $error_message);
    logTwitterResponse($http_status, $error_message);
    exit(1);
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

/**
 * Logs Twitter API responses and errors with timestamp
 *
 * @param int $status HTTP status code
 * @param string $message Response message or error details
 * @param string $logFile Path to log file
 * @return void
 */
function logTwitterResponse($status, $message, $logFile = 'twitter_api.log')
{
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] Status: $status\nMessage: $message\n---\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
