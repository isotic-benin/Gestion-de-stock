<?php
// public/logout.php
session_start(); // Toujours démarrer la session au tout début

// Assurez-vous que config.php est inclus pour avoir BASE_URL défini
// Vérifiez et ajustez ce chemin si votre config.php n'est pas dans le dossier 'pos'
require_once dirname(__DIR__) . '/gestion/config.php';

// Détruit toutes les variables de session
$_SESSION = array();

// Si vous voulez détruire complètement la session, effacez également le cookie de session.
// Note : Cela détruira la session, et pas seulement les données de session !
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finalement, détruit la session.
session_destroy();

// Redirige vers la page de connexion en utilisant BASE_URL
header("location: " . BASE_URL . "index.php");
exit;
?>