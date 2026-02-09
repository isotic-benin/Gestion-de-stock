<?php
// public/localisation.php
$page_title = "Localisation & Infos - HGB-MULTI";
include '../includes/header.php';
?>

<div class="container mx-auto p-6">
    <h1 class="text-4xl font-bold text-gray-800 mb-8 text-center">Où nous trouver et nos informations</h1>

    <div class="flex flex-col md:flex-row gap-8 mb-8">
        <div class="md:w-1/2 bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Notre Emplacement Principal</h2>
            <p class="text-gray-700 mb-4 flex items-start">
                <i class="fas fa-map-marker-alt text-blue-600 mr-3 mt-1 text-xl"></i>
                <span>Porto-Novo, AKONABOE, Bénin</span>
            </p>
            <p class="text-gray-700 mb-4">
                Nous sommes idéalement situés au cœur de la ville, facilement accessibles en voiture ou par les transports en commun. Un grand parking est disponible pour nos clients.
            </p>
            <!-- Placeholder pour la carte Google Maps -->
            <div class="w-full h-64 bg-gray-200 rounded-md flex items-center justify-center text-gray-500 mb-4">
                <p>
                    <i class="fas fa-map text-4xl mr-2"></i> Placeholder Carte (Intégrer Google Maps ici)
                </p>
            </div>
            <a href="https://www.google.com/maps/search/?api=1&query=123+Rue+de+la+Quincaillerie,Ville,Pays" target="_blank" class="text-blue-600 hover:underline flex items-center">
                <i class="fas fa-directions mr-2"></i> Obtenir l'itinéraire
            </a>
        </div>

        <div class="md:w-1/2 bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Informations sur l'Entreprise</h2>
            <div class="space-y-4">
                <p class="flex items-center text-gray-700 text-lg">
                    <i class="fas fa-phone-alt text-blue-600 mr-3"></i> <strong>Téléphone:</strong> +123 456 7890
                </p>
                <p class="flex items-center text-gray-700 text-lg">
                    <i class="fas fa-envelope text-blue-600 mr-3"></i> <strong>Email:</strong> info@quincailleriexyz.com
                </p>
                <p class="flex items-center text-gray-700 text-lg">
                    <i class="fas fa-globe text-blue-600 mr-3"></i> <strong>Site Web:</strong> www.quincailleriexyz.com
                </p>
                <p class="flex items-center text-gray-700 text-lg">
                    <i class="fas fa-clock text-blue-600 mr-3"></i> <strong>Heures d'ouverture:</strong><br>
                    Lundi - Vendredi: 8h00 - 18h00<br>
                    Samedi: 9h00 - 17h00<br>
                    Dimanche: Fermé
                </p>
                <p class="flex items-center text-gray-700 text-lg">
                    <i class="fas fa-building text-blue-600 mr-3"></i> <strong>Numéro d'enregistrement:</strong> QNCXYZ12345
                </p>
            </div>
            <h2 class="text-2xl font-semibold text-gray-800 mt-8 mb-4">Nos Réseaux Sociaux</h2>
            <div class="flex space-x-4">
                <a href="#" class="text-blue-600 hover:text-blue-800 transition duration-300 text-3xl"><i class="fab fa-facebook-square"></i></a>
                <a href="#" class="text-blue-600 hover:text-blue-800 transition duration-300 text-3xl"><i class="fab fa-twitter-square"></i></a>
                <a href="#" class="text-blue-600 hover:text-blue-800 transition duration-300 text-3xl"><i class="fab fa-instagram-square"></i></a>
                <a href="#" class="text-blue-600 hover:text-blue-800 transition duration-300 text-3xl"><i class="fab fa-linkedin"></i></a>
            </div>
        </div>
    </div>

    <section class="faq bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Foire Aux Questions (FAQ)</h2>
        <div class="space-y-4">
            <div class="border-b border-gray-200 pb-4">
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Quels types de produits proposez-vous ?</h3>
                <p class="text-gray-600">Nous proposons une vaste gamme de produits incluant des outils à main et électriques, des matériaux de construction (ciment, sable, gravier), des peintures, des articles de plomberie, d'électricité, de jardinage et bien plus encore.</p>
            </div>
            <div class="border-b border-gray-200 pb-4">
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Offrez-vous la livraison ?</h3>
                <p class="text-gray-600">Oui, nous offrons un service de livraison pour toutes nos commandes. Les frais et délais de livraison peuvent varier en fonction de la taille de la commande et de votre localisation. Veuillez nous contacter pour plus de détails.</p>
            </div>
            <div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Puis-je retourner un produit ?</h3>
                <p class="text-gray-600">Oui, nous acceptons les retours de produits non utilisés et dans leur emballage d'origine dans les 30 jours suivant l'achat, sur présentation du reçu. Certaines conditions peuvent s'appliquer.</p>
            </div>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
