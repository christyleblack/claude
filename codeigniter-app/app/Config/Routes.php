<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', static fn () => redirect()->to('organisation/entites'));

// Connexion, inscription, déconnexion (CodeIgniter Shield)
service('auth')->routes($routes);

// Lecture publique
$routes->get('articles', 'Articles::index');
$routes->get('articles/(:num)', 'Articles::show/$1');

// Écriture réservée aux utilisateurs connectés
$routes->group('', ['filter' => 'session'], static function ($routes) {
    $routes->get('articles/new', 'Articles::new');
    $routes->post('articles', 'Articles::create');
    $routes->get('articles/(:num)/edit', 'Articles::edit/$1');
    $routes->post('articles/(:num)', 'Articles::update/$1');
    $routes->post('articles/(:num)/delete', 'Articles::delete/$1');
});

$routes->group('api', static function ($routes) {
    $routes->get('articles', 'Api\Articles::index');
    // Création via l'API : jeton d'accès Shield requis (en-tête Authorization: Bearer <jeton>)
    $routes->post('articles', 'Api\Articles::create', ['filter' => 'tokens']);
});

// Module Organisation (lot 1) : consultation pour tout utilisateur connecté,
// modification réservée à la permission organisation.gerer (vérifiée aussi sur l'entité concernée)
$routes->group('organisation', ['filter' => 'session', 'namespace' => 'App\Controllers\Organisation'], static function ($routes) {
    $routes->get('/', static fn () => redirect()->to('organisation/entites'));
    $routes->get('entites', 'Entites::index');
    $routes->get('entites/(:num)', 'Entites::show/$1');
    $routes->get('forces', 'Forces::index');
    $routes->get('types', 'TypesEntite::index');
    $routes->get('sites/(:num)', 'Sites::show/$1');

    $routes->group('', ['filter' => 'droit:organisation.gerer'], static function ($routes) {
        foreach (['entites' => 'Entites', 'forces' => 'Forces', 'types' => 'TypesEntite', 'sites' => 'Sites', 'emplacements' => 'Emplacements'] as $chemin => $controleur) {
            $routes->get("{$chemin}/new", "{$controleur}::new");
            $routes->post($chemin, "{$controleur}::create");
            $routes->get("{$chemin}/(:num)/edit", "{$controleur}::edit/$1");
            $routes->post("{$chemin}/(:num)", "{$controleur}::update/$1");
        }
    });
});

// Administration (section 4) : chaque partie exige sa permission ; « Mes droits » est ouvert à tout utilisateur connecté
$routes->group('administration', ['filter' => 'session', 'namespace' => 'App\Controllers\Administration'], static function ($routes) {
    $routes->get('mes-droits', 'Delegations::index');
    $routes->get('delegations/new', 'Delegations::new');
    $routes->post('delegations', 'Delegations::create');
    $routes->post('delegations/(:num)/revoquer', 'Delegations::revoquer/$1');

    $routes->group('', ['filter' => 'droit:administration.utilisateurs'], static function ($routes) {
        $routes->get('utilisateurs', 'Utilisateurs::index');
        $routes->get('utilisateurs/(:num)', 'Utilisateurs::show/$1');
        $routes->post('utilisateurs/(:num)/affectations', 'Utilisateurs::affecter/$1');
        $routes->post('affectations/(:num)/revoquer', 'Utilisateurs::revoquer/$1');
    });

    $routes->group('', ['filter' => 'droit:administration.roles'], static function ($routes) {
        $routes->get('roles', 'Roles::index');
        $routes->get('roles/new', 'Roles::new');
        $routes->post('roles', 'Roles::create');
        $routes->get('roles/(:num)/edit', 'Roles::edit/$1');
        $routes->post('roles/(:num)', 'Roles::update/$1');
    });

    $routes->get('journal', 'Journal::index', ['filter' => 'droit:audit.consulter']);
});

// Référentiel du matériel (lot 1) : consultation par tout utilisateur connecté ;
// écriture vérifiée par filière ou par force dans les contrôleurs et dans la base
$routes->group('referentiel', ['filter' => 'session', 'namespace' => 'App\Controllers\Referentiel'], static function ($routes) {
    $routes->get('/', static fn () => redirect()->to('referentiel/articles'));
    $routes->get('articles', 'Articles::index');
    $routes->get('articles/(:num)', 'Articles::show/$1');
    $routes->get('filieres', 'Filieres::index');
    $routes->get('filieres/(:num)/categories', 'Categories::index/$1');

    $routes->group('', ['filter' => 'droit:referentiel.gerer'], static function ($routes) {
        foreach (['articles' => 'Articles', 'filieres' => 'Filieres', 'categories' => 'Categories'] as $chemin => $controleur) {
            $routes->get("{$chemin}/new", "{$controleur}::new");
            $routes->post($chemin, "{$controleur}::create");
            $routes->get("{$chemin}/(:num)/edit", "{$controleur}::edit/$1");
            $routes->post("{$chemin}/(:num)", "{$controleur}::update/$1");
        }
    });

    $routes->group('', ['filter' => 'droit:referentiel.fiche_force'], static function ($routes) {
        $routes->get('fiches/new', 'FichesForce::new');
        $routes->post('fiches', 'FichesForce::create');
        $routes->get('fiches/(:num)/edit', 'FichesForce::edit/$1');
        $routes->post('fiches/(:num)', 'FichesForce::update/$1');
    });
});

// Stocks et mouvements (lot 1) : consultation dans le périmètre ; saisie et annulation avec
// stocks.mouvementer sur l'entité responsable, vérifiée par la base
$routes->group('stocks', ['filter' => 'session', 'namespace' => 'App\Controllers\Stocks'], static function ($routes) {
    $routes->get('/', 'Stocks::index');
    $routes->get('mouvements', 'Mouvements::index');
    $routes->get('mouvements/(:num)', 'Mouvements::show/$1');
    $routes->get('materiels/(:num)', 'Materiels::show/$1');
    $routes->get('bons', 'Bons::index');
    $routes->get('bons/(:num).pdf', 'Bons::pdf/$1');

    $routes->group('', ['filter' => 'droit:stocks.mouvementer'], static function ($routes) {
        $routes->get('mouvements/new', 'Mouvements::new');
        $routes->post('mouvements', 'Mouvements::create');
        $routes->post('mouvements/(:num)/annuler', 'Mouvements::annuler/$1');
        $routes->post('materiels/(:num)/etat', 'Materiels::etat/$1');
    });
});

// Inventaires (lot 1) : les droits, l'ordre des étapes et la séparation des tâches sont
// vérifiés par les fonctions de la base appelées par le contrôleur
$routes->group('inventaires', ['filter' => 'session', 'namespace' => 'App\Controllers\Inventaires'], static function ($routes) {
    $routes->get('/', 'Inventaires::index');
    $routes->get('new', 'Inventaires::new', ['filter' => 'droit:inventaires.gerer']);
    $routes->post('/', 'Inventaires::create', ['filter' => 'droit:inventaires.gerer']);
    $routes->get('(:num)', 'Inventaires::show/$1');
    foreach (['lancer', 'terminer', 'signer', 'valider', 'annuler'] as $action) {
        $routes->post("(:num)/{$action}", "Inventaires::{$action}/$1");
    }
    $routes->post('(:num)/comptage', 'Inventaires::compter/$1');
    $routes->post('(:num)/lignes', 'Inventaires::ajouterLigne/$1');
});

// Demandes et validations (lot 2) : états, circuits, séparation des tâches et service sont
// contrôlés par les fonctions de la base appelées par les contrôleurs
$routes->group('demandes', ['filter' => 'session', 'namespace' => 'App\Controllers\Demandes'], static function ($routes) {
    $routes->group('circuits', ['filter' => 'droit:demandes.circuits'], static function ($routes) {
        $routes->get('/', 'Circuits::index');
        $routes->post('/', 'Circuits::create');
        $routes->post('(:num)', 'Circuits::update/$1');
        $routes->post('(:num)/etapes', 'Circuits::ajouterEtape/$1');
        $routes->post('(:num)/etapes/(:num)/supprimer', 'Circuits::supprimerEtape/$1/$2');
    });
    $routes->get('/', 'Demandes::index');
    $routes->get('new', 'Demandes::new', ['filter' => 'droit:demandes.saisir']);
    $routes->post('/', 'Demandes::create', ['filter' => 'droit:demandes.saisir']);
    $routes->get('(:num)', 'Demandes::show/$1');
    $routes->get('(:num)/edit', 'Demandes::edit/$1');
    $routes->post('(:num)', 'Demandes::update/$1');
    $routes->post('(:num)/lignes', 'Demandes::ajouterLigne/$1');
    $routes->post('(:num)/lignes/(:num)/supprimer', 'Demandes::supprimerLigne/$1/$2');
    foreach (['soumettre', 'decider', 'servir', 'cloturer', 'annuler'] as $action) {
        $routes->post("(:num)/{$action}", "Demandes::{$action}/$1");
    }
});
$routes->group('notifications', ['filter' => 'session', 'namespace' => 'App\Controllers\Demandes'], static function ($routes) {
    $routes->get('/', 'Notifications::index');
    $routes->post('tout-lire', 'Notifications::toutLire');
    $routes->post('(:num)/lire', 'Notifications::lire/$1');
});

