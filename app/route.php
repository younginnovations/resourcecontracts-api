<?php
$router->addRoute('GET', '/', 'App\Controllers\APIController::home');
$router->addRoute('GET', '/contracts/summary', 'App\Controllers\APIController::getSummary');
$router->addRoute('GET', '/contract/{id}/text', 'App\Controllers\APIController::getTextPages');
$router->addRoute('GET', '/contract/{id}/annotations', 'App\Controllers\APIController::getAnnotationPages');
$router->addRoute('GET', '/contract/{id}/annotations/search', 'App\Controllers\APIController::getAnnotationPages');
$router->addRoute('GET', '/contract/{id}/metadata', 'App\Controllers\APIController::getMetadata');
$router->addRoute('GET', '/contracts', 'App\Controllers\APIController::getAllContract');
$router->addRoute('GET', '/contracts/count', 'App\Controllers\APIController::getAllContractCount');
$router->addRoute('GET', '/contract/{id}/searchtext', 'App\Controllers\APIController::search');
$router->addRoute('GET', '/contracts/search', 'App\Controllers\APIController::fullTextSearch');

$router->addRoute('GET', '/annotation/{id}', 'App\Controllers\APIController::getAnnotationById');


//Internal API
$router->addRoute('GET', '/contract/{id}/annotations/download', 'App\Controllers\APIController::downloadAnnotationsAsCSV');
$router->addRoute('GET', '/contract/countries', 'App\Controllers\APIController::getCoutriesContracts');
$router->addRoute('GET', '/contract/resources', 'App\Controllers\APIController::getResourceContracts');
$router->addRoute('GET', '/contract/years', 'App\Controllers\APIController::getYearsContracts');
$router->addRoute('GET', '/contract/country/resource', 'App\Controllers\APIController::getContractByCountryAndResource');
$router->addRoute('GET', '/contract/attributes', 'App\Controllers\APIController::getFilterAttributes');
$router->addRoute('GET', '/contracts/annotations/category', 'App\Controllers\APIController::getAnnotationsCategory');
$router->addRoute('GET', '/contracts/metadata/download', 'App\Controllers\APIController::downloadMetadtaAsCSV');

