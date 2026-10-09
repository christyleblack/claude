<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', static fn () => redirect()->to('articles'));

$routes->get('articles', 'Articles::index');
$routes->get('articles/new', 'Articles::new');
$routes->get('articles/(:num)', 'Articles::show/$1');
$routes->post('articles', 'Articles::create');
$routes->get('articles/(:num)/edit', 'Articles::edit/$1');
$routes->post('articles/(:num)', 'Articles::update/$1');
$routes->post('articles/(:num)/delete', 'Articles::delete/$1');

$routes->group('api', static function ($routes) {
    $routes->get('articles', 'Api\\Articles::index');
    $routes->post('articles', 'Api\\Articles::create');
});
