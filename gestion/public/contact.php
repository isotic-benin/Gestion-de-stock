<?php
// public/contact.php
$page_title = "Contact - HGB-MULTI";
include '../includes/header.php';

$message_status = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage et validation des entrées
    $nom = isset($_POST['nom']) ? htmlspecialchars(trim($_POST['nom'])) : '';
    $email = isset($_POST['email']) ? htmlspecialchars(trim($_POST['email'])) : '';
    $sujet = isset($_POST['sujet']) ? htmlspecialchars(trim($_POST['sujet'])) : '';
    $message = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message'])) : '';

    if (!empty($nom) && !empty($email) && !empty($sujet) && !empty($message) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Ici, vous enverriez l'e-mail ou enregistreriez le message dans une base de données
        // Pour cet exemple, nous allons juste simuler l'envoi.
        // En production, utilisez une fonction mail() ou une bibliothèque d'envoi d'e-mails.

        // Exemple de simulation d'envoi d'e-mail:
        $to = "contact@quincailleriehgb.com";
        $headers = "From: " . $nom . " <" . $email . ">\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";

        $email_body = "
        <html>
        <head>
            <title>Nouveau message de contact</title>
        </head>
        <body>
            <p><strong>Nom:</strong> " . $nom . "</p>
            <p><strong>Email:</strong> " . $email . "</p>
            <p><strong>Sujet:</strong> " . $sujet . "</p>
            <p><strong>Message:</strong><br>" . nl2br($message) . "</p>
        </body>
        </html>
        ";

        // mail($to, $sujet, $email_body, $headers); // Décommenter pour un envoi réel (nécessite une configuration PHP mail)

        $message_status = '<div class="alert alert-success" id="message-container">Votre message a été envoyé avec succès ! Nous vous répondrons bientôt.</div>';
        // Réinitialiser le formulaire après succès
        $_POST = array();

    } else {
        $message_status = '<div class="alert alert-error" id="message-container">Veuillez remplir tous les champs correctement.</div>';
    }
}
?>

<div class="container mx-auto p-6">
    <h1 class="text-4xl font-bold text-gray-800 mb-8 text-center">Contactez-nous</h1>

    <?php echo $message_status; // Affiche le message de statut ?>

    <div class="flex flex-col md:flex-row gap-8">
        <div class="md:w-2/3 bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Envoyez-nous un message</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                <div class="form-group">
                    <label for="nom" class="block text-gray-700 text-sm font-bold mb-2">Nom Complet:</label>
                    <input type="text" id="nom" name="nom" class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required value="<?php echo isset($_POST['nom']) ? $_POST['nom'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                    <input type="email" id="email" name="email" class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="sujet" class="block text-gray-700 text-sm font-bold mb-2">Sujet:</label>
                    <input type="text" id="sujet" name="sujet" class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required value="<?php echo isset($_POST['sujet']) ? $_POST['sujet'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="message" class="block text-gray-700 text-sm font-bold mb-2">Votre Message:</label>
                    <textarea id="message" name="message" rows="6" class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required><?php echo isset($_POST['message']) ? $_POST['message'] : ''; ?></textarea>
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md focus:outline-none focus:shadow-outline transition duration-300">
                    Envoyer le message <i class="fas fa-paper-plane ml-2"></i>
                </button>
            </form>
        </div>

        <div class="md:w-1/3 bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Nos Coordonnées</h2>
            <div class="space-y-4">
                <p class="flex items-center text-gray-700"><i class="fas fa-map-marker-alt text-blue-600 mr-3"></i> Porto-Novo, Akonaboè, Bénin</p>
                <p class="flex items-center text-gray-700"><i class="fas fa-phone-alt text-blue-600 mr-3"></i> +229 97 00 00 00</p>
                <p class="flex items-center text-gray-700"><i class="fas fa-envelope text-blue-600 mr-3"></i> info@quincailleriehgb.com</p>
                <p class="flex items-center text-gray-700"><i class="fas fa-clock text-blue-600 mr-3"></i> Lun - Sam: 8h00 - 18h00</p>
            </div>
            <h2 class="text-2xl font-semibold text-gray-800 mt-8 mb-4">Suivez-nous</h2>
            <div class="flex space-x-4">
                <a href="#" class="text-blue-600 hover:text-blue-800 transition duration-300 text-3xl"><i class="fab fa-facebook-square"></i></a>
                <a href="#" class="text-blue-600 hover:text-blue-800 transition duration-300 text-3xl"><i class="fab fa-twitter-square"></i></a>
                <a href="#" class="text-blue-600 hover:text-blue-800 transition duration-300 text-3xl"><i class="fab fa-instagram-square"></i></a>
                <a href="#" class="text-blue-600 hover:text-blue-800 transition duration-300 text-3xl"><i class="fab fa-linkedin"></i></a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
