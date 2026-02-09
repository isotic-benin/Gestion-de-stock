<?php
// modules/parametres/configuration_entreprise.php
session_start();

include '../../db_connect.php';
include '../../includes/header.php';

// Accès: Administrateur uniquement
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] ?? '') !== 'Administrateur') {
    header("location: " . BASE_URL . "public/login.php");
    exit;
}

$page_title = "Configuration Entreprise";
$message = '';

// Charger infos actuelles (fallback via $company_info depuis db_connect.php)
$current = $company_info ?? [
    'nom' => 'HGB Multi',
    'adresse' => '',
    'telephone' => '',
    'email' => '',
    'ifu' => '',
    'rccm' => '',
    'devise' => 'XOF',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_company'])) {
    $nom = sanitize_input($_POST['nom'] ?? '');
    $adresse = sanitize_input($_POST['adresse'] ?? '');
    $telephone = sanitize_input($_POST['telephone'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $ifu = sanitize_input($_POST['ifu'] ?? '');
    $rccm = sanitize_input($_POST['rccm'] ?? '');
    $devise = sanitize_input($_POST['devise'] ?? 'XOF');

    if ($nom === '') {
        $message = '<div class="alert alert-error">Le nom de l\'entreprise est obligatoire.</div>';
    } else {
        // Vérifier si la colonne devise existe, sinon l'ajouter
        $check_column = "SHOW COLUMNS FROM entreprise_infos LIKE 'devise'";
        $result_check = mysqli_query($conn, $check_column);
        if (mysqli_num_rows($result_check) == 0) {
            // Ajouter la colonne devise si elle n'existe pas
            $alter_sql = "ALTER TABLE entreprise_infos ADD COLUMN devise VARCHAR(10) DEFAULT 'XOF' AFTER rccm";
            mysqli_query($conn, $alter_sql);
        }

        // Upsert id=1 (évite de perturber les fonctionnalités existantes)
        $sql = "INSERT INTO entreprise_infos (id, nom, adresse, telephone, email, ifu, rccm, devise)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    nom = VALUES(nom),
                    adresse = VALUES(adresse),
                    telephone = VALUES(telephone),
                    email = VALUES(email),
                    ifu = VALUES(ifu),
                    rccm = VALUES(rccm),
                    devise = VALUES(devise)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssss", $nom, $adresse, $telephone, $email, $ifu, $rccm, $devise);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Informations entreprise enregistrées avec succès.</div>';
                $current = [
                    'nom' => $nom,
                    'adresse' => $adresse,
                    'telephone' => $telephone,
                    'email' => $email,
                    'ifu' => $ifu,
                    'rccm' => $rccm,
                    'devise' => $devise,
                ];
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'enregistrement : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = '<div class="alert alert-error">Erreur de préparation SQL : ' . mysqli_error($conn) . '</div>';
        }
    }
}
?>

<div class="flex h-screen bg-gray-100">
    <?php include '../../includes/sidebar_dashboard.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include '../../includes/topbar.php'; ?>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 pb-20">
            <div class="container mx-auto">
                <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $page_title; ?></h1>

                <?php if (!empty($message)): ?>
                    <div class="mb-4"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="nom">Nom entreprise *</label>
                                <input id="nom" name="nom" required
                                       value="<?php echo htmlspecialchars($current['nom'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="telephone">Téléphone</label>
                                <input id="telephone" name="telephone"
                                       value="<?php echo htmlspecialchars($current['telephone'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                                <input id="email" name="email" type="email"
                                       value="<?php echo htmlspecialchars($current['email'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="adresse">Adresse</label>
                                <input id="adresse" name="adresse"
                                       value="<?php echo htmlspecialchars($current['adresse'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="ifu">IFU</label>
                                <input id="ifu" name="ifu"
                                       value="<?php echo htmlspecialchars($current['ifu'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="rccm">RCCM</label>
                                <input id="rccm" name="rccm"
                                       value="<?php echo htmlspecialchars($current['rccm'] ?? ''); ?>"
                                       class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="devise">Devise *</label>
                                <select id="devise" name="devise" required
                                       class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="XOF" <?php echo (($current['devise'] ?? 'XOF') == 'XOF') ? 'selected' : ''; ?>>XOF (Franc CFA)</option>
                                    <option value="EUR" <?php echo (($current['devise'] ?? 'XOF') == 'EUR') ? 'selected' : ''; ?>>EUR (Euro)</option>
                                    <option value="USD" <?php echo (($current['devise'] ?? 'XOF') == 'USD') ? 'selected' : ''; ?>>USD (Dollar US)</option>
                                    <option value="GBP" <?php echo (($current['devise'] ?? 'XOF') == 'GBP') ? 'selected' : ''; ?>>GBP (Livre Sterling)</option>
                                    <option value="NGN" <?php echo (($current['devise'] ?? 'XOF') == 'NGN') ? 'selected' : ''; ?>>NGN (Naira)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" name="save_company"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <?php include '../../includes/footer.php'; ?>
    </div>
</div>

</body>
</html>


