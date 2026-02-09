<?php
// db_connect.php

// Inclut le fichier de configuration principal.
// Assurez-vous que ce chemin est correct par rapport à l'emplacement de db_connect.php.
// Si db_connect.php est à la racine du projet, et config.php aussi, alors __DIR__ . '/config.php' est correct.
require_once __DIR__ . '/config.php';

// Connexion à la base de données (utilise les constantes définies dans config.php)
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Vérifie la connexion
if ($conn === false) {
    die("ERREUR: Impossible de se connecter à la base de données. " . mysqli_connect_error());
}

// Définit le jeu de caractères UTF-8 pour la connexion
mysqli_set_charset($conn, 'utf8mb4');

// --- Chargement des informations entreprise (si la table existe) ---
// Fournit un fallback pour ne pas perturber le système si la table n'est pas encore créée.
$company_info = [
    'nom' => 'HGB Multi',
    'adresse' => '123 Rue de la Quincaillerie, Ville, Pays',
    'telephone' => '+123 456 7890',
    'email' => 'info@hgb.com',
    'ifu' => '0201910904776',
    'rccm' => 'RCCM RB/PNO/20 A 19823',
    'devise' => 'XOF',
];

// Si la table `entreprise_infos` n'existe pas encore, mysqli_prepare échouera : on garde le fallback.
$sql_company = "SELECT nom, adresse, telephone, email, ifu, rccm, devise FROM entreprise_infos WHERE id = 1 LIMIT 1";
if ($stmt_company = mysqli_prepare($conn, $sql_company)) {
    mysqli_stmt_execute($stmt_company);
    $result_company = mysqli_stmt_get_result($stmt_company);
    if ($result_company && ($row_company = mysqli_fetch_assoc($result_company))) {
        $company_info = array_merge($company_info, array_filter($row_company, fn($v) => $v !== null && $v !== ''));
    }
    mysqli_stmt_close($stmt_company);
}

// Fonction helper pour obtenir la devise
function get_currency() {
    global $company_info;
    return $company_info['devise'] ?? 'XOF';
}
?>
