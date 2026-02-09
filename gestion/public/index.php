<?php
// public/index.php
$page_title = "Accueil - HGB-MULTI";
include '../includes/header.php';
?>

<div class="container mx-auto p-6">
    <section class="hero bg-blue-600 text-white rounded-lg shadow-lg p-8 mb-8 text-center">
        <h1 class="text-4xl md:text-5xl font-bold mb-4">Bienvenue à la HGB-MULTI</h1>
        <p class="text-lg md:text-xl mb-6">Votre partenaire de confiance pour tous vos besoins en matériaux de construction, outils et équipements.</p>
        <a href="/public/contact.php" class="bg-white text-blue-600 hover:bg-blue-100 font-semibold py-3 px-8 rounded-full shadow-md transition duration-300 transform hover:scale-105">Contactez-nous</a>
    </section>

    <section class="features grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
        <div class="feature-card bg-white p-6 rounded-lg shadow-md text-center transform hover:scale-105 transition duration-300">
            <i class="fas fa-tools text-5xl text-blue-600 mb-4"></i>
            <h2 class="text-2xl font-semibold mb-2">Large Gamme de Produits</h2>
            <p class="text-gray-700">Découvrez notre vaste sélection d'outils, matériaux, peintures et bien plus encore.</p>
        </div>
        <div class="feature-card bg-white p-6 rounded-lg shadow-md text-center transform hover:scale-105 transition duration-300">
            <i class="fas fa-handshake text-5xl text-blue-600 mb-4"></i>
            <h2 class="text-2xl font-semibold mb-2">Service Client Expert</h2>
            <p class="text-gray-700">Notre équipe est là pour vous conseiller et vous aider à trouver ce dont vous avez besoin.</p>
        </div>
        <div class="feature-card bg-white p-6 rounded-lg shadow-md text-center transform hover:scale-105 transition duration-300">
            <i class="fas fa-truck-fast text-5xl text-blue-600 mb-4"></i>
            <h2 class="text-2xl font-semibold mb-2">Livraison Rapide</h2>
            <p class="text-gray-700">Profitez de notre service de livraison efficace pour vos commandes.</p>
        </div>
    </section>

    <section class="about-us bg-white p-8 rounded-lg shadow-md mb-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-4 text-center">À propos de nous</h2>
        <p class="text-gray-700 leading-relaxed mb-4">
            Depuis plus de 20 ans, la HGB-MULTI est un pilier de la communauté, fournissant des produits de qualité supérieure et un service inégalé aux professionnels et aux particuliers. Nous nous engageons à offrir les meilleurs produits aux meilleurs prix, tout en cultivant des relations durables avec nos clients.
        </p>
        <p class="text-gray-700 leading-relaxed">
            Notre mission est de simplifier vos projets en vous offrant une expérience d'achat fluide et en vous garantissant l'accès à tout ce dont vous avez besoin pour réussir, du petit bricolage aux grands chantiers.
        </p>
    </section>

    <section class="cta bg-blue-700 text-white rounded-lg shadow-lg p-8 text-center">
        <h2 class="text-3xl font-bold mb-4">Prêt à commencer votre projet ?</h2>
        <p class="text-lg mb-6">Explorez notre catalogue ou visitez-nous en magasin dès aujourd'hui !</p>
        <a href="/public/localisation.php" class="bg-white text-blue-700 hover:bg-blue-100 font-semibold py-3 px-8 rounded-full shadow-md transition duration-300 transform hover:scale-105">Trouvez un magasin</a>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
