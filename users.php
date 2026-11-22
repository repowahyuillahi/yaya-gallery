<?php
require_once __DIR__ . '/layout.php';

require_admin();
$action = $_GET['action'] ?? '';
$id = post_int('id') ?: (int)($_GET['id'] ?? 0);
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'hapus') {
    csrf_check();
    if ($id === (int)$me['id']) {
        flash_set('error', 'Akun yang sedang dipakai tidak bisa dihapus.');
        redirect('users.php');
    }
    q('DELETE FROM users WHERE id = ?', [$id]);
    flash_set('ok', 'Akun dihapus.');
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'simpan') {
    csrf_check();
    $name = post('name');
    $username = post('username');
    $password = post('password');
    $role = post_enum('role', ['admin', 'karyawan'], 'karyawan');
    $isActive = post('is_active') === '1' ? 1 : 0;

    if ($name === '' || $username === '') {
        flash_set('error', 'Nama dan username wajib diisi.');
        redirect($id ? 'users.php?action=edit&id=' . $id : 'users.php?action=baru');
    }
    $duplikat = (int)qval('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', [$username, $id ?: 0]);
    if ($duplikat > 0) {
        flash_set('error', 'Username sudah dipakai akun lain.');
        redirect($id ? 'users.php?action=edit&id=' . $id : 'users.php?action=baru');
    }
    if ($id) {
        // Akun sendiri tidak boleh turunkan hak atau matikan, supaya tidak terkunci.
        if ($id === (int)$me['id']) {
            $role = 'admin';
            $isActive = 1;
        }
        if ($password !== '') {
            q('UPDATE users SET name=:n, username=:u, role=:r, is_active=:a, password_hash=:p WHERE id=:id', [
                'n' => $name, 'u' => $username, 'r' => $role, 'a' => $isActive,
                'p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id,
            ]);
        } else {
            q('UPDATE users SET name=:n, username=:u, role=:r, is_active=:a WHERE id=:id', [
                'n' => $name, 'u' => $username, 'r' => $role, 'a' => $isActive, 'id' => $id,
            ]);
        }
        flash_set('ok', 'Akun ' . $name . ' diperbarui.');
    } else {
        if (strlen($password) < 6) {
            flash_set('error', 'Password baru minimal 6 karakter.');
            redirect('users.php?action=baru');
        }
        q('INSERT INTO users (name, username, password_hash, role, is_active) VALUES (:n, :u, :p, :r, :a)', [
            'n' => $name, 'u' => $username, 'p' => password_hash($password, PASSWORD_DEFAULT), 'r' => $role, 'a' => $isActive,
        ]);
        flash_set('ok', 'Akun ' . $name . ' dibuat.');
    }
    redirect('users.php');
}

if ($action === 'edit' && $id) {
    $edit = qone('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$edit) {
        flash_set('error', 'Akun tidak ditemukan.');
        redirect('users.php');
    }
}

$users = qall('SELECT id, name, username, role, is_active, created_at FROM users ORDER BY id ASC');
page_start('Pengguna', 'users.php');
render_flash();
?>
<?php $v = $edit ?? []; $isEdit = !empty($edit); ?>
<?php if (!empty($edit) || $action === 'baru'): ?>
    <div class="card" style="max-width:640px">
        <h2 class="section" style="margin-top:0;padding-left:0"><?= $isEdit ? 'Edit akun' : 'Tambah akun' ?></h2>
        <?php if (!empty($edit) && (int)$edit['id'] === (int)$me['id']): ?>
            <p class="hint">Ini akun Anda sendiri: role dan status dikunci agar tidak terkunci dari aplikasi.</p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="simpan">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="form-grid">
                <div>
                    <label for="name">Nama</label>
                    <input type="text" id="name" name="name" required value="<?= e($v['name'] ?? '') ?>" placeholder="mis. Feli">
                </div>
                <div>
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required value="<?= e($v['username'] ?? '') ?>" autocomplete="off">
                </div>
                <div>
                    <label for="password">Password <?= $isEdit ? '(kosongkan bila tidak diganti)' : '' ?></label>
                    <input type="password" id="password" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                    <p class="hint">Minimal 6 karakter untuk akun baru.</p>
                </div>
                <div>
                    <label for="role">Role</label>
                    <select id="role" name="role" <?= (!empty($edit) && (int)$edit['id'] === (int)$me['id']) ? 'disabled' : '' ?>>
                        <option value="admin" <?= ($v['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="karyawan" <?= ($v['role'] ?? 'karyawan') === 'karyawan' ? 'selected' : '' ?>>Karyawan</option>
                    </select>
                    <?php if ((!empty($edit) && (int)$edit['id'] === (int)$me['id'])): ?>
                        <input type="hidden" name="role" value="admin">
                    <?php endif; ?>
                </div>
                <div>
                    <label for="is_active">Status akun</label>
                    <select id="is_active" name="is_active" <?= (!empty($edit) && (int)$edit['id'] === (int)$me['id']) ? 'disabled' : '' ?>>
                        <option value="1" <?= (int)($v['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= (int)($v['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <?php if ((!empty($edit) && (int)$edit['id'] === (int)$me['id'])): ?>
                        <input type="hidden" name="is_active" value="1">
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a class="btn btn-ghost" href="users.php">Batal</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="pagebar">
        <span class="spacer"></span>
        <a class="btn btn-primary" href="users.php?action=baru">Tambah akun</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="cell-main"><?= e($u['name']) ?><?= (int)$u['id'] === (int)$me['id'] ? ' <span class="badge st-ok">Anda</span>' : '' ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e(ucfirst($u['role'])) ?></td>
                    <td><?= (int)$u['is_active'] === 1 ? '<span class="badge st-ok">Aktif</span>' : '<span class="badge st-off">Nonaktif</span>' ?></td>
                    <td>
                        <span class="rowactions">
                            <a class="btn btn-ghost btn-sm" href="users.php?action=edit&id=<?= (int)$u['id'] ?>">Edit</a>
                            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                                <form method="post" data-confirm="Hapus akun <?= e($u['name']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
page_end();
