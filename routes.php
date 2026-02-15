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

    $r->addRoute('GET', '/roles', 'auth:RoleController@index:role.view');
    $r->addRoute('GET', '/roles/create', 'auth:RoleController@create:role.create');
    $r->addRoute('POST', '/roles', 'auth:RoleController@store:role.create');
    $r->addRoute('GET', '/roles/{id:\d+}/edit', 'auth:RoleController@edit:role.edit');
    $r->addRoute('POST', '/roles/{id:\d+}', 'auth:RoleController@update:role.edit');
    $r->addRoute('POST', '/roles/{id:\d+}/delete', 'auth:RoleController@destroy:role.delete');

    $r->addRoute('GET', '/permissions', 'auth:PermissionController@index:permission.view');
    $r->addRoute('POST', '/permissions', 'auth:PermissionController@update:permission.manage');

    $r->addRoute('GET', '/profile', 'auth:ProfileController@edit');
    $r->addRoute('POST', '/profile', 'auth:ProfileController@update');
});
