<?php
/**
 * WorkOrderController.php
 * Phase 5 -- Work Orders Module (Admin). See handoff doc Section 6
 * (Admin screen 5) and Section 8 Phase 5.
 *
 * "View materials logged per WO" (Section 6) is a read-only display
 * pulled straight from usage_logs -- the seed data already has usage
 * logs against WOs 1-5, so this is populated now even though the full
 * UsageLogController (create/edit/soft-delete) doesn't land until
 * Phase 6/7. No UsageLog model is introduced here to keep that scope
 * boundary clean; the query lives in this controller as a private helper.
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/WorkOrder.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/UsageLog.php';
require_once __DIR__ . '/../models/AuditLog.php';

class WorkOrderController
{
    public function index(): void
    {
        Auth::requireRole('admin');

        $status = $_GET['status'] ?? '';
        $technicianId = isset($_GET['technician']) && $_GET['technician'] !== ''
            ? (int) $_GET['technician']
            : null;

        $workOrders = WorkOrder::getAll($status !== '' ? $status : null, $technicianId);
        $teknisiList = User::getAllTeknisi();

        $user = Auth::user();
        $pageTitle = 'Work Orders';
        $flash = $this->consumeFlash('wo_list_flash');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/workorders/list.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function form(): void
    {
        Auth::requireRole('admin');

        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $workOrder = $id ? WorkOrder::find($id) : null;

        if ($id && !$workOrder) {
            header('Location: index.php?page=workorders');
            exit;
        }

        $teknisiList = User::getAllTeknisi();
        $flash = $this->consumeFlash('wo_form_flash');

        $user = Auth::user();
        $pageTitle = $workOrder ? 'Edit Work Order' : 'Tambah Work Order';

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/workorders/form.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function save(): void
    {
        Auth::requireRole('admin');

        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

        $woNo = trim($_POST['wo_no'] ?? '');
        $woDate = trim($_POST['wo_date'] ?? '');
        $technicianId = $_POST['technician_id'] ?? '';
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $portFat = trim($_POST['port_fat'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $errors = [];

        if ($woNo === '') {
            $errors[] = 'Nomor WO wajib diisi.';
        } else {
            $existing = WorkOrder::findByWoNo($woNo);
            if ($existing && (!$id || (int) $existing['id'] !== $id)) {
                $errors[] = "Nomor WO \"{$woNo}\" sudah dipakai.";
            }
        }
        if ($woDate === '') {
            $errors[] = 'Tanggal WO wajib diisi.';
        }
        if ($technicianId === '') {
            $errors[] = 'Teknisi wajib dipilih.';
        }
        if ($customerName === '') {
            $errors[] = 'Nama pelanggan wajib diisi.';
        }

        if (!empty($errors)) {
            $_SESSION['wo_form_flash'] = [
                'errors' => $errors,
                'old'    => $_POST,
            ];
            $redirect = $id ? "index.php?page=workorder-form&id={$id}" : 'index.php?page=workorder-form';
            header('Location: ' . $redirect);
            exit;
        }

        $data = [
            'wo_no'            => $woNo,
            'wo_date'          => $woDate,
            'technician_id'    => (int) $technicianId,
            'customer_name'    => $customerName,
            'customer_address' => $customerAddress,
            'port_fat'         => $portFat,
            'notes'            => $notes !== '' ? $notes : null,
        ];

        $userId = (int) Auth::user()['id'];

        if ($id) {
            // Status is managed separately via the toggle action, not this form --
            // keep whatever the WO's current status already is.
            $existingWo = WorkOrder::find($id);
            $data['status'] = $existingWo['status'];
            WorkOrder::update($id, $data);
            $after = WorkOrder::find($id);
            AuditLog::record($userId, 'update', 'work_orders', $id, $existingWo, $after);
            $_SESSION['wo_list_flash'] = ['success' => 'Work Order berhasil diperbarui.'];
        } else {
            $data['status'] = 'open';
            $newId = WorkOrder::create($data);
            $after = WorkOrder::find($newId);
            AuditLog::record($userId, 'create', 'work_orders', $newId, null, $after);
            $_SESSION['wo_list_flash'] = ['success' => 'Work Order baru berhasil ditambahkan.'];
        }

        header('Location: index.php?page=workorders');
        exit;
    }

    public function detail(): void
    {
        Auth::requireRole('admin');

        $id = (int) ($_GET['id'] ?? 0);
        $workOrder = WorkOrder::find($id);

        if (!$workOrder) {
            header('Location: index.php?page=workorders');
            exit;
        }

        $loggedMaterials = UsageLog::getByWorkOrder($id);
        $flash = $this->consumeFlash('wo_detail_flash');

        $user = Auth::user();
        $pageTitle = $workOrder['wo_no'];

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/workorders/detail.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function toggleStatus(): void
    {
        Auth::requireRole('admin');

        $id = (int) ($_POST['id'] ?? 0);
        $returnTo = $_POST['return_to'] ?? 'list';

        $before = WorkOrder::find($id);
        WorkOrder::toggleStatus($id);
        $after = WorkOrder::find($id);

        if ($before !== null && $after !== null) {
            AuditLog::record((int) Auth::user()['id'], 'update', 'work_orders', $id, $before, $after);
        }

        $_SESSION['wo_list_flash'] = ['success' => 'Status Work Order diperbarui.'];
        $_SESSION['wo_detail_flash'] = ['success' => 'Status Work Order diperbarui.'];

        $redirect = $returnTo === 'detail'
            ? 'index.php?page=workorder-detail&id=' . $id
            : 'index.php?page=workorders';

        header('Location: ' . $redirect);
        exit;
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