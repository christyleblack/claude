<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', static fn () => redirect()->to('articles'));

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
