<?php
require_once 'muroute.php';
$muRoute = new MuRoute();

// Example of a simple authentication handler
$muRoute->setAuthHandler(function ($roles) {
    $roles = array_map('trim', explode(',', $roles));
    // if authorizaton header value is in the roles array, return true
    return in_array($_SERVER['HTTP_AUTHORIZATION'] ?? '', $roles, true);
});

$muRoute->run();
