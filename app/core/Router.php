<?php
/**
 * Router.php
 * Minimal query-param router: index.php?page=xxx
 * Maps a page key to [ControllerClass, method].
 */

class Router
{
    private static array $routes = [
        'login'     => ['AuthController', 'showLogin'],
        'do_login'  => ['AuthController', 'login'],
        'logout'    => ['AuthController', 'logout'],

        'dashboard' => ['DashboardController', 'index'],      // admin home
        'home'      => ['DashboardController', 'teknisiHome'], // teknisi home

        // Phase 4 -- Materials Module (Admin)
        'materials'             => ['MaterialController', 'index'],
        'material-form'         => ['MaterialController', 'form'],
        'material-save'         => ['MaterialController', 'save'],
        'material-serials'      => ['MaterialController', 'serials'],
        'material-serial-add'   => ['MaterialController', 'addSerial'],
        'material-stock-adjust' => ['MaterialController', 'adjustStock'],

        // Phase 5 -- Work Orders Module (Admin)
        'workorders'             => ['WorkOrderController', 'index'],
        'workorder-form'         => ['WorkOrderController', 'form'],
        'workorder-save'         => ['WorkOrderController', 'save'],
        'workorder-detail'       => ['WorkOrderController', 'detail'],
        'workorder-toggle-status' => ['WorkOrderController', 'toggleStatus'],

        // Phase 6 -- Usage Logging (Teknisi core flow)
        'log-usage'      => ['UsageLogController', 'form'],
        'usage-log-save' => ['UsageLogController', 'save'],

        // Phase 7 -- Edit/Soft-Delete & Stock Correction
        'usage-log-edit'   => ['UsageLogController', 'editForm'],
        'usage-log-update' => ['UsageLogController', 'update'],
        'usage-log-delete' => ['UsageLogController', 'delete'],

        // Phase 8 -- History & Logs Views
        'history' => ['UsageLogController', 'history'],
        'logs'    => ['UsageLogController', 'adminLogs'],

        // Phase 9 -- Admin Dashboard & Audit Log
        'audit-log' => ['AuditController', 'index'],
    ];

    public static function dispatch(): void
    {
        $page = $_GET['page'] ?? '';

        if ($page === '') {
            self::redirectToLanding();
            return;
        }

        if (!isset(self::$routes[$page])) {
            self::notFound();
            return;
        }

        [$controllerName, $method] = self::$routes[$page];
        $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';

        if (!file_exists($controllerFile)) {
            die("Controller not found: {$controllerName}");
        }

        require_once $controllerFile;
        $controller = new $controllerName();
        $controller->$method();
    }

    private static function redirectToLanding(): void
    {
        if (Auth::check()) {
            $role = Auth::user()['role'];
            header('Location: index.php?page=' . ($role === 'admin' ? 'dashboard' : 'home'));
        } else {
            header('Location: index.php?page=login');
        }
        exit;
    }

    private static function notFound(): void
    {
        http_response_code(404);
        $pageTitle = 'Not Found';
        require __DIR__ . '/../views/partials/header.php';
        echo '<div class="container"><p>Page not found.</p></div>';
        require __DIR__ . '/../views/partials/footer.php';
    }
}