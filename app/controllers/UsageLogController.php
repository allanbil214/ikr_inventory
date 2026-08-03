<?php
/**
 * UsageLogController.php
 * Phase 6 -- Usage Logging (teknisi core flow). See handoff doc
 * Section 6 (teknisi screen 3) and Section 8 Phase 6.
 *
 * Phase 7 -- adds edit/soft-delete for usage_logs. The full filterable
 * History/Logs screens are still Phase 8 scope; in the meantime, admin
 * edits/deletes from the existing WO detail page's "Material Terpakai"
 * list, and teknisi from a small "log terbaru" panel added to this
 * Log Usage screen (per selected WO) -- both routed through the
 * editForm()/update()/delete() actions below.
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/WorkOrder.php';
require_once __DIR__ . '/../models/Material.php';
require_once __DIR__ . '/../models/MaterialSerial.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/UsageLog.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

class UsageLogController
{
    public function form(): void
    {
        Auth::requireRole('teknisi');

        $user = Auth::user();
        $assignedWOs = WorkOrder::getAssignedOpen($user['id']);
        foreach ($assignedWOs as &$wo) {
            $wo['logs'] = UsageLog::getByWorkOrder((int) $wo['id']);
        }
        unset($wo);

        $categories = Category::getAll();
        $materials = $this->getMaterialsForPicker();
        $flash = $this->consumeFlash('log_usage_flash');

        $pageTitle = 'Log Usage';

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/teknisi/log-usage.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Multi-log UX pass: logs every material checked on the form in one
     * submit, instead of the old one-material-per-POST radio flow.
     * Everything is pre-validated before any write happens, so a bad
     * row (missing qty, no SN chosen) blocks the whole batch with a
     * clear message rather than silently dropping it. Once writes
     * start, each material is still its own transaction (see
     * UsageLog::create()) -- a rare mid-batch failure (e.g. someone
     * else claims the same SN a second earlier) only affects that one
     * item; everything already written stays logged, and the response
     * reports exactly which item(s) didn't go through so the
     * technician isn't stuck resubmitting materials that already succeeded.
     */
    public function save(): void
    {
        Auth::requireRole('teknisi');

        $user = Auth::user();
        $woId = (int) ($_POST['wo_id'] ?? 0);
        $materialIds = array_map('intval', $_POST['material_ids'] ?? []);
        $qtyInputs = $_POST['qty_used'] ?? [];
        $serialInputs = $_POST['serial_id'] ?? [];

        $errors = [];

        // Re-verify server-side that this WO is actually this teknisi's
        // own open WO -- never trust the submitted wo_id alone.
        $workOrder = WorkOrder::find($woId);
        if (!$workOrder || (int) $workOrder['technician_id'] !== (int) $user['id'] || $workOrder['status'] !== 'open') {
            $errors[] = 'Work Order tidak valid atau bukan WO open milik kamu.';
        }

        if (empty($materialIds)) {
            $errors[] = 'Pilih minimal satu material yang digunakan.';
        }

        $items = [];
        foreach ($materialIds as $materialId) {
            $material = Material::find($materialId);
            if (!$material) {
                $errors[] = "Material #{$materialId} tidak ditemukan.";
                continue;
            }

            if ($material['tracking_type'] === 'serial') {
                $serialId = isset($serialInputs[$materialId]) && $serialInputs[$materialId] !== ''
                    ? (int) $serialInputs[$materialId]
                    : null;
                if (!$serialId) {
                    $errors[] = "{$material['description']}: pilih SN yang digunakan.";
                    continue;
                }
                $items[] = [
                    'material_id' => $materialId,
                    'serial_id'   => $serialId,
                    'qty_used'    => null,
                    'label'       => $material['description'],
                ];
            } else {
                $qty = $qtyInputs[$materialId] ?? null;
                if (!is_numeric($qty) || (float) $qty <= 0) {
                    $errors[] = "{$material['description']}: jumlah yang digunakan harus berupa angka lebih dari 0.";
                    continue;
                }
                $items[] = [
                    'material_id' => $materialId,
                    'serial_id'   => null,
                    'qty_used'    => $qty,
                    'label'       => $material['description'],
                ];
            }
        }

        if (!empty($errors)) {
            $_SESSION['log_usage_flash'] = ['errors' => $errors];
            header('Location: index.php?page=log-usage');
            exit;
        }

        $successCount = 0;
        $writeErrors = [];

        foreach ($items as $item) {
            try {
                $newLogId = UsageLog::create([
                    'wo_id'         => $woId,
                    'technician_id' => $user['id'],
                    'material_id'   => $item['material_id'],
                    'serial_id'     => $item['serial_id'],
                    'qty_used'      => $item['qty_used'],
                ]);
                $after = UsageLog::find($newLogId);
                AuditLog::record((int) $user['id'], 'create', 'usage_logs', $newLogId, null, $after);
                $successCount++;
            } catch (RuntimeException $e) {
                // Stock/SN no longer available -- message is already user-safe.
                $writeErrors[] = "{$item['label']}: {$e->getMessage()}";
            } catch (InvalidArgumentException $e) {
                $writeErrors[] = "{$item['label']}: material tidak ditemukan.";
            }
        }

        if ($successCount > 0 && empty($writeErrors)) {
            $_SESSION['log_usage_flash'] = ['success' => "{$successCount} material berhasil dicatat."];
        } elseif ($successCount > 0) {
            $_SESSION['log_usage_flash'] = [
                'success' => "{$successCount} material berhasil dicatat.",
                'errors'  => $writeErrors,
            ];
        } else {
            $_SESSION['log_usage_flash'] = ['errors' => $writeErrors];
        }

        header('Location: index.php?page=log-usage');
        exit;
    }

    /**
     * Phase 8 -- Teknisi History: own logs across ALL of their WOs (open
     * + completed, unlike the Phase 7 mini-panel which only covers open
     * ones), filterable by WO/category/search/date range, with an
     * optional "show deleted" toggle. Edit/delete on each row is still
     * gated the normal way (authorize() / the view only renders the
     * buttons when appropriate) -- completed-WO logs render read-only.
     */
    public function history(): void
    {
        Auth::requireRole('teknisi');

        $user = Auth::user();
        $filters = $this->buildFilters($_GET);
        $filters['technician_id'] = $user['id'];

        $logs = UsageLog::getFiltered($filters);
        $workOrders = WorkOrder::getAll(null, (int) $user['id']);
        $categories = Category::getAll();
        $showDeleted = !empty($_GET['show_deleted']);
        $flash = $this->consumeFlash('history_flash');

        $pageTitle = 'History';

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/teknisi/history.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Phase 8 -- Admin Logs: all technicians' usage logs, filterable by
     * technician/WO/category/search/date range, with the same "show
     * deleted" toggle as History. Admin can edit/delete any active log
     * regardless of WO status (per authorize()).
     */
    public function adminLogs(): void
    {
        Auth::requireRole('admin');

        $user = Auth::user();
        $filters = $this->buildFilters($_GET);
        if (!empty($_GET['technician'])) {
            $filters['technician_id'] = (int) $_GET['technician'];
        }

        $logs = UsageLog::getFiltered($filters);
        $teknisiList = User::getAllTeknisi();
        $workOrders = WorkOrder::getAll();
        $categories = Category::getAll();
        $showDeleted = !empty($_GET['show_deleted']);
        $flash = $this->consumeFlash('admin_logs_flash');

        $pageTitle = 'Logs';

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/logs/list.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    /**
     * Shared GET-param parsing for history()/adminLogs() -- technician_id
     * is deliberately NOT read here since its source differs (forced to
     * the logged-in teknisi in history(), optional admin-picked filter
     * in adminLogs()).
     */
    private function buildFilters(array $get): array
    {
        $search = trim($get['q'] ?? '');

        return [
            'wo_id'           => !empty($get['wo']) ? (int) $get['wo'] : null,
            'category_id'     => !empty($get['category']) ? (int) $get['category'] : null,
            'search'          => $search !== '' ? $search : null,
            'date_from'       => !empty($get['date_from']) ? $get['date_from'] : null,
            'date_to'         => !empty($get['date_to']) ? $get['date_to'] : null,
            'include_deleted' => !empty($get['show_deleted']),
        ];
    }

    /**
     * Phase 7 -- shows the edit form for a single log. Admin can edit
     * any log; teknisi only their own, and only while the log's WO is
     * still 'open' (admin isn't held to that restriction).
     */
    public function editForm(): void
    {
        Auth::requireLogin();

        $id = (int) ($_GET['id'] ?? 0);
        $log = UsageLog::find($id);
        $returnTo = $this->sanitizeReturnTo($_GET['return_to'] ?? '');

        if (!$log || (int) $log['is_deleted'] === 1) {
            $this->redirectBack($returnTo, (int) ($log['wo_id'] ?? 0));
            return;
        }

        $denyReason = $this->authorize($log);
        if ($denyReason !== null) {
            $this->setFlash($returnTo, ['errors' => [$denyReason]]);
            $this->redirectBack($returnTo, (int) $log['wo_id']);
            return;
        }

        $selectableSerials = $log['tracking_type'] === 'serial'
            ? MaterialSerial::getSelectableForEdit((int) $log['material_id'], (int) $log['id'])
            : [];

        $flash = $this->consumeFlash('usage_log_edit_flash');
        $user = Auth::user();
        $pageTitle = 'Edit Log Usage';

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/usage-logs/edit.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function update(): void
    {
        Auth::requireLogin();

        $id = (int) ($_POST['id'] ?? 0);
        $returnTo = $this->sanitizeReturnTo($_POST['return_to'] ?? '');
        $log = UsageLog::find($id);

        if (!$log || (int) $log['is_deleted'] === 1) {
            $this->redirectBack($returnTo, (int) ($log['wo_id'] ?? 0));
            return;
        }

        $denyReason = $this->authorize($log);
        if ($denyReason !== null) {
            $this->setFlash($returnTo, ['errors' => [$denyReason]]);
            $this->redirectBack($returnTo, (int) $log['wo_id']);
            return;
        }

        try {
            if ($log['tracking_type'] === 'serial') {
                $newSerialId = (int) ($_POST['serial_id'] ?? 0);
                if ($newSerialId <= 0) {
                    throw new RuntimeException('Pilih SN yang digunakan.');
                }
                UsageLog::updateSerial($id, $newSerialId);
            } else {
                $newQty = $_POST['qty_used'] ?? null;
                if (!is_numeric($newQty)) {
                    throw new RuntimeException('Jumlah yang digunakan harus berupa angka lebih dari 0.');
                }
                UsageLog::updateQuantity($id, (float) $newQty);
            }

            $after = UsageLog::find($id);
            AuditLog::record((int) Auth::user()['id'], 'update', 'usage_logs', $id, $log, $after);

            $this->setFlash($returnTo, ['success' => 'Log usage berhasil diperbarui.']);
        } catch (RuntimeException $e) {
            $_SESSION['usage_log_edit_flash'] = ['errors' => [$e->getMessage()]];
            header('Location: index.php?page=usage-log-edit&id=' . $id . '&return_to=' . $returnTo);
            exit;
        }

        $this->redirectBack($returnTo, (int) $log['wo_id']);
    }

    public function delete(): void
    {
        Auth::requireLogin();

        $id = (int) ($_POST['id'] ?? 0);
        $returnTo = $this->sanitizeReturnTo($_POST['return_to'] ?? '');
        $log = UsageLog::find($id);

        if (!$log || (int) $log['is_deleted'] === 1) {
            $this->redirectBack($returnTo, (int) ($log['wo_id'] ?? 0));
            return;
        }

        $denyReason = $this->authorize($log);
        if ($denyReason !== null) {
            $this->setFlash($returnTo, ['errors' => [$denyReason]]);
            $this->redirectBack($returnTo, (int) $log['wo_id']);
            return;
        }

        UsageLog::delete($id);

        // old_value = the log as it was right before deletion (still has
        // is_deleted = 0 here); new_value left null -- there's no
        // meaningful "after" state for a delete beyond is_deleted flipping.
        AuditLog::record((int) Auth::user()['id'], 'delete', 'usage_logs', $id, $log, null);

        $this->setFlash($returnTo, ['success' => 'Log usage berhasil dihapus, stok dikembalikan.']);
        $this->redirectBack($returnTo, (int) $log['wo_id']);
    }

    /**
     * Admin can edit/delete any log. Teknisi can only edit/delete their
     * own logs, and only while the log's WO is still 'open' -- matches
     * the same restriction already enforced when creating a log.
     *
     * @return string|null a user-safe denial message, or null if allowed
     */
    private function authorize(array $log): ?string
    {
        $user = Auth::user();

        if ($user['role'] === 'admin') {
            return null;
        }

        if ((int) $log['technician_id'] !== (int) $user['id']) {
            return 'Log ini bukan milik kamu.';
        }

        if ($log['wo_status'] !== 'open') {
            return 'Work Order ini sudah selesai. Hubungi admin untuk koreksi.';
        }

        return null;
    }

    /**
     * Known return targets -- whitelist rather than trusting the raw
     * query/post value. 'history' and 'logs' added in Phase 8 alongside
     * the existing 'workorder-detail' (admin WO detail) and default
     * 'log-usage' (teknisi Log Usage mini-panel) targets.
     */
    private function sanitizeReturnTo(string $value): string
    {
        $valid = ['workorder-detail', 'history', 'logs'];
        return in_array($value, $valid, true) ? $value : 'log-usage';
    }

    /**
     * Writes to whichever flash key the actual redirect target reads --
     * 'wo_detail_flash' (WorkOrderController::detail), 'history_flash'
     * (this controller's history()), 'admin_logs_flash' (this
     * controller's adminLogs()), or 'log_usage_flash' (this controller's
     * form()) as the default.
     */
    private function setFlash(string $returnTo, array $flash): void
    {
        if ($returnTo === 'workorder-detail') {
            $key = 'wo_detail_flash';
        } elseif ($returnTo === 'history') {
            $key = 'history_flash';
        } elseif ($returnTo === 'logs') {
            $key = 'admin_logs_flash';
        } else {
            $key = 'log_usage_flash';
        }

        $_SESSION[$key] = $flash;
    }

    private function redirectBack(string $returnTo, int $woId): void
    {
        if ($returnTo === 'workorder-detail' && $woId > 0) {
            $redirect = 'index.php?page=workorder-detail&id=' . $woId;
        } elseif ($returnTo === 'history') {
            $redirect = 'index.php?page=history';
        } elseif ($returnTo === 'logs') {
            $redirect = 'index.php?page=logs';
        } else {
            $redirect = 'index.php?page=log-usage';
        }

        header('Location: ' . $redirect);
        exit;
    }

    /**
     * All materials, each annotated with its available SNs (serial-
     * tracked only) so the Log Usage view can render the full picker
     * without extra round-trips. Small seed dataset makes the N+1
     * query here a reasonable tradeoff for this mockup's scope.
     */
    private function getMaterialsForPicker(): array
    {
        $materials = Material::getAll();

        foreach ($materials as &$material) {
            $material['available_serials'] = $material['tracking_type'] === 'serial'
                ? MaterialSerial::getAvailableByMaterial((int) $material['id'])
                : [];
        }

        return $materials;
    }

    private function consumeFlash(string $key): ?array
    {
        if (!isset($_SESSION[$key])) {
            return null;
        }
        $flash = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $flash;
    }
}
