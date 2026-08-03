<?php
/**
 * DashboardController.php
 * Phase 2 -- teknisi home placeholder, replaced Phase 6.
 * Phase 9 -- admin dashboard: stock overview cards (aggregate stat
 * cards + per-category stock breakdown), low-stock highlighting
 * (reusing the same badge-lowstock pattern from teknisi Home), a
 * recent activity feed pulled from audit_log, and a link to the full
 * Audit Log page.
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/WorkOrder.php';
require_once __DIR__ . '/../models/Material.php';
require_once __DIR__ . '/../models/AuditLog.php';

class DashboardController
{
    public function index(): void
    {
        Auth::requireRole('admin');

        $user = Auth::user();
        $pageTitle = 'Dashboard';

        $totalMaterialTypes = Material::countAll();
        $openWoCount = WorkOrder::countByStatus('open');
        $lowStockMaterials = Material::getLowStock();
        $stockByCategory = Material::getStockByCategory();
        $recentActivity = AuditLog::getRecent(8);

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/dashboard.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function teknisiHome(): void
    {
        Auth::requireRole('teknisi');

        $user = Auth::user();
        $pageTitle = 'Home';
        $assignedWOs = WorkOrder::getAssignedOpen($user['id']);
        $lowStockMaterials = Material::getLowStock();

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/teknisi/home.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }
}