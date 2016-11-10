<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
date_default_timezone_set('Europe/Moscow');
require_once __DIR__.'/../vendor/autoload.php';

require_once __DIR__.'/../app/controllers/UserController.php';
require_once __DIR__.'/../app/controllers/SearchController.php';
require_once __DIR__.'/../app/controllers/ClientController.php';
require_once __DIR__.'/../app/controllers/EditController.php';
require_once __DIR__.'/../app/controllers/DeclarationsController.php';


$app = new Silex\Application();
$app->register(new Silex\Provider\SessionServiceProvider());

$app['debug'] = true;

$validate = function(Symfony\Component\HttpFoundation\Request $request, Silex\Application $app){
    $uc = new UserController();
    $uc->logRequest($request);
    return $uc->validate($request, $app);
};

$logRequest2 = function(Symfony\Component\HttpFoundation\Request $request){
    $uc = new UserController();
    return $uc->logRequest2($request);
};


$app->get('/user/me', 'UserController::me');

$app->get('/user/auth', 'UserController::auth')->before($logRequest2);
$app->post('/user/auth', 'UserController::auth')->before($logRequest2);

$app->get('/user/push', 'UserController::push');
$app->get('/user/devices', 'UserController::viewDeviceIds');

$app->get('/user/pushes', 'UserController::getPushes')->before($validate);
$app->post('/user/pushes', 'UserController::getPushes')->before($validate);

$app->post('/uploadDoc', 'EditController::uploadDocument');


$app->get('/user/unregister', 'UserController::unregister')->before($validate);
$app->post('/user/unregister', 'UserController::unregister')->before($validate);

$app->get('/user/available_tables', 'UserController::getAvailableTables')->before($validate);
$app->post('/user/available_tables', 'UserController::getAvailableTables')->before($validate);

$app->get('/search/dictionary_search', 'SearchController::getDictionaryEntities')->before($validate);
$app->post('/search/dictionary_search', 'SearchController::getDictionaryEntities')->before($validate);

$app->get('/search/dict', 'SearchController::getDictionaryRecords')->before($validate);
$app->post('/search/dict', 'SearchController::getDictionaryRecords')->before($validate);

$app->get('/search/bookmark_search', 'SearchController::bookmarkSearch')->before($validate);
$app->post('/search/bookmark_search', 'SearchController::bookmarkSearch')->before($validate);

$app->get('/remove_bookmark', 'EditController::deleteBookmark')->before($validate);
$app->post('/remove_bookmark', 'EditController::deleteBookmark')->before($validate);


$app->get('/user/available_fields', 'UserController::getAvailableFields')->before($validate);
$app->post('/user/available_fields', 'UserController::getAvailableFields')->before($validate);

$app->get('/edit', 'EditController::index')->before($validate);
$app->post('/edit', 'EditController::index')->before($validate);

$app->get('/add_bookmark', 'EditController::addBookmark')->before($validate);
$app->post('/add_bookmark', 'EditController::addBookmark')->before($validate);

$app->get('/edit_bookmark', 'EditController::editBookmark')->before($validate);
$app->post('/edit_bookmark', 'EditController::editBookmark')->before($validate);

$app->get('/edit_bookmark_order', 'EditController::editBookmarkOrder')->before($validate);
$app->post('/edit_bookmark_order', 'EditController::editBookmarkOrder')->before($validate);

$app->get('/user/check_auth', 'UserController::checkAuth')->before($validate);
$app->post('/user/check_auth', 'UserController::checkAuth')->before($validate);

$app->get('/user/request_fields', 'UserController::requestFields')->before($validate);
$app->post('/user/request_fields', 'UserController::requestFields')->before($validate);

$app->get('/search', 'SearchController::index')->before($validate);
$app->post('/search', 'SearchController::index')->before($validate);

$app->get('/search/record', 'SearchController::findById')->before($validate);
$app->post('/search/record', 'SearchController::findById')->before($validate);

$app->get('/user/custom_bookmark_settings', 'UserController::addCustomBookmarkSettings')->before($validate);
$app->post('/user/custom_bookmark_settings', 'UserController::addCustomBookmarkSettings')->before($validate);

$app->get('/user/check_bookmark_settings', 'UserController::checkStatusFields')->before($validate);
$app->post('/user/check_bookmark_settings', 'UserController::checkStatusFields')->before($validate);

$app->get('/declarations/record', 'DeclarationsController::findByID')->before($validate);
$app->post('/declarations/record', 'DeclarationsController::findByID')->before($validate);


$app->get('/declarations', 'DeclarationsController::index')->before($validate);
$app->post('/declarations', 'DeclarationsController::index')->before($validate);

$app->get('/declarations/edit', 'DeclarationsController::editDeclarations')->before($validate);
$app->post('/declarations/edit', 'DeclarationsController::editDeclarations')->before($validate);

$app->get('/search/autocomplete', 'SearchController::autocomplete')->before($validate);
$app->post('/search/autocomplete', 'SearchController::autocomplete')->before($validate);


$app->get('/test', 'SearchController::testBookmarks');
$app->post('/test', 'SearchController::testBookmarks');

$app->get('/clients', 'ClientController::index')->before($validate);
$app->post('/clients', 'ClientController::index')->before($validate);

$app->get('/partners', 'ClientController::customerIndex')->before($validate);
$app->post('/partners', 'ClientController::customerIndex')->before($validate);



$app->run();
