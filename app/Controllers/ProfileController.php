<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantContext;
use App\Core\Translator;
use App\Core\Validator;
use App\Core\View;
use App\Services\AuthService;
use App\Services\AuditService;

/**
 * Profil akun: data dasar, bahasa, dan kata sandi.
 */
final class ProfileController
{
    public function index(): string
    {
        $user = Auth::user() ?? [];

        return View::render('pages/profile', [
            'title' => t('profile.title'),
            'user' => $user,
            'roles' => self::roles((int) ($user['id'] ?? 0)),
            'tenants' => TenantContext::availableTenants((int) ($user['id'] ?? 0)),
        ], 'layouts/app');
    }

    public function update(Request $request): Response|string
    {
        $userId = Auth::id() ?? 0;
        $input = $request->only(['name', 'phone', 'locale', 'timezone']);

        $errors = Validator::validate($input, [
            'name' => 'required|min:2|max:150',
            'phone' => 'nullable|max:40',
            'locale' => 'required|in:' . implode(',', Translator::available()),
            'timezone' => 'nullable|max:64',
        ], [
            'name' => t('auth.field.name'),
            'phone' => t('profile.phone'),
            'locale' => t('profile.language'),
            'timezone' => t('profile.timezone'),
        ]);

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('old', $input);
            return back();
        }

        Database::updateRow('users', [
            'name' => trim((string) $input['name']),
            'phone' => $input['phone'] !== null && $input['phone'] !== '' ? trim((string) $input['phone']) : null,
            'locale' => (string) $input['locale'],
            'timezone' => $input['timezone'] !== null && $input['timezone'] !== '' ? (string) $input['timezone'] : null,
        ], 'id = ?', [$userId]);

        Translator::setLocale((string) $input['locale']);
        Auth::refresh(['name' => trim((string) $input['name']), 'locale' => (string) $input['locale']]);
        AuditService::log(null, 'profile.update', 'users', $userId);

        Session::flash('success', t('profile.updated'));

        return back();
    }

    public function updatePassword(Request $request): Response|string
    {
        $userId = Auth::id() ?? 0;
        $input = $request->only(['current_password', 'password', 'password_confirmation']);

        $errors = Validator::validate($input, [
            'current_password' => 'required',
            'password' => 'required|min_chars:' . Config::int('security.password.min_length', 8) . '|max_chars:200|confirmed',
        ], ['current_password' => t('profile.current_password'), 'password' => t('profile.new_password')]);

        if ($errors !== []) {
            Session::flash('errors', $errors);
            return back();
        }

        if (!AuthService::changePassword($userId, (string) $input['current_password'], (string) $input['password'])) {
            Session::flash('errors', ['current_password' => [t('profile.password_wrong')]]);
            return back();
        }

        Session::flash('success', t('profile.password_updated'));

        return back();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function roles(int $userId): array
    {
        $tenantId = TenantContext::id();

        $rows = Database::select(
            'SELECT r.name, r.code, ur.company_id, c.name AS company_name
             FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             LEFT JOIN companies c ON c.id = ur.company_id
             WHERE ur.user_id = ? AND (ur.tenant_id = ? OR ur.tenant_id IS NULL)
             ORDER BY r.name',
            [$userId, $tenantId]
        ) ?? [];

        $roles = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $roles[] = [
                'name' => (string) $row['name'],
                'code' => (string) $row['code'],
                'company' => $row['company_name'] !== null ? (string) $row['company_name'] : t('profile.all_companies'),
            ];
        }

        return $roles;
    }
}
