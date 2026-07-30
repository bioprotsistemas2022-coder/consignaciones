<?php
$pdo = new PDO('mysql:host=localhost;dbname=bioprot;charset=utf8', 'bioprot', 'B1oPr0t765', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
