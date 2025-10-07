<?php
/**
 * Configuration Google OAuth 2.0
 *
 * Pour obtenir vos identifiants:
 * 1. Aller sur https://console.cloud.google.com/
 * 2. Créer un nouveau projet ou sélectionner un projet existant
 * 3. Activer "Google+ API"
 * 4. Créer des identifiants OAuth 2.0
 * 5. Ajouter les URIs de redirection autorisées
 */

// Charger la config app pour BASE_URL
require_once __DIR__ . '/app.php';

// Configuration Google OAuth
define('GOOGLE_CLIENT_ID', 'VOTRE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'VOTRE_CLIENT_SECRET');

// Construire l'URL complète de redirection
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('GOOGLE_REDIRECT_URI', $protocol . '://' . $host . BASE_URL . '/auth/google_callback.php');

// URLs Google OAuth
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USER_INFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');

// Scopes demandés
define('GOOGLE_SCOPES', [
    'https://www.googleapis.com/auth/userinfo.email',
    'https://www.googleapis.com/auth/userinfo.profile'
]);

/**
 * Générer l'URL de connexion Google
 */
function getGoogleLoginUrl() {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => implode(' ', GOOGLE_SCOPES),
        'access_type' => 'offline',
        'prompt' => 'consent'
    ];

    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

/**
 * Échanger le code d'autorisation contre un access token
 */
function getGoogleAccessToken($code) {
    $data = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Erreur Google OAuth: HTTP $httpCode - $response");
        return false;
    }

    $result = json_decode($response, true);
    return $result['access_token'] ?? false;
}

/**
 * Récupérer les informations de l'utilisateur Google
 */
function getGoogleUserInfo($accessToken) {
    $ch = curl_init(GOOGLE_USER_INFO_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Erreur récupération info Google: HTTP $httpCode - $response");
        return false;
    }

    return json_decode($response, true);
}
?>
