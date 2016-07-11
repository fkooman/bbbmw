<?php
$db = "sqlite:" . dirname(__DIR__) . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "client.sqlite";
$pdo = new PDO($db);

$pdo->exec('
    CREATE TABLE IF NOT EXISTS `access_token` (
    `user_id` VARCHAR(255) NOT NULL,
    `access_token` VARCHAR(255) NOT NULL,
    UNIQUE(`user_id`))
');
