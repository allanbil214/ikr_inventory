<?php
/**
 * MaterialController.php
 * Phase 4 -- Materials Module (Admin). See handoff doc Section 6 (Admin
 * screen 3) and Section 8 Phase 4 for scope.
 *
 * Validation errors + repopulated form input are passed via a one-shot
 * session flash ($_SESSION['material_form_flash']) rather than a real
 * flash-message library, consistent with this project's plain-PHP,
 * no-framework approach.
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Material.php';
require_once __DIR__ . '/../models/MaterialSerial.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/AuditLog.php';

class MaterialController
{
    public function index(): void
    {
        Auth::requireRole('admin');

        $search = trim($_GET['q'] ?? '');
        $categoryId = isset($_GET['category']) && $_GET['category'] !== ''
            ? (int) $_GET['category']
            : null;

        $materials = Material::getAll($search !== '' ? $search : null, $categoryId);
        $categories = Category::getAll();

        $user = Auth::user();
        $pageTitle = 'Materials';
        $flash = $this->consumeFlash('material_list_flash');

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/materials/list.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function form(): void
    {
        Auth::requireRole('admin');

        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $material = $id ? Material::find($id) : null;

        if ($id && !$material) {
            header('Location: index.php?page=materials');
            exit;
        }

        $categories = Category::getAll();
        $flash = $this->consumeFlash('material_form_flash');

        $user = Auth::user();
        $pageTitle = $material ? 'Edit Material' : 'Tambah Material';

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/materials/form.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function save(): void
    {
        Auth::requireRole('admin');

        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

        $description = trim($_POST['description'] ?? '');
        $merk = trim($_POST['merk'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $trackingType = $_POST['tracking_type'] ?? '';
        $stockQty = $_POST['stock_qty'] ?? '0';
        $lowStockThreshold = trim($_POST['low_stock_threshold'] ?? '');
        $categoryChoice = $_POST['category_id'] ?? '';
        $newCategoryName = trim($_POST['new_category_name'] ?? '');
        $newCategoryPrefix = strtoupper(trim($_POST['new_category_prefix'] ?? ''));

        $errors = [];

        // Resolve category: either an existing one, or an inline "new category".
        $categoryId = null;
        if ($categoryChoice === '__new__') {
            if ($newCategoryName === '' || $newCategoryPrefix === '') {
                $errors[] = 'Nama dan prefix kategori baru wajib diisi.';
            } elseif (Category::findByName($newCategoryName)) {
                $errors[] = "Kategori \"{$newCategoryName}\" sudah ada.";
            } elseif (Category::findByPrefix($newCategoryPrefix)) {
                $errors[] = "Prefix \"{$newCategoryPrefix}\" sudah dipakai kategori lain.";
            } elseif (!preg_match('/^[A-Z0-9]{2,10}$/', $newCategoryPrefix)) {
                $errors[] = 'Prefix harus 2-10 karakter huruf/angka (mis. AONT, ICAB).';
            } else {
                $categoryId = Category::create($newCategoryName, $newCategoryPrefix);
            }
        } elseif ($categoryChoice !== '') {
            $categoryId = (int) $categoryChoice;
        } else {
            $errors[] = 'Kategori wajib dipilih.';
        }

        if ($description === '') {
            $errors[] = 'Deskripsi wajib diisi.';
        }
        if ($unit === '') {
            $errors[] = 'Unit wajib diisi (pcs / meter).';
        }
        if (!$id && !in_array($trackingType, ['serial', 'quantity'], true)) {
            $errors[] = 'Tipe tracking wajib dipilih.';
        }
        if (!$id && $trackingType === 'quantity' && !is_numeric($stockQty)) {
            $errors[] = 'Stok awal harus berupa angka.';
        }
        if ($lowStockThreshold !== '' && (!is_numeric($lowStockThreshold) || (float) $lowStockThreshold < 0)) {
            $errors[] = 'Ambang batas stok rendah harus berupa angka >= 0.';
        }

        if (!empty($errors)) {
            $_SESSION['material_form_flash'] = [
                'errors' => $errors,
                'old'    => $_POST,
            ];
            $redirect = $id ? "index.php?page=material-form&id={$id}" : 'index.php?page=material-form';
            header('Location: ' . $redirect);
            exit;
        }

        $data = [
            'category_id'         => $categoryId,
            'description'         => $description,
            'merk'                => $merk,
            'unit'                => $unit,
            'tracking_type'       => $trackingType,
            'stock_qty'           => $stockQty,
            'low_stock_threshold' => $lowStockThreshold !== '' ? $lowStockThreshold : null,
        ];

        $userId = (int) Auth::user()['id'];

        if ($id) {
            $before = Material::find($id);
            Material::update($id, $data);
            $after = Material::find($id);
            AuditLog::record($userId, 'update', 'materials', $id, $before, $after);
            $_SESSION['material_list_flash'] = ['success' => 'Material berhasil diperbarui.'];
        } else {
            $newId = Material::create($data);
            $after = Material::find($newId);
            AuditLog::record($userId, 'create', 'materials', $newId, null, $after);
            $_SESSION['material_list_flash'] = ['success' => 'Material baru berhasil ditambahkan.'];
        }

        header('Location: index.php?page=materials');
        exit;
    }

    public function serials(): void
    {
        Auth::requireRole('admin');

        $id = (int) ($_GET['id'] ?? 0);
        $material = Material::find($id);

        if (!$material || $material['tracking_type'] !== 'serial') {
            header('Location: index.php?page=materials');
            exit;
        }

        $serialList = MaterialSerial::getByMaterial($id);
        $counts = MaterialSerial::countByStatus($id);
        $flash = $this->consumeFlash('material_serials_flash');

        $user = Auth::user();
        $pageTitle = 'SN — ' . $material['description'];

        require __DIR__ . '/../views/partials/header.php';
        require __DIR__ . '/../views/admin/materials/serials.php';
        require __DIR__ . '/../views/partials/navbar.php';
        require __DIR__ . '/../views/partials/footer.php';
    }

    public function addSerial(): void
    {
        Auth::requireRole('admin');

        $materialId = (int) ($_POST['material_id'] ?? 0);
        $serialNumber = trim($_POST['serial_number'] ?? '');
        $material = Material::find($materialId);

        if (!$material || $material['tracking_type'] !== 'serial') {
            header('Location: index.php?page=materials');
            exit;
        }

        if ($serialNumber === '') {
            $_SESSION['material_serials_flash'] = ['errors' => ['Nomor SN wajib diisi.']];
        } elseif (MaterialSerial::serialExists($serialNumber)) {
            $_SESSION['material_serials_flash'] = ['errors' => ["SN \"{$serialNumber}\" sudah terdaftar."]];
        } else {
            $before = $material;
            MaterialSerial::create($materialId, $serialNumber);
            Material::syncSerialStock($materialId);
            // Logged as a materials update (stock_qty change), not a
            // material_serials create -- audit scope for Phase 9 covers
            // materials/work_orders/usage_logs, and the meaningful change
            // here is the material's stock going up by one SN.
            $after = Material::find($materialId);
            AuditLog::record((int) Auth::user()['id'], 'update', 'materials', $materialId, $before, $after);
            $_SESSION['material_serials_flash'] = ['success' => "SN \"{$serialNumber}\" berhasil ditambahkan ke stok."];
        }

        header('Location: index.php?page=material-serials&id=' . $materialId);
        exit;
    }

    public function adjustStock(): void
    {
        Auth::requireRole('admin');

        $id = (int) ($_POST['id'] ?? 0);
        $newQty = $_POST['new_stock_qty'] ?? '';

        if (!is_numeric($newQty) || (float) $newQty < 0) {
            $_SESSION['material_list_flash'] = ['errors' => ['Stok baru harus berupa angka >= 0.']];
            header('Location: index.php?page=materials');
            exit;
        }

        $before = Material::find($id);
        $ok = Material::adjustStock($id, (float) $newQty);

        if ($ok) {
            $after = Material::find($id);
            AuditLog::record((int) Auth::user()['id'], 'update', 'materials', $id, $before, $after);
        }

        $_SESSION['material_list_flash'] = $ok
            ? ['success' => 'Stok berhasil disesuaikan.']
            : ['errors' => ['Penyesuaian stok manual hanya berlaku untuk material quantity-tracked.']];

        header('Location: index.php?page=materials');
        exit;
    }

    /**
     * Reads and clears a one-shot session flash by key.
     */
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
