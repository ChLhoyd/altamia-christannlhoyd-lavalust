<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$router->match('/auth/login','AuthController::login',['GET', 'POST']);
$router->get('/auth/logout','AuthController::logout');
$router->get('/','ProductController::index');
$router->get('/products','ProductController::index');
$router->match('/products/create','ProductController::create',['GET', 'POST']);
$router->match('/products/edit/{id}','ProductController::edit',['GET', 'POST'])->where_number('id');
$router->get('/products/delete/{id}','ProductController::delete')->where_number('id');

// LAB 6 API ROUTES

$router->match(
    '/api/login',
    'ProductApiController::login',
    ['POST', 'OPTIONS']
);

$router->match(
    '/api/logout',
    'ProductApiController::logout',
    ['POST', 'OPTIONS']
);

$router->match(
    '/api/products',
    'ProductApiController::index',
    ['GET', 'OPTIONS']
);

$router->match(
    '/api/products',
    'ProductApiController::store',
    ['POST', 'OPTIONS']
);

$router->match(
    '/api/products/{id}',
    'ProductApiController::show',
    ['GET', 'OPTIONS']
)->where_number('id');

$router->match(
    '/api/products/{id}',
    'ProductApiController::update',
    ['PUT', 'PATCH', 'OPTIONS']
)->where_number('id');

$router->match(
    '/api/products/{id}',
    'ProductApiController::delete',
    ['DELETE', 'OPTIONS']
)->where_number('id');

$router->match(
    '/api/refresh',
    'ProductApiController::refresh',
    ['POST', 'OPTIONS']
);
