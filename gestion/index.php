<?php
// public/login.php

// Inclure les fichiers nécessaires au tout début, avant toute sortie HTML.
// On suppose que db_connect.php inclut config.php, qui démarre la session
// et définit sanitize_input et BASE_URL.
include 'db_connect.php';

$login_error = '';

// Traiter la requête POST avant d'inclure le header HTML
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($_POST['username']);
    $password = sanitize_input($_POST['password']);

    // Requête SQL pour vérifier les identifiants
    // IMPORTANT: Le mot de passe sera comparé en texte clair.
    // Cette méthode est EXTRÊMEMENT INSECURISÉE et ne doit pas être utilisée en production.
    $sql = "SELECT id, nom_utilisateur, mot_de_passe, role FROM personnel WHERE nom_utilisateur = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $param_username);
        $param_username = $username;

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $id, $db_username, $db_password_plain, $role);
                if (mysqli_stmt_fetch($stmt)) {
                    // Comparaison directe du mot de passe (NON SÉCURISÉ)
                    if ($password === $db_password_plain) {
                        // La session est déjà active (démarrée par config.php selon le message d'erreur)
                        $_SESSION['loggedin'] = true;
                        $_SESSION['id'] = $id;
                        $_SESSION['username'] = $db_username;
                        $_SESSION['role'] = $role;

                        // Redirection vers le tableau de bord après connexion réussie
                        header("location: " . BASE_URL . "dashboard.php");
                        exit; // Terminer l'exécution du script après la redirection
                    } else {
                        $login_error = "Nom d'utilisateur ou mot de passe incorrect.";
                    }
                }
            } else {
                $login_error = "Nom d'utilisateur ou mot de passe incorrect.";
            }
        } else {
            $login_error = "Oops! Quelque chose s'est mal passé. Veuillez réessayer plus tard.";
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
}

// Définir le titre de la page et inclure le header HTML après toute logique de redirection
$page_title = "GESTION STOCK";
include 'includes/header.php';
?>

<div class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-lg">
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Connexion</h2>
        <p class="text-center text-gray-600 mb-6">Connectez-vous à votre compte pour accéder au tableau de bord.</p>

        <?php if (!empty($login_error)): ?>
            <div class="alert alert-error mb-4" id="message-container"><?php echo $login_error; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-5">
            <div class="form-group">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Nom d'utilisateur:</label>
                <input type="text" id="username" name="username"
                    class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="Entrez votre nom d'utilisateur" required>
            </div>
            <div class="form-group">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Mot de passe:</label>
                <input type="password" id="password" name="password"
                    class="shadow appearance-none border rounded-md w-full py-3 px-4 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline"
                    placeholder="Entrez votre mot de passe" required>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-md focus:outline-none focus:shadow-outline transition duration-300 w-full">
                    Se connecter <i class="fas fa-sign-in-alt ml-2"></i>
                </button>
            </div>
            <div class="text-center mt-4">
                <a href="#" class="inline-block align-baseline font-bold text-sm text-blue-600 hover:text-blue-800">
                    Mot de passe oublié?
                </a>
            </div>
        </form>
    </div>
</div>