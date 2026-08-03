<?php
/**
 * AuditController.php
 * Phase 9 -- full Audit Log page (Section 6 admin screen 6), reached
 * via a link from the Dashboard rather than a bottom-navbar slot
 * (Section 5). Admin-only: this shows every user's actions, including
 * other technicians', so it isn't exposed to the teknisi role at all.
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/User.php';

class AuditController
{
    public function index(): void
    {
        Auth::requireRole('admin');

        $filters = [
            'table_name' => $_GET['table'] ?? null,
            'action'     => $_GET['action'] ?? null,
            'user_id'    => !empty($_GET['user']) ? (int) $_GET['user'] : null,
            'date_from'  => $_GET['date_from'] ?? null,
            'date_to'    => $_GET['date_to'] ?? null,
        ];

        $entries = AuditLog::getFiltered($filters);
        $allUsers = User::getAll();

        $user = Auth::user();
        $pageTitle = 'Audit Log';

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/audit/list.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }
}
