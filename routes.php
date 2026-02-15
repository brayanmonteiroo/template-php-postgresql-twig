<?php

use FastRoute\RouteCollector;

return \FastRoute\simpleDispatcher(function (RouteCollector $r) {
    $r->addRoute('GET', '/login', 'AuthController@showLogin');
    $r->addRoute('POST', '/login', 'AuthController@login');
    $r->addRoute('POST', '/logout', 'AuthController@logout');

    $r->addRoute('GET', '/', 'auth:DashboardController@index');
    $r->addRoute('GET', '/dashboard', 'auth:DashboardController@index');

    $r->addRoute('GET', '/users', 'auth:UserController@index:user.view');
    $r->addRoute('GET', '/users/create', 'auth:UserController@create:user.create');
    $r->addRoute('POST', '/users', 'auth:UserController@store:user.create');
    $r->addRoute('GET', '/users/{id:\d+}', 'auth:UserController@show:user.view');
    $r->addRoute('GET', '/users/{id:\d+}/edit', 'auth:UserController@edit:user.edit');
    $r->addRoute('POST', '/users/{id:\d+}', 'auth:UserController@update:user.edit');
    $r->addRoute('POST', '/users/{id:\d+}/delete', 'auth:UserController@destroy:user.delete');
});
