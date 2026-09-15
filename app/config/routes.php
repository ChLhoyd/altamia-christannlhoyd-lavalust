<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$router->match(
    '/auth/login',
    'AuthController::login',
    ['GET', 'POST']
);

$router->get(
    '/auth/logout',
    'AuthController::logout'
);


/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

$router->get(
    '/',
    'ProductController::index'
);

$router->get(
    '/products',
    'ProductController::index'
);

$router->match(
    '/products/create',
    'ProductController::create',
    ['GET', 'POST']
);

$router->match(
    '/products/edit/{id}',
    'ProductController::edit',
    ['GET', 'POST']
)->where_number('id');

$router->get(
    '/products/delete/{id}',
    'ProductController::delete'
)->where_number('id');
