<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URL);
$client->addScope("email");
$client->addScope("profile");
$client->addScope("https://www.googleapis.com/auth/gmail.modify"); // Gmail modification access
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['error'])) {
        die("Error fetching access token: " . $token['error_description']);
    }

    $client->setAccessToken($token);
    
    // Get user info
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    
    $email = $google_account_info->email;
    $name = $google_account_info->name;
    $picture = $google_account_info->picture;
    $google_id = $google_account_info->id;
    
    $access_token = json_encode($token);
    $refresh_token = $client->getRefreshToken();
    $expires_in = time() + $token['expires_in'];

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user
        $stmt = $pdo->prepare("UPDATE users SET google_id = ?, name = ?, picture = ?, access_token = ?, refresh_token = ?, token_expires = ? WHERE email = ?");
        $stmt->execute([$google_id, $name, $picture, $access_token, $refresh_token, $expires_in, $email]);
        $userId = $user['id'];
    } else {
        // Create new user
        $stmt = $pdo->prepare("INSERT INTO users (google_id, email, name, picture, access_token, refresh_token, token_expires) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$google_id, $email, $name, $picture, $access_token, $refresh_token, $expires_in]);
        $userId = $pdo->lastInsertId();
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['access_token'] = $access_token;
    
    header("Location: dashboard.php");
    exit();
} else {
    // Redirect to Google login
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();
}
?>
