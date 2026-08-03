<?php
/**
 * partials/header.php
 * Expects optional $pageTitle in scope.
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' – IKR Inventory' : 'IKR Inventory' ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
<div class="app-shell">
