<?php
// modules/ventes/ajax_search_clients.php
// Ce fichier gère la recherche de clients pour le point de vente.

session_start();
include '../../db_connect.php'; // Assurez-vous que ce chemin est correct

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Caissier', 'Vendeur'])) {
    echo json_encode([]);
    exit;
}

$query = isset($_GET['query']) ? sanitize_input($_GET['query']) : '';
$clients = [];

if (!empty($query) && strlen($query) >= 2) {
    // Fonction pour normaliser une chaîne (supprimer les accents et caractères spéciaux, mettre en minuscules)
    // Cette fonction est cruciale pour la recherche insensible aux accents et caractères spéciaux.
    function normalizeStringForSearch($str) {
        // Convertir en ASCII (enlève les accents)
        // Note: iconv peut nécessiter l'extension php_iconv activée sur votre serveur
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        // Convertir en minuscules
        $str = strtolower($str);
        // Supprimer les caractères non alphanumériques (sauf les espaces)
        $str = preg_replace('/[^a-z0-9\s]/', '', $str);
        // Remplacer les espaces multiples par un seul espace
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }

    // Normaliser la requête de l'utilisateur pour la comparaison
    $normalized_query_param = '%' . normalizeStringForSearch($query) . '%';

    // Requête SQL pour rechercher les clients par nom, prénom ou téléphone.
    // Les fonctions REPLACE et LOWER sont utilisées pour rendre la recherche insensible
    // aux accents et à la casse directement dans la base de données pour les champs texte.
    // Le champ 'telephone' est généralement numérique, donc une recherche LIKE simple est suffisante.
    $sql = "SELECT id, nom, prenom, telephone, email
            FROM clients
            WHERE (LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(nom, 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'ë', 'e'), 'à', 'a'), 'â', 'a'), 'ä', 'a'), 'ù', 'u'), 'û', 'u'), 'ü', 'u'), 'ô', 'o'), 'ö', 'o'), 'î', 'i'), 'ï', 'i')) LIKE ?)
               OR (LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(prenom, 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'ë', 'e'), 'à', 'a'), 'â', 'a'), 'ä', 'a'), 'ù', 'u'), 'û', 'u'), 'ü', 'u'), 'ô', 'o'), 'ö', 'o'), 'î', 'i'), 'ï', 'i')) LIKE ?)
               OR telephone LIKE ?
            LIMIT 10"; // Limite les résultats à 10 pour la performance

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Lier les paramètres : 'sss' pour les trois placeholders de chaîne normalisée
        mysqli_stmt_bind_param($stmt, "sss", $normalized_query_param, $normalized_query_param, $normalized_query_param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $clients[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        // En cas d'erreur de préparation de la requête, loguer l'erreur (pour le débogage)
        error_log("Erreur de préparation de la requête de recherche de clients: " . mysqli_error($conn));
    }
}

echo json_encode($clients);
mysqli_close($conn);
?>
