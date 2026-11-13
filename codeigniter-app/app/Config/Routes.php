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
// modification réservée à la permission organisation.gerer
$routes->group('organisation', ['filter' => 'session', 'namespace' => 'App\Controllers\Organisation'], static function ($routes) {
    $routes->get('/', static fn () => redirect()->to('organisation/entites'));
    $routes->get('entites', 'Entites::index');
    $routes->get('entites/(:num)', 'Entites::show/$1');
    $routes->get('forces', 'Forces::index');
    $routes->get('types', 'TypesEntite::index');
    $routes->get('sites/(:num)', 'Sites::show/$1');

    $routes->group('', ['filter' => 'permission:organisation.gerer'], static function ($routes) {
        foreach (['entites' => 'Entites', 'forces' => 'Forces', 'types' => 'TypesEntite', 'sites' => 'Sites', 'emplacements' => 'Emplacements'] as $chemin => $controleur) {
            $routes->get("{$chemin}/new", "{$controleur}::new");
            $routes->post($chemin, "{$controleur}::create");
            $routes->get("{$chemin}/(:num)/edit", "{$controleur}::edit/$1");
            $routes->post("{$chemin}/(:num)", "{$controleur}::update/$1");
        }
    });
});

