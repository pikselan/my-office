<?php

declare(strict_types=1);

/**
 * Komponen antarmuka yang dapat dipakai ulang.
 *
 * Komponen dikembalikan sebagai string HTML dengan seluruh nilai sudah
 * di-escape, sehingga view tidak perlu mengulang atribut yang sama.
 */

use App\Core\Csrf;
use App\Core\Paginator;
use App\Core\Translator;
use App\Core\View;

if (!function_exists('mo_attr')) {
    /**
     * Bangun atribut HTML dari array.
     *
     * @param array<string, mixed> $attributes
     */
    function mo_attr(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $parts[] = e((string) $name);
                continue;
            }

            $parts[] = e((string) $name) . '="' . e((string) $value) . '"';
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('mo_field_open')) {
    /**
     * @param array<string, mixed> $options
     */
    function mo_field_open(string $name, string $label, array $options = []): string
    {
        $required = (bool) ($options['required'] ?? false);
        $error = errors($name);

        $html = '<div class="mo-field">';
        $html .= '<label class="mo-field__label" for="' . e($options['id'] ?? $name) . '">' . e($label);
        if ($required) {
            $html .= ' <span>*</span>';
        }
        $html .= '</label>';

        return $html;
    }
}

if (!function_exists('mo_field_close')) {
    /**
     * @param array<string, mixed> $options
     */
    function mo_field_close(string $name, array $options = []): string
    {
        $error = errors($name);
        $html = '';

        if ($error !== null && $error !== '') {
            $html .= '<span class="mo-field__error">' . e((string) $error) . '</span>';
        } elseif (isset($options['hint'])) {
            $html .= '<span class="mo-field__hint">' . e((string) $options['hint']) . '</span>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('mo_input')) {
    /**
     * @param array<string, mixed> $options
     */
    function mo_input(string $name, string $label, mixed $value = '', array $options = []): string
    {
        $type = (string) ($options['type'] ?? 'text');
        $error = errors($name);

        $attributes = [
            'type' => $type,
            'id' => $options['id'] ?? $name,
            'name' => $name,
            'value' => old($name, $value),
            'class' => 'mo-input' . ($error ? ' is-invalid' : ''),
            'placeholder' => $options['placeholder'] ?? null,
            'required' => ($options['required'] ?? false) ? 'required' : null,
            'min' => $options['min'] ?? null,
            'max' => $options['max'] ?? null,
            'step' => $options['step'] ?? null,
            'autocomplete' => $options['autocomplete'] ?? null,
            'disabled' => ($options['disabled'] ?? false) ? 'disabled' : null,
            'data-mo-mask' => $options['mask'] ?? null,
        ];

        return mo_field_open($name, $label, $options)
            . '<input ' . mo_attr($attributes) . '>'
            . mo_field_close($name, $options);
    }
}

if (!function_exists('mo_textarea')) {
    /**
     * @param array<string, mixed> $options
     */
    function mo_textarea(string $name, string $label, mixed $value = '', array $options = []): string
    {
        $error = errors($name);

        $attributes = [
            'id' => $options['id'] ?? $name,
            'name' => $name,
            'class' => 'mo-textarea' . ($error ? ' is-invalid' : ''),
            'rows' => $options['rows'] ?? 4,
            'placeholder' => $options['placeholder'] ?? null,
            'required' => ($options['required'] ?? false) ? 'required' : null,
        ];

        return mo_field_open($name, $label, $options)
            . '<textarea ' . mo_attr($attributes) . '>' . e((string) old($name, $value)) . '</textarea>'
            . mo_field_close($name, $options);
    }
}

if (!function_exists('mo_select')) {
    /**
     * @param array<string, mixed> $optionsList
     * @param array<string, mixed> $options
     */
    function mo_select(string $name, string $label, array $optionsList = [], mixed $value = '', array $options = []): string
    {
        $selected = (string) old($name, $value);
        $error = errors($name);

        $html = mo_field_open($name, $label, $options);
        $html .= '<select ' . mo_attr([
            'id' => $options['id'] ?? $name,
            'name' => $name,
            'class' => 'mo-select' . ($error ? ' is-invalid' : ''),
            'required' => ($options['required'] ?? false) ? 'required' : null,
            'disabled' => ($options['disabled'] ?? false) ? 'disabled' : null,
        ]) . '>';

        if (($options['placeholder'] ?? null) !== null) {
            $html .= '<option value="">' . e((string) $options['placeholder']) . '</option>';
        }

        foreach ($optionsList as $key => $label) {
            $optionValue = (string) $key;
            $html .= '<option value="' . e($optionValue) . '"' . ($optionValue === $selected ? ' selected' : '') . '>'
                . e((string) $label) . '</option>';
        }

        $html .= '</select>';

        return $html . mo_field_close($name, $options);
    }
}

if (!function_exists('mo_checkbox')) {
    function mo_checkbox(string $name, string $label, bool $checked = false, array $options = []): string
    {
        return '<label class="mo-check">'
            . '<input type="checkbox" name="' . e($name) . '" value="1"' . (old($name, $checked) ? ' checked' : '') . '>'
            . '<span>' . e($label) . '</span></label>';
    }
}

if (!function_exists('mo_button')) {
    /**
     * @param array<string, mixed> $options
     */
    function mo_button(string $label, array $options = []): string
    {
        $variant = (string) ($options['variant'] ?? 'default');
        $classes = 'mo-btn';

        if ($variant !== 'default') {
            $classes .= ' mo-btn--' . $variant;
        }
        if ($options['small'] ?? false) {
            $classes .= ' mo-btn--sm';
        }
        if ($options['block'] ?? false) {
            $classes .= ' mo-btn--block';
        }

        $tag = ($options['href'] ?? null) !== null ? 'a' : 'button';

        $attributes = [
            'class' => $classes . ' ' . ($options['class'] ?? ''),
            'href' => $options['href'] ?? null,
            'type' => $tag === 'button' ? ($options['type'] ?? 'submit') : null,
            'data-mo-confirm' => $options['confirm'] ?? null,
            'data-mo-danger' => ($options['confirm'] ?? null) !== null ? true : null,
            'disabled' => ($options['disabled'] ?? false) ? 'disabled' : null,
        ];

        return '<' . $tag . ' ' . mo_attr($attributes) . '>' . e($label) . '</' . $tag . '>';
    }
}

if (!function_exists('mo_badge')) {
    /**
     * @param array<string, string> $map
     */
    function mo_badge(string $value, array $map = [], ?string $variant = null): string
    {
        $label = $map[$value] ?? $value;
        $classes = 'mo-badge';

        if ($variant !== null) {
            $classes .= ' mo-badge--' . $variant;
        }

        return '<span class="' . e($classes) . '">' . e((string) $label) . '</span>';
    }
}

if (!function_exists('mo_status_badge')) {
    function mo_status_badge(?string $status): string
    {
        $status = (string) $status;

        $variants = [
            'active' => 'success', 'published' => 'success', 'approved' => 'success',
            'present' => 'success', 'paid' => 'success', 'done' => 'success',
            'pending' => 'warning', 'draft' => 'warning', 'trialing' => 'info',
            'archived' => 'default', 'inactive' => 'default', 'cancelled' => 'default',
            'rejected' => 'danger', 'suspended' => 'danger', 'expired' => 'danger',
            'past_due' => 'danger', 'absent' => 'danger', 'late' => 'warning',
        ];

        $labels = [
            'active' => 'Aktif', 'inactive' => 'Nonaktif', 'archived' => 'Diarsipkan',
            'draft' => 'Draf', 'pending' => 'Menunggu', 'approved' => 'Disetujui',
            'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'published' => 'Dipublikasikan',
            'trialing' => 'Percobaan', 'past_due' => 'Jatuh tempo', 'suspended' => 'Ditangguhkan',
            'expired' => 'Kedaluwarsa', 'present' => 'Hadir', 'absent' => 'Tidak hadir',
            'late' => 'Terlambat', 'paid' => 'Dibayar', 'done' => 'Selesai',
        ];

        return mo_badge($labels[$status] ?? $status, [], $variants[$status] ?? 'default');
    }
}

if (!function_exists('mo_alert')) {
    function mo_alert(string $message, string $type = 'info'): string
    {
        return '<div class="mo-alert mo-alert--' . e($type) . '"><div>' . e($message) . '</div></div>';
    }
}

if (!function_exists('mo_empty_state')) {
    /**
     * @param array<string, mixed> $options
     */
    function mo_empty_state(string $title, string $hint = '', array $options = []): string
    {
        $html = '<div class="mo-empty"><div class="mo-empty__icon">'
            . '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">'
            . '<path d="M4 7h16M4 12h16M4 17h10"/></svg></div>'
            . '<div class="mo-empty__title">' . e($title) . '</div>';

        if ($hint !== '') {
            $html .= '<div class="mo-empty__hint">' . e($hint) . '</div>';
        }

        if (isset($options['action'])) {
            $html .= $options['action'];
        }

        return $html . '</div>';
    }
}

if (!function_exists('mo_pagination')) {
    function mo_pagination(Paginator $paginator): string
    {
        return $paginator->render();
    }
}

if (!function_exists('mo_stat_card')) {
    /**
     * @param array<string, mixed> $options
     */
    function mo_stat_card(string $label, mixed $value, string $hint = '', array $options = []): string
    {
        $html = '<div class="mo-card mo-stat">';

        if (isset($options['icon'])) {
            $html .= '<div class="mo-stat__icon">' . $options['icon'] . '</div>';
        }

        $html .= '<span class="mo-stat__label">' . e($label) . '</span>';
        $html .= '<span class="mo-stat__value">' . e((string) $value) . '</span>';

        if ($hint !== '') {
            $html .= '<span class="mo-stat__hint">' . e($hint) . '</span>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('mo_avatar')) {
    /**
     * @param array<string, mixed> $options
     */
    function mo_avatar(string $name, ?string $photoPath = null, array $options = []): string
    {
        $size = $options['size'] ?? '';
        $class = 'mo-avatar' . ($size !== '' ? ' mo-avatar--' . $size : '');

        if ($photoPath !== null && $photoPath !== '') {
            return '<img class="' . e($class) . '" src="' . e($photoPath) . '" alt="' . e($name) . '">';
        }

        $initials = '';
        foreach (explode(' ', trim($name)) as $word) {
            if ($word !== '') {
                $initials .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
            }
            if (mb_strlen($initials, 'UTF-8') >= 2) {
                break;
            }
        }

        return '<span class="' . e($class) . '">' . e($initials !== '' ? $initials : '?') . '</span>';
    }
}

if (!function_exists('mo_modal')) {
    /**
     * @param array<string, mixed> $options
     */
    function mo_modal(string $id, string $title, string $body, string $footer = '', array $options = []): string
    {
        $wide = $options['wide'] ?? false;

        return '<div class="mo-modal" id="' . e($id) . '">'
            . '<div class="mo-modal__backdrop" data-mo-modal-close></div>'
            . '<div class="mo-modal__dialog' . ($wide ? ' mo-modal__dialog--wide' : '') . '">'
            . '<div class="mo-modal__header"><div class="mo-modal__title">' . e($title) . '</div>'
            . '<button type="button" class="mo-btn mo-btn--icon" data-mo-modal-close aria-label="Tutup">✕</button></div>'
            . '<div class="mo-modal__body">' . $body . '</div>'
            . ($footer !== '' ? '<div class="mo-modal__footer">' . $footer . '</div>' : '')
            . '</div></div>';
    }
}

if (!function_exists('mo_csrf')) {
    function mo_csrf(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('mo_confirm_modal')) {
    function mo_confirm_modal(): string
    {
        return mo_modal('mo-confirm-modal', Translator::get('action.confirm'),
            '<p id="mo-confirm-message" class="mo-mb-0">' . e(Translator::get('message.confirm_action')) . '</p>',
            mo_button(Translator::get('action.cancel'), ['variant' => 'ghost', 'type' => 'button', 'class' => ''])
            . '<button type="button" class="mo-btn mo-btn--primary" id="mo-confirm-accept">' . e(Translator::get('action.confirm')) . '</button>',
            []
        );
    }
}
