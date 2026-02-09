<?php
// config.php

// --- Variables de Configuration Globales ---

// Définit le chemin absolu du système de fichiers vers le répertoire racine du projet.
// Ceci suppose que config.php est situé directement dans le dossier racine de votre projet.
// Exemple : C:\xampp\htdocs\pos
define('ROOT_DIR', __DIR__);

// Définit le nom du sous-dossier de votre projet par rapport à la racine du serveur web (htdocs).
// Si votre projet est directement dans htdocs (ex: http://localhost/), laissez vide: define('PROJECT_SUBFOLDER', '');
// Si votre projet est dans C:\xampp\htdocs\mon_projet\ (ex: http://localhost/mon_projet/), mettez 'mon_projet'.
define('PROJECT_SUBFOLDER', 'gestion-stock/gestion'); // <--- MODIFIEZ CECI AVEC LE NOM DE VOTRE DOSSIER DE PROJET

// Définit l'URL de base de l'application web.
// Ceci est utilisé pour générer tous les liens internes (href, src) afin d'assurer la portabilité.

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

$base_url_path = '/';
if (!empty(PROJECT_SUBFOLDER)) {
    $base_url_path .= trim(PROJECT_SUBFOLDER, '/') . '/';
}

define('BASE_URL', $protocol . '://' . $host . $base_url_path);


// --- Détails de Connexion à la Base de Données ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gestion');

// --- Rapports d'Erreurs (pour le développement) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Fonctions Utilitaires Globales ---

/**
 * Nettoie les données d'entrée pour prévenir les attaques XSS et autres injections.
 * Note : Pour l'injection SQL, utilisez toujours des requêtes préparées avec des paramètres.
 * Cette fonction gère le nettoyage général HTML et l'injection de scripts.
 *
 * @param string $data Les données d'entrée à nettoyer.
 * @return string Les données nettoyées.
 */
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Démarre la session si elle n'est pas déjà démarrée (important pour de nombreux fichiers)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
