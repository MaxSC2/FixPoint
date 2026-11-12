<?php
declare(strict_types=1);

/**
 * Accessible select field.
 *
 * Inputs: name, label, options[{value,label}], selected, placeholder,
 * help, error, required, id.
 */
$fieldName = isset($name) ? (string) $name : '';
$fieldLabel = isset($label) ? (string) $label : '';
$fieldId = isset($id) && is_string($id)
    ? $id
    : (preg_replace('/[^a-zA-Z0-9_-]/', '-', $fieldName) ?: 'form-select');
$fieldOptions = isset($options) && is_array($options) ? $options : [];
$fieldSelected = isset($selected) ? (string) $selected : '';
$fieldPlaceholder = isset($placeholder) ? (string) $placeholder : 'Выберите вариант';
$fieldHelp = isset($help) ? (string) $help : '';
$fieldError = isset($error) ? (string) $error : '';
$fieldRequired = !empty($required);
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
    <select
        id="<?= e($fieldId) ?>"
        name="<?= e($fieldName) ?>"
        <?= $fieldRequired ? 'required' : '' ?>
        <?= $fieldError !== '' ? 'aria-invalid="true"' : '' ?>
        <?= $describedBy !== [] ? 'aria-describedby="' . e(implode(' ', $describedBy)) . '"' : '' ?>
    >
        <option value=""><?= e($fieldPlaceholder) ?></option>
        <?php foreach ($fieldOptions as $option): ?>
            <?php
            if (!is_array($option)) {
                continue;
            }
            $optionValue = (string) ($option['value'] ?? '');
            $optionLabel = (string) ($option['label'] ?? '');
            ?>
            <option value="<?= e($optionValue) ?>" <?= $fieldSelected === $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($fieldHelp !== ''): ?><p class="field__help" id="<?= e($fieldId) ?>-help"><?= e($fieldHelp) ?></p><?php endif; ?>
    <?php if ($fieldError !== ''): ?><p class="field__error" id="<?= e($fieldId) ?>-error"><?= e($fieldError) ?></p><?php endif; ?>
</div>
