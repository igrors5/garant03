<?php /* $allUsers, $currentUser, $staffMsg, $canManageStaff */ ?>
<section class="view active" id="view-staff">
<div class="main-head">
<div><h1>Сотрудники</h1><p>Управление учётными записями, ролями и правами доступа</p></div>
<div style="font-size:13px;color:var(--muted)">Всего: <?= count($allUsers) ?></div>
</div>

<?php if ($staffMsg !== null): ?>
<div class="staff-alert <?= !empty($staffMsg['ok']) ? 'ok' : 'err' ?>">
<?php if (!empty($staffMsg['ok'])): ?>
    ✓ <?= e($staffMsg['is_new'] ?? false ? 'Сотрудник создан.' : 'Изменения сохранены.') ?>
<?php else: ?>
    ✕ <?= e($staffMsg['error'] ?? 'Ошибка') ?>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canManageStaff): ?>
<div class="staff-form" id="staffForm">
<div class="staff-form-title">
<span id="staffFormTitle">Новый сотрудник</span>
<span class="mode-badge" id="staffModeBadge">создание</span>
</div>
<form method="post" action="admin.php" autocomplete="off">
<input type="hidden" name="action" value="staff_save">
<input type="hidden" name="edit_mode" id="editMode" value="0">
<div class="staff-form-grid">
<div class="staff-field">
<label for="sf-username">Логин *</label>
<input type="text" id="sf-username" name="username" required pattern="[a-zA-Z0-9._\-]{3,32}" placeholder="например, operator1">
<div class="hint">3–32 символа, латиница, цифры, . _ -</div>
</div>
<div class="staff-field">
<label for="sf-fullname">ФИО</label>
<input type="text" id="sf-fullname" name="fullname" placeholder="Иванов Иван Иванович">
</div>
<div class="staff-field">
<label for="sf-password">Пароль <span id="pwd-hint">*</span></label>
<input type="text" id="sf-password" name="password" autocomplete="new-password">
<div class="hint" id="pwd-sub">Минимум 6 символов. Обязательно при создании.</div>
</div>
<div class="staff-field">
<label for="sf-role">Роль *</label>
<select id="sf-role" name="role" required>
<?php foreach (ROLE_LABELS as $k => $l): ?>
<option value="<?= e($k) ?>"><?= e($l) ?></option>
<?php endforeach; ?>
</select>
<div class="hint">При выборе роли подставляются рекомендуемые права.</div>
</div>
</div>
<div class="staff-perms">
<div class="perm-block">
<div class="perm-block-title">
<span>Доступ к разделам</span>
<span class="toggle-all" data-target="views">выбрать все</span>
</div>
<?php foreach (VIEW_LABELS as $k => $l): ?>
<div class="perm-item">
<input type="checkbox" name="views[]" value="<?= e($k) ?>" id="v-<?= e($k) ?>" class="perm-views">
<label for="v-<?= e($k) ?>"><?= e($l) ?></label>
</div>
<?php endforeach; ?>
</div>
<div class="perm-block">
<div class="perm-block-title">
<span>Права на действия</span>
<span class="toggle-all" data-target="actions">выбрать все</span>
</div>
<?php foreach (ACTION_LABELS as $k => $l): ?>
<div class="perm-item">
<input type="checkbox" name="actions[]" value="<?= e($k) ?>" id="a-<?= e($k) ?>" class="perm-actions">
<label for="a-<?= e($k) ?>"><?= e($l) ?></label>
</div>
<?php endforeach; ?>
</div>
</div>
<div class="staff-form-actions">
<button type="submit" class="btn btn-accept" id="staffSubmitBtn">Создать сотрудника</button>
<button type="button" class="btn btn-ghost" id="staffCancelBtn">Отмена</button>
</div>
</form>
</div>
<?php endif; ?>

<div class="staff-table">
<div class="staff-table-head">
<div>Логин</div><div>ФИО</div><div>Роль</div><div>Разделы</div><div>Действия</div><div>Обновлён</div><div></div>
</div>
<?php if (empty($allUsers)): ?>
<div style="padding:40px;text-align:center;color:var(--muted)">Сотрудников пока нет.</div>
<?php else: ?>
<?php foreach ($allUsers as $u):
$role = $u['role'] ?? 'viewer';
$roleBadge = 'role-' . e($role);
$views = $u['views'] ?? [];
$actions = $u['actions'] ?? [];
$updated = !empty($u['updated_at']) ? date('d.m.Y H:i', strtotime($u['updated_at'])) : '—';
$isYou = ($u['username'] === $currentUser['username']);
?>
<div class="staff-table-row"
     data-username="<?= e($u['username']) ?>"
     data-fullname="<?= e($u['fullname'] ?? '') ?>"
     data-role="<?= e($role) ?>"
     data-views='<?= e(json_encode($views, JSON_UNESCAPED_UNICODE)) ?>'
     data-actions='<?= e(json_encode($actions, JSON_UNESCAPED_UNICODE)) ?>'>
<div class="staff-cell login"><?= e($u['username']) ?><?php if ($isYou): ?><span class="staff-you">(вы)</span><?php endif; ?></div>
<div class="staff-cell name"><?= e($u['fullname'] ?? '—') ?></div>
<div class="staff-cell role"><span class="mini-badge <?= $roleBadge ?>"><?= e(ROLE_LABELS[$role] ?? $role) ?></span></div>
<div class="staff-cell views"><div class="mini-badges"><?php foreach ($views as $v): ?><span class="mini-badge"><?= e(VIEW_LABELS[$v] ?? $v) ?></span><?php endforeach; ?><?php if (empty($views)): ?><span class="mini-badge" style="color:#bbb">нет</span><?php endif; ?></div></div>
<div class="staff-cell actions-col"><div class="mini-badges"><?php foreach ($actions as $a): ?><span class="mini-badge"><?= e(ACTION_LABELS[$a] ?? $a) ?></span><?php endforeach; ?><?php if (empty($actions)): ?><span class="mini-badge" style="color:#bbb">нет</span><?php endif; ?></div></div>
<div class="staff-cell upd"><?= e($updated) ?></div>
<div class="staff-actions">
<?php if ($canManageStaff): ?>
<button type="button" class="icon-btn staff-edit" title="Редактировать">✎</button>
<?php if ($u['username'] !== $currentUser['username']): ?>
<form method="post" action="admin.php" style="display:inline" onsubmit="return confirm('Удалить сотрудника «<?= e($u['username']) ?>»?');">
<input type="hidden" name="action" value="staff_delete">
<input type="hidden" name="username" value="<?= e($u['username']) ?>">
<button type="submit" class="icon-btn danger" title="Удалить">🗑</button>
</form>
<?php endif; ?>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</section>