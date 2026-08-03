<?php
/**
 * admin/workorders/list.php
 * Expects: $workOrders, $teknisiList, $flash, $user, $_GET['status'], $_GET['technician']
 */
$currentStatus = $_GET['status'] ?? '';
$currentTechnician = $_GET['technician'] ?? '';
?>
<div class="container">
    <div class="topbar">
        <span class="topbar-greeting">Halo, <?= htmlspecialchars($user['name']) ?></span>
        <a href="index.php?page=logout" class="topbar-logout">Logout</a>
    </div>

    <div class="section-header">
        <h2>Work Orders</h2>
        <a href="index.php?page=workorder-form" class="btn-small btn-add">+ Tambah</a>
    </div>

    <?php if ($flash && !empty($flash['success'])): ?>
        <div class="alert-success"><?= htmlspecialchars($flash['success']) ?></div>
    <?php endif; ?>
    <?php if ($flash && !empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $err): ?>
            <div class="alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="GET" action="index.php" class="search-bar">
        <input type="hidden" name="page" value="workorders">
        <select name="technician" onchange="this.form.submit()">
            <option value="">Semua Teknisi</option>
            <?php foreach ($teknisiList as $t): ?>
                <option value="<?= $t['id'] ?>" <?= (string) $currentTechnician === (string) $t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="chip-row">
        <a href="index.php?page=workorders<?= $currentTechnician !== '' ? '&technician=' . urlencode($currentTechnician) : '' ?>"
           class="chip<?= $currentStatus === '' ? ' active' : '' ?>">Semua</a>
        <a href="index.php?page=workorders&status=open<?= $currentTechnician !== '' ? '&technician=' . urlencode($currentTechnician) : '' ?>"
           class="chip<?= $currentStatus === 'open' ? ' active' : '' ?>">Open</a>
        <a href="index.php?page=workorders&status=completed<?= $currentTechnician !== '' ? '&technician=' . urlencode($currentTechnician) : '' ?>"
           class="chip<?= $currentStatus === 'completed' ? ' active' : '' ?>">Completed</a>
    </div>

    <?php if (empty($workOrders)): ?>
        <p class="placeholder-note">Tidak ada Work Order yang cocok.</p>
    <?php endif; ?>

    <div class="card-list">
        <?php foreach ($workOrders as $wo): ?>
            <div class="material-card">
                <div class="material-card-top">
                    <span class="mono-code"><?= htmlspecialchars($wo['wo_no']) ?></span>
                    <span class="badge badge-<?= $wo['status'] === 'open' ? 'serial' : 'quantity' ?>">
                        <?= $wo['status'] === 'open' ? 'Open' : 'Completed' ?>
                    </span>
                </div>
                <div class="material-desc"><?= htmlspecialchars($wo['customer_name']) ?></div>
                <div class="material-meta">
                    <?= htmlspecialchars($wo['technician_name']) ?> · <?= htmlspecialchars($wo['wo_date']) ?>
                </div>

                <div class="material-actions">
                    <a href="index.php?page=workorder-detail&id=<?= $wo['id'] ?>" class="btn-small">Lihat</a>
                    <a href="index.php?page=workorder-form&id=<?= $wo['id'] ?>" class="btn-small">Edit</a>
                    <form method="POST" action="index.php?page=workorder-toggle-status" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $wo['id'] ?>">
                        <input type="hidden" name="return_to" value="list">
                        <button type="submit" class="btn-small">
                            <?= $wo['status'] === 'open' ? 'Tandai Selesai' : 'Buka Kembali' ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>