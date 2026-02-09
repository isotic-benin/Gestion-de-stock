<?php
// modules/rapports/ajax_export_pdf.php
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php';

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Gérant'])) {
    header("location: ../../public/login.php");
    exit;
}

// --- Récupération des Statistiques (dupliqué de statistiques_rapports.php pour l'export) ---

// Gestion des filtres depuis l'URL
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$magasin_id_filter = isset($_GET['magasin_id']) ? $_GET['magasin_id'] : '';

$sql_filters = '';
if (!empty($start_date)) {
    $sql_filters .= " AND v.date_vente >= '" . mysqli_real_escape_string($conn, $start_date) . " 00:00:00'";
}
if (!empty($end_date)) {
    $sql_filters .= " AND v.date_vente <= '" . mysqli_real_escape_string($conn, $end_date) . " 23:59:59'";
}
if (!empty($magasin_id_filter)) {
    $sql_filters .= " AND v.magasin_id = " . mysqli_real_escape_string($conn, $magasin_id_filter);
}

// Pour les statistiques qui ne concernent pas les ventes, ajuster les filtres
$sql_produits_filters = '';
if (!empty($magasin_id_filter)) {
    // $sql_produits_filters .= " AND magasin_id = " . mysqli_real_escape_string($conn, $magasin_id_filter); // Si produits sont liés au magasin
}

$sql_dettes_clients_filters = '';
if (!empty($start_date)) {
    $sql_dettes_clients_filters .= " AND date_creation >= '" . mysqli_real_escape_string($conn, $start_date) . " 00:00:00'";
}
if (!empty($end_date)) {
    $sql_dettes_clients_filters .= " AND date_creation <= '" . mysqli_real_escape_string($conn, $end_date) . " 23:59:59'";
}

$sql_dettes_magasins_filters = '';
if (!empty($start_date)) {
    $sql_dettes_magasins_filters .= " AND date_emission >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
}
if (!empty($end_date)) {
    $sql_dettes_magasins_filters .= " AND date_emission <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
}
if (!empty($magasin_id_filter)) {
    $sql_dettes_magasins_filters .= " AND magasin_id = " . mysqli_real_escape_string($conn, $magasin_id_filter);
}

$sql_epargne_clients_filters = '';

// 1. Bénéfices total des ventes
$total_benefices = 0;
$sql_benefices = "SELECT SUM(dv.total_ligne - (dv.quantite * dv.prix_achat_unitaire)) AS total_profit
                  FROM details_vente dv
                  JOIN ventes v ON dv.vente_id = v.id
                  WHERE v.statut_paiement = 'paye' " . $sql_filters;
$result_benefices = mysqli_query($conn, $sql_benefices);
if ($result_benefices && mysqli_num_rows($result_benefices) > 0) {
    $row = mysqli_fetch_assoc($result_benefices);
    $total_benefices = $row['total_profit'] ?? 0;
}

// 2. Total des produits
$total_produits = 0;
$sql_total_produits = "SELECT COUNT(id) AS total_count FROM produits" . $sql_produits_filters;
$result_total_produits = mysqli_query($conn, $sql_total_produits);
if ($result_total_produits && mysqli_num_rows($result_total_produits) > 0) {
    $row = mysqli_fetch_assoc($result_total_produits);
    $total_produits = $row['total_count'] ?? 0;
}

// 3. Nombre de produits en alerte de stock
$produits_alerte_stock = 0;
$sql_alerte_stock = "SELECT COUNT(id) AS alert_count FROM produits WHERE quantite_stock <= seuil_alerte_stock" . $sql_produits_filters;
$result_alerte_stock = mysqli_query($conn, $sql_alerte_stock);
if ($result_alerte_stock && mysqli_num_rows($result_alerte_stock) > 0) {
    $row = mysqli_fetch_assoc($result_alerte_stock);
    $produits_alerte_stock = $row['alert_count'] ?? 0;
}

// 4. Total des dettes clients (montant restant dû)
$total_dettes_clients = 0;
$sql_dettes_clients = "SELECT SUM(montant_restant) AS total_debt FROM dettes_clients WHERE (statut = 'en_cours' OR statut = 'partiellement_paye')" . $sql_dettes_clients_filters;
$result_dettes_clients = mysqli_query($conn, $sql_dettes_clients);
if ($result_dettes_clients && mysqli_num_rows($result_dettes_clients) > 0) {
    $row = mysqli_fetch_assoc($result_dettes_clients);
    $total_dettes_clients = $row['total_debt'] ?? 0;
}

// 5. Total des dettes des magasins (dettes de l'entreprise)
$total_dettes_magasins = 0;
$sql_dettes_magasins = "SELECT SUM(montant_restant) AS total_debt FROM dettes_magasins WHERE (statut = 'en_cours' OR statut = 'partiellement_payee')" . $sql_dettes_magasins_filters;
$result_dettes_magasins = mysqli_query($conn, $sql_dettes_magasins);
if ($result_dettes_magasins && mysqli_num_rows($result_dettes_magasins) > 0) {
    $row = mysqli_fetch_assoc($result_dettes_magasins);
    $total_dettes_magasins = $row['total_debt'] ?? 0;
}

// 6. Total de l'épargne des clients
$total_epargne_clients = 0;
$sql_epargne_clients = "SELECT SUM(solde) AS total_savings FROM comptes_epargne" . $sql_epargne_clients_filters;
$result_epargne_clients = mysqli_query($conn, $sql_epargne_clients);
if ($result_epargne_clients && mysqli_num_rows($result_epargne_clients) > 0) {
    $row = mysqli_fetch_assoc($result_epargne_clients);
    $total_epargne_clients = $row['total_savings'] ?? 0;
}

// 7. Total des ventes (montant total)
$total_ventes = 0;
$sql_total_ventes = "SELECT SUM(v.montant_total) AS total_sales FROM ventes v WHERE v.statut_paiement = 'paye' " . $sql_filters;
$result_total_ventes = mysqli_query($conn, $sql_total_ventes);
if ($result_total_ventes && mysqli_num_rows($result_total_ventes) > 0) {
    $row = mysqli_fetch_assoc($result_total_ventes);
    $total_ventes = $row['total_sales'] ?? 0;
}


// Get store name if a filter is applied
$magasin_nom = "Tous";
if (!empty($magasin_id_filter)) {
    $sql_magasin_nom = "SELECT nom FROM magasins WHERE id = " . mysqli_real_escape_string($conn, $magasin_id_filter);
    $result_magasin_nom = mysqli_query($conn, $sql_magasin_nom);
    if ($result_magasin_nom && mysqli_num_rows($result_magasin_nom) > 0) {
        $magasin_row = mysqli_fetch_assoc($result_magasin_nom);
        $magasin_nom = $magasin_row['nom'];
    }
}


// Fermer la connexion à la base de données
mysqli_close($conn);

// Générer le contenu HTML pour le PDF
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport de Statistiques - <?php echo htmlspecialchars(($company_info['nom'] ?? 'HGB Multi')); ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            color: #333;
            line-height: 1.6;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #eee;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #0056b3;
            text-align: center;
            margin-bottom: 20px;
        }
        .header-info {
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.9em;
            color: #555;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .stat-card p {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
            margin: 0;
        }
        .stat-card.green p { color: #28a745; }
        .stat-card.blue p { color: #007bff; }
        .stat-card.yellow p { color: #ffc107; }
        .stat-card.red p { color: #dc3545; }
        .stat-card.purple p { color: #6f42c1; }
        .stat-card.teal p { color: #20c997; }
        .stat-card.indigo p { color: #4b0082; } /* Added for Total Sales */

        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 0.8em;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        @media print {
            body {
                margin: 0;
                -webkit-print-color-adjust: exact; /* Pour Chrome/Safari */
                color-adjust: exact; /* Standard */
            }
            .container {
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .stat-card {
                page-break-inside: avoid;
                background-color: #f9f9f9 !important; /* Force background print */
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .stat-card h3, .stat-card p {
                color: #333 !important; /* Ensure text color prints */
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .stat-card.green p { color: #28a745 !important; }
            .stat-card.blue p { color: #007bff !important; }
            .stat-card.yellow p { color: #ffc107 !important; }
            .stat-card.red p { color: #dc3545 !important; }
            .stat-card.purple p { color: #6f42c1 !important; }
            .stat-card.teal p { color: #20c997 !important; }
            .stat-card.indigo p { color: #4b0082 !important; } /* Added for Total Sales */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Rapport de Statistiques</h1>
        <div class="header-info">
            <p><strong><?php echo htmlspecialchars(($company_info['nom'] ?? 'HGB Multi')); ?></strong></p>
            <p>Date du rapport: <?php echo date('d/m/Y H:i'); ?></p>
            <p>Généré par: <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</p>
            <?php if (!empty($start_date) || !empty($end_date)) : ?>
                <p>Période: Du <?php echo !empty($start_date) ? date('d/m/Y', strtotime($start_date)) : 'début'; ?> au <?php echo !empty($end_date) ? date('d/m/Y', strtotime($end_date)) : 'présent'; ?></p>
            <?php endif; ?>
            <?php if (!empty($magasin_id_filter)) : ?>
                <p>Magasin: <?php echo htmlspecialchars($magasin_nom); ?></p>
            <?php endif; ?>
        </div>

        <h2>Statistiques Clés de l'Entreprise</h2>
        <div class="stats-grid">
            <div class="stat-card green">
                <h3>Bénéfices Total des Ventes</h3>
                <p><?php echo number_format($total_benefices, 2, ',', ' '); ?> XOF</p>
            </div>
            <div class="stat-card indigo">
                <h3>Total des Ventes</h3>
                <p><?php echo number_format($total_ventes, 2, ',', ' '); ?> XOF</p>
            </div>
            <div class="stat-card blue">
                <h3>Total des Produits</h3>
                <p><?php echo number_format($total_produits, 0, ',', ' '); ?></p>
            </div>
            <div class="stat-card yellow">
                <h3>Produits en Alerte de Stock</h3>
                <p><?php echo number_format($produits_alerte_stock, 0, ',', ' '); ?></p>
            </div>
            <div class="stat-card red">
                <h3>Total Dettes Clients</h3>
                <p><?php echo number_format($total_dettes_clients, 2, ',', ' '); ?> XOF</p>
            </div>
            <div class="stat-card purple">
                <h3>Total Dettes Magasins</h3>
                <p><?php echo number_format($total_dettes_magasins, 2, ',', ' '); ?> XOF</p>
            </div>
            <div class="stat-card teal">
                <h3>Total Épargne Clients</h3>
                <p><?php echo number_format($total_epargne_clients, 2, ',', ' '); ?> XOF</p>
            </div>
        </div>

        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(($company_info['nom'] ?? 'HGB Multi')); ?>. Tous droits réservés.</p>
            <p>Ce rapport est généré automatiquement et peut ne pas inclure toutes les données en temps réel.</p>
        </div>
    </div>

    <script>
        // Déclenche l'impression automatiquement une fois la page chargée
        window.onload = function() {
            window.print();
            // Optionnel: rediriger l'utilisateur après un court délai
            setTimeout(function() {
                window.close(); // Ferme la fenêtre d'impression
            }, 1000); // 1 seconde de délai
        };
    </script>
</body>
</html>