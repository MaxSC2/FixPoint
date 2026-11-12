<?php
declare(strict_types=1);

/**
 * Reusable text-like form field.
 *
 * Inputs: name, label, value, type, placeholder, help, error, required,
 * autocomplete, multiline, rows.
 */
$fieldName = isset($name) ? (string) $name : '';
$fieldLabel = isset($label) ? (string) $label : '';
$fieldType = isset($type) ? (string) $type : 'text';
$allowedTypes = ['text', 'email', 'tel', 'password', 'number', 'date', 'search', 'url'];
if (!in_array($fieldType, $allowedTypes, true)) {
    $fieldType = 'text';
}
$fieldId = isset($id) && is_string($id)
    ? $id
    : (preg_replace('/[^a-zA-Z0-9_-]/', '-', $fieldName) ?: 'form-field');
$fieldValue = $fieldType === 'password' ? '' : (string) ($value ?? '');
$fieldPlaceholder = isset($placeholder) ? (string) $placeholder : '';
$fieldHelp = isset($help) ? (string) $help : '';
$fieldError = isset($error) ? (string) $error : '';
$fieldRequired = !empty($required);
$fieldAutocomplete = isset($autocomplete) ? (string) $autocomplete : '';
$fieldMultiline = !empty($multiline) && $fieldType === 'text';
$fieldRows = isset($rows) && is_numeric($rows) ? max(3, min(12, (int) $rows)) : 5;
$describedBy = [];
if ($fieldHelp !== '') {
    $describedBy[] = $fieldId . '-help';
}
if ($fieldError !== '') {
    $describedBy[] = $fieldId . '-error';
}
?>
<div class="field<?= $fieldError !== '' ? ' field--invalid' : '' ?>">
    <label class="field__label" for="<?= e($fieldId) ?>">
        <?= e($fieldLabel) ?>
        <?php if ($fieldRequired): ?><span class="field__required" aria-hidden="true">*</span><?php endif; ?>
    </label>
    <?php if ($fieldMultiline): ?>
        <textarea
            id="<?= e($fieldId) ?>"
            name="<?= e($fieldName) ?>"
            rows="<?= e((string) $fieldRows) ?>"
            placeholder="<?= e($fieldPlaceholder) ?>"
            <?= $fieldRequired ? 'required' : '' ?>
            <?= $describedBy !== [] ? 'aria-describedby="' . e(implode(' ', $describedBy)) . '"' : '' ?>
        ><?= e($fieldValue) ?></textarea>
    <?php else: ?>
        <input
            id="<?= e($fieldId) ?>"
            type="<?= e($fieldType) ?>"
            name="<?= e($fieldName) ?>"
            value="<?= e($fieldValue) ?>"
            placeholder="<?= e($fieldPlaceholder) ?>"
            <?= $fieldAutocomplete !== '' ? 'autocomplete="' . e($fieldAutocomplete) . '"' : '' ?>
            <?= $fieldRequired ? 'required' : '' ?>
            <?= $describedBy !== [] ? 'aria-describedby="' . e(implode(' ', $describedBy)) . '"' : '' ?>
        >
    <?php endif; ?>
    <?php if ($fieldHelp !== ''): ?><p class="field__help" id="<?= e($fieldId) ?>-help"><?= e($fieldHelp) ?></p><?php endif; ?>
    <?php if ($fieldError !== ''): ?><p class="field__error" id="<?= e($fieldId) ?>-error"><?= e($fieldError) ?></p><?php endif; ?>
</div>
