<?php
/**
 * admin/audit/list.php
 * Phase 9. Expects: $entries, $allUsers, $user, and the raw filter
 * values in $_GET (table, action, user, date_from, date_to).
 *
 * Logging started this phase (see AuditLog.php header) -- there is no
 * history before this point, so an empty list simply means nothing has
 * been created/updated/deleted yet since Phase 9 shipped.
 */
$currentTable = $_GET['table'] ?? '';
$currentAction = $_GET['action'] ?? '';
$currentUser = $_GET['user'] ?? '';
$currentDateFrom = htmlspecialchars($_GET['date_from'] ?? '');
$currentDateTo = htmlspecialchars($_GET['date_to'] ?? '');

$tableLabels = [
    'materials'    => 'Materials',
    'work_orders'  => 'Work Orders',
    'usage_logs'   => 'Usage Logs',
];
$actionLabels = [
    'create' => 'Dibuat',
    'update' => 'Diubah',
    'delete' => 'Dihapus',
];
?>
<div class="container">
    <div class="topbar">
        <a href="index.php?page=dashboard" class="topbar-back">&larr; Dashboard</a>
    </div>

    <div class="section-header">
        <h2>Audit Log</h2>
    </div>

    <p class="placeholder-note">
        Mencatat aksi create/update/delete sejak Phase 9 diaktifkan. Aksi
        sebelum ini tidak tercatat (lihat handoff doc Phase 9).
    </p>

    <form method="GET" action="index.php" class="filter-panel">
        <input type="hidden" name="page" value="audit-log">

        <div class="filter-row">
            <label>Tabel
                <select name="table">
                    <option value="">Semua Tabel</option>
                    <?php foreach ($tableLabels as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $currentTable === $value ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Aksi
                <select name="action">
                    <option value="">Semua Aksi</option>
                    <?php foreach ($actionLabels as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $currentAction === $value ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="filter-row">
            <label>Pengguna
                <select name="user">
                    <option value="">Semua Pengguna</option>
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (string) $currentUser === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= $u['role'] === 'admin' ? 'Admin' : 'Teknisi' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="filter-row">
            <label>Dari
                <input type="date" name="date_from" value="<?= $currentDateFrom ?>">
            </label>
            <label>Sampai
                <input type="date" name="date_to" value="<?= $currentDateTo ?>">
            </label>
        </div>

        <div class="filter-row">
            <button type="submit" class="btn-search">Terapkan Filter</button>
        </div>
    </form>

    <?php if (empty($entries)): ?>
        <p class="placeholder-note">Tidak ada entri audit log yang cocok.</p>
    <?php else: ?>
        <div class="serial-list">
            <?php foreach ($entries as $entry): ?>
                <?php
                    $actionClass = [
                        'create' => 'badge-serial',
                        'update' => 'badge-quantity',
                        'delete' => 'badge-used',
                    ][$entry['action']] ?? 'badge-serial';
                    $tableLabel = $tableLabels[$entry['table_name']] ?? htmlspecialchars($entry['table_name']);
                    $actionLabel = $actionLabels[$entry['action']] ?? htmlspecialchars($entry['action']);
                ?>
                <div class="log-row">
                    <div class="log-row-info">
                        <div>
                            <span class="badge <?= $actionClass ?>"><?= $actionLabel ?></span>
                            <?= $tableLabel ?> #<?= (int) $entry['record_id'] ?>
                        </div>
                        <div class="material-meta">
                            <?= htmlspecialchars($entry['user_name']) ?> ·
                            <?= htmlspecialchars(substr($entry['created_at'], 0, 16)) ?>
                        </div>
                        <?php if ($entry['old_value'] !== null || $entry['new_value'] !== null): ?>
                            <details class="audit-detail">
                                <summary>Detail perubahan</summary>
                                <?php if ($entry['old_value'] !== null): ?>
                                    <div class="audit-detail-block">
                                        <strong>Sebelum:</strong>
                                        <code><?= htmlspecialchars($entry['old_value']) ?></code>
                                    </div>
                                <?php endif; ?>
                                <?php if ($entry['new_value'] !== null): ?>
                                    <div class="audit-detail-block">
                                        <strong>Sesudah:</strong>
                                        <code><?= htmlspecialchars($entry['new_value']) ?></code>
                                    </div>
                                <?php endif; ?>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
