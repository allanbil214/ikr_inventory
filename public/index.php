<?php
/**
 * public/index.php
 * Single entry point for the IKR Inventory mockup.
 * Routes on ?page=xxx via Router.php.
 */

require_once __DIR__ . '/../app/core/helpers.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Router.php';

Router::dispatch();
