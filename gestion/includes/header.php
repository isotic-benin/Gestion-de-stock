<?php
// includes/header.php
// session_start(); // Démarre la session pour toutes les pages

// Définir la constante BASE_URL si elle n'est pas déjà définie
// Cela permet d'avoir des chemins absolus pour les ressources et les redirections
if (!defined('BASE_URL')) {
    // Déterminez le protocole (http ou https)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    // Obtenez le nom d'hôte
    $host = $_SERVER['HTTP_HOST'];
    // Obtenez le chemin du répertoire racine de l'application
    // Supposons que votre application est à la racine ou dans un sous-répertoire connu
    // Si votre application est dans un sous-dossier, par exemple 'monapp/',
    // vous devrez ajuster ce chemin. Ici, on suppose que 'includes' est dans le dossier parent de la racine de l'app.
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path_parts = explode('/', $script_name);
    $base_path = '/'; // Chemin par défaut si l'app est à la racine du domaine

    // Trouver la partie du chemin qui correspond à la racine de l'application
    // Ceci est une heuristique, ajustez si votre structure de dossiers est différente
    // Par exemple, si 'includes' est dans 'app/includes', et 'app' est la racine, on veut '/app/'
    // Si 'includes' est directement dans la racine du site, on veut '/'
    if (in_array('public', $path_parts)) {
        $base_path = strstr($script_name, '/public/', true) . '/';
    } elseif (in_array('modules', $path_parts)) {
        $base_path = strstr($script_name, '/modules/', true) . '/';
    }
    // Fallback si aucun des chemins connus n'est trouvé, ou si l'app est à la racine
    if ($base_path === '/') {
        // Vérifier si le script est directement à la racine du document root
        $doc_root_path = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $current_dir = str_replace('\\', '/', dirname(__DIR__, 2)); // Remonter deux niveaux depuis 'includes'
        if ($current_dir === $doc_root_path) {
            $base_path = '/';
        } else {
            // Calculer le chemin relatif de l'application par rapport à DOCUMENT_ROOT
            $base_path = str_replace($doc_root_path, '', $current_dir) . '/';
            // Assurez-vous que le chemin commence et se termine par un slash
            $base_path = '/' . trim($base_path, '/') . '/';
            if ($base_path === '//')
                $base_path = '/'; // Corriger si double slash
        }
    }

    define('BASE_URL', $protocol . "://" . $host . $base_path);
}


// Vérifie si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

// Définir le texte et le lien du bouton de connexion/tableau de bord
$auth_button_text = $is_logged_in ? 'Tableau de Bord' : 'Connexion';
$auth_button_link = $is_logged_in ? BASE_URL . 'dashboard.php' : BASE_URL . 'public/login.php'; // Utilisation de BASE_URL
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Application de Gestion de Magasins'; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/style.css"> <!-- Utilisation de BASE_URL -->
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-100 font-sans leading-normal tracking-normal overflow-hidden h-screen">