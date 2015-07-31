<?php
$router->addRoute('GET','/', 'App\Controllers\APIController::home');
$router->addRoute('GET','/es/contracts/summary', 'App\Controllers\APIController::getSummary');
//$router->addRoute('GET','/es/contracts/search', 'App\Controllers\APIController::search');
//$router->addRoute('GET','/es/contracts/filter', 'App\Controllers\APIController::filterContract');
$router->addRoute('GET','/es/contracts/{id}/text/{page_no}/page', 'App\Controllers\APIController::getTextPages');
$router->addRoute('GET','/es/contracts/{id}/page/{page_no}/annotations', 'App\Controllers\APIController::getAnnotationPages');
$router->addRoute('GET','/es/contracts/{id}/metadata', 'App\Controllers\APIController::getMetadata');
$router->addRoute('GET','/es/contracts/{id}/annotations', 'App\Controllers\APIController::getContractAnnotation');
$router->addRoute('GET','/es/contracts', 'App\Controllers\APIController::getAllContract');
$router->addRoute('GET','/es/contracts/count', 'App\Controllers\APIController::getAllContractCount');
$router->addRoute('GET','/es/contracts/pdfsearch', 'App\Controllers\APIController::pdfSearch');
$router->addRoute('GET','/es/contracts/fulltextsearch', 'App\Controllers\APIController::fullTextSearch');

