<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoload
use MongoDB\Client;
$MONGO_URI = 'mongodb://localhost:27017'; 
$MONGO_DB_NAME = 'PhonePulse';         

$mongoClient = new Client($MONGO_URI);

$mongoDB = $mongoClient->selectDatabase($MONGO_DB_NAME);
$mongo = $mongoClient->selectDatabase($MONGO_DB_NAME);
$settings = $mongoDB->settings->findOne([]) ?? [];
