<?php
declare(strict_types=1);
/**
 * Compact "assign to a church" form used on admin list pages.
 * Expected variables: $assignAction (POST url), $reassignId (record id),
 * $assignableUnits (array of ['id','name','type']) and $reassignUnitId (optional current unit id).
 */
$assignableUnits = $assignableUnits ?? [];
$reassignUnitId = $reassignUnitId ?? null;
$showUnassignedOnly = $showUnassignedOnly ?? true; // only show when currently unassigned
$isUnassigned = empty($reassignUnitId);
?>
<?php if ((!$showUnassignedOnly || $isUnassigned) && $assignableUnits): ?>
  <form method="post" action="<?= e($assignAction) ?>" class="unit-assign" style="display:inline-flex;gap:6px;align-items:center;white-space:nowrap;">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $reassignId ?>">
    <select name="org_unit_id" required style="max-width:190px;font-size:12px;padding:5px 8px;border-radius:8px;background:var(--panel-2);border:1px solid var(--border);color:var(--ink);">
      <option value=""><?= $isUnassigned ? 'Assign to…' : 'Move to…' ?></option>
      <?php foreach ($assignableUnits as $u): ?>
        <option value="<?= (int) $u['id'] ?>" <?= $reassignUnitId === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name'] . ' (' . $u['type'] . ')') ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn secondary sm" type="submit"><?= $isUnassigned ? 'Assign' : 'Move' ?></button>
  </form>
<?php endif; ?>
