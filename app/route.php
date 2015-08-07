<?php
$router->addRoute('GET', '/', 'App\Controllers\APIController::home');
$router->addRoute('GET', '/es/contracts/summary', 'App\Controllers\APIController::getSummary');
$router->addRoute('GET', '/es/contract/{id}/text', 'App\Controllers\APIController::getTextPages');
$router->addRoute('GET', '/es/contract/{id}/annotations', 'App\Controllers\APIController::getAnnotationPages');
$router->addRoute('GET', '/es/contract/{id}/metadata', 'App\Controllers\APIController::getMetadata');
$router->addRoute('GET', '/es/contracts', 'App\Controllers\APIController::getAllContract');
$router->addRoute('GET', '/es/contracts/count', 'App\Controllers\APIController::getAllContractCount');
$router->addRoute('GET', '/es/contract/{id}/searchtext', 'App\Controllers\APIController::pdfSearch');
$router->addRoute('GET', '/es/contracts/search', 'App\Controllers\APIController::fullTextSearch');

