# PHP Script to Post Tweets Using X (Twitter) API v2

This PHP script allows you to post tweets to **X** (formerly known as Twitter) using the X API v2 with OAuth 1.0a User Authentication.

## Prerequisites

- **PHP** installed on your system (PHP 7.2 or higher recommended).
- **cURL extension** enabled in PHP.
- A **Twitter Developer Account** (X Developer Account) with an app that has **Read and Write** permissions.
- **Composer** installed if you plan to use external dependencies.

## Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/dvirdung/php-x-twitter-api-v2-post-tweet.git
```

### 2. Install Dependencies

This script does not require external dependencies, but if you choose to use environment variables or the **vlucas/phpdotenv** library, install dependencies using Composer:

```bash
composer install
```

### Configuration
#### Option 1: Using Environment Variables

Rename the file .env.example to .env and update it with your Twitter API credentials:

```dotenv
API_KEY=your_api_key
API_SECRET_KEY=your_api_secret_key
ACCESS_TOKEN=your_access_token
ACCESS_TOKEN_SECRET=your_access_token_secret
```

#### Option 2: Using config.php

Create a config.php file in the project root and define your Twitter API credentials:

```php
<?php
define('API_KEY', 'your_api_key');
define('API_SECRET_KEY', 'your_api_secret_key');
define('ACCESS_TOKEN', 'your_access_token');
define('ACCESS_TOKEN_SECRET', 'your_access_token_secret');
?>
```

Note: For security reasons, ensure that config.php and .env are added to your .gitignore file and not committed to version control.

### Customize Your Tweet

Edit the post_tweet.php script to customize the tweet content:

```php
// The tweet content variables
$tweet_text = 'Hello, world! This is a tweet from my PHP script using Twitter API v2 from https://github.com/dvirdung/php-x-twitter-api-v2-post-tweet.';
$tweet_hashtags = ['#PHP', '#TwitterAPI', '#OAuth', '#php-x-twitter-api-v2-post-tweet'];
```

### Running the Script

From the command line, navigate to the directory containing the script and run:

```bash
php post_tweet.php
```

### Detailed Explanation

The script performs the following steps:

- Sets Up Twitter API Credentials: Loads credentials from config.php or environment variables.
- Constructs the Tweet Content: Combines `$tweet_text` and `$tweet_hashtags` into `$fullTweet`.
- Handles Character Limits: Accounts for Twitter's character limit (280 for standard accounts, 10,000 for premium).
- Adjusts for URLs, which count as 23 characters regardless of length.
- Truncates the tweet text if necessary to comply with limits.
- Prepares OAuth Parameters: Generates OAuth parameters including nonce, timestamp, and signature.
- Builds the Authorization Header: Creates the Authorization header required for OAuth 1.0a authentication.
- Makes the API Request: Uses cURL to send a POST request to https://api.twitter.com/2/tweets.
- Handles the Response: Checks the HTTP status code and displays a success or error message.
- Provides detailed error information if the request fails.

### Troubleshooting

- 401 Unauthorized Error: Ensure that your API credentials are correct and that your app has the necessary permissions.
  Confirm that the OAuth signature is correctly generated (the script handles this automatically).

- Character Limit Exceeded: The script truncates the tweet text if it exceeds the character limit.
  Review the adjusted tweet to ensure it conveys the intended message.

- cURL Errors: Verify that the cURL extension is installed and enabled in your PHP configuration.

Other Errors: Enable error reporting in PHP to display any warnings or errors:

```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Security Considerations

- Protect Your API Credentials: Do not share your API credentials publicly.
  Use environment variables or a configuration file that is excluded from version control.

- Rate Limits: Be aware of Twitter's API rate limits to avoid being throttled.

- Implement appropriate error handling for rate limit responses.

#### Contributing

Contributions are welcome! Please open an issue or submit a pull request for any improvements or bug fixes.

#### License

This project is licensed under the MIT License. See the LICENSE file for details.

___
#### Acknowledgements

Thanks to the Twitter Developer community for providing resources and support.
Inspired by examples and best practices in PHP development.

_____
#### Note on Rebranding

As of July 2023, Twitter has rebranded to X. This script uses the X API (formerly Twitter API) v2 to post tweets. References to "Twitter" are retained in some places for clarity and to assist users who are familiar with the previous branding.
