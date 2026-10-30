<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Authorization;
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
use App\Services\TenantService;

/**
 * Autentikasi: masuk, daftar, pilih tenant, verifikasi, dan lupa kata sandi.
 */
final class AuthController
{
    public function showLogin(): Response|string
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin();
        }

        return View::render('Auth::login', [
            'title' => t('auth.login'),
            'csrf' => csrf_token(),
        ], 'layouts/auth');
    }

    public function login(Request $request): Response|string
    {
        $input = $request->only(['email', 'password']);

        $errors = Validator::validate($input, [
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email' => t('auth.email'),
            'password' => t('auth.password'),
        ]);

        if ($errors !== []) {
            return $this->backWithErrors($errors, $input);
        }

        $result = AuthService::attempt(
            (string) $input['email'],
            (string) $input['password'],
            $request->ip(),
            $request->userAgent()
        );

        if ($result['ok'] !== true) {
            $message = $result['error'] === 'locked' ? t('auth.account_locked') : t('auth.invalid_credentials');

            return $this->backWithErrors(['email' => [$message]], ['email' => $input['email']]);
        }

        AuthService::establishSession($result['user'], $request->ip(), $request->userAgent());

        $this->selectFirstTenant((int) $result['user']['id']);

        $intended = Session::get('mo.intended');
        Session::forget('mo.intended');

        if (is_string($intended) && $intended !== '') {
            return redirect($intended);
        }

        if (TenantContext::id() === null) {
            return redirect('select-tenant');
        }

        return redirect('dashboard');
    }

    public function showRegister(): Response|string
    {
        if (Auth::check()) {
            return redirect('dashboard');
        }

        return View::render('Auth::register', [
            'title' => t('auth.register'),
            'plans' => self::publicPlans(),
            'csrf' => csrf_token(),
        ], 'layouts/auth');
    }

    public function register(Request $request): Response|string
    {
        $input = $request->only(['owner_name', 'email', 'password', 'password_confirmation', 'tenant_name', 'company_name', 'plan_code']);

        $plans = self::publicPlans();
        $allowed = array_map(static fn (array $plan): string => (string) $plan['code'], $plans);

        if (!in_array((string) $input['plan_code'], $allowed, true)) {
            return $this->backWithErrors(['plan_code' => [t('auth.plan_invalid')]], $input);
        }

        $input['locale'] = Translator::locale();
        $input['timezone'] = 'Asia/Jakarta';

        $result = AuthService::registerOwner($input);

        if ($result['ok'] !== true) {
            return $this->backWithErrors($result['errors'] ?? ['_general' => [t('auth.register_failed')]], $input);
        }

        $user = Database::selectOne('SELECT id, name, email FROM users WHERE id = ?', [$result['data']['user_id']]);

        if ($user === null) {
            return $this->backWithErrors(['_general' => [t('auth.register_failed')]], $input);
        }

        AuthService::establishSession($user, $request->ip(), $request->userAgent());
        $this->selectFirstTenant((int) $user['id']);

        Session::flash('success', t('auth.register_success'));

        return redirect('dashboard');
    }

    public function showSelectTenant(): Response|string
    {
        $userId = Auth::id();

        if ($userId === null) {
            return redirect('login');
        }

        $tenants = TenantContext::availableTenants($userId);

        // Administrator platform tidak harus menjadi anggota tenant.
        if ($tenants === [] && Authorization::isPlatformAdmin() && \App\Core\App::instance()->router()->hasRoute('platform.index')) {
            return redirect('platform');
        }

        if ($tenants === []) {
            Session::flash('error', t('auth.select_tenant_none'));
            return redirect('logout');
        }

        if (count($tenants) === 1) {
            TenantContext::setTenant((int) $tenants[0]['id'], $userId);
            return redirect('dashboard');
        }

        return View::render('Auth::select-tenant', [
            'title' => t('auth.select_tenant_title'),
            'tenants' => $tenants,
            'csrf' => csrf_token(),
        ], 'layouts/auth');
    }

    public function selectTenant(Request $request): Response|string
    {
        $userId = Auth::id();

        if ($userId === null) {
            return redirect('login');
        }

        $tenantId = (int) $request->input('tenant_id', 0);

        if (!TenantContext::setTenant($tenantId, $userId)) {
            Session::flash('error', t('auth.select_tenant_denied'));
            return redirect('select-tenant');
        }

        $intended = Session::get('mo.intended');
        Session::forget('mo.intended');

        return redirect(is_string($intended) && $intended !== '' ? $intended : 'dashboard');
    }

    public function logout(Request $request): Response|string
    {
        if (Auth::check()) {
            AuthService::logout();
            Session::flash('success', t('auth.logged_out'));
        }

        return redirect('login');
    }

    public function verifyEmail(string $token): Response|string
    {
        if (AuthService::verifyEmail($token)) {
            Session::flash('success', t('auth.verify.success'));
            return redirect(Auth::check() ? 'dashboard' : 'login');
        }

        Session::flash('error', t('auth.verify.invalid'));
        return redirect(Auth::check() ? 'dashboard' : 'login');
    }

    public function showForgotPassword(): Response|string
    {
        return View::render('Auth::forgot', [
            'title' => t('auth.forgot_title'),
            'csrf' => csrf_token(),
        ], 'layouts/auth');
    }

    public function sendResetLink(Request $request): Response|string
    {
        $input = $request->only(['email']);

        $errors = Validator::validate($input, ['email' => 'required|email'], ['email' => t('auth.email')]);

        if ($errors !== []) {
            return $this->backWithErrors($errors, $input);
        }

        // Selalu beri respons yang sama agar keberadaan akun tidak terbaca.
        AuthService::sendPasswordReset((string) $input['email'], $request->ip());

        Session::flash('success', t('auth.reset.sent'));

        return redirect('login');
    }

    public function showResetPassword(string $token): Response|string
    {
        return View::render('Auth::reset', [
            'title' => t('auth.reset.title'),
            'token' => $token,
            'csrf' => csrf_token(),
        ], 'layouts/auth');
    }

    public function resetPassword(Request $request): Response|string
    {
        $input = $request->only(['token', 'password', 'password_confirmation']);

        $errors = Validator::validate($input, [
            'token' => 'required',
            'password' => 'required|min_chars:' . Config::int('security.password.min_length', 8) . '|max_chars:200|confirmed',
        ], ['token' => 'Token', 'password' => t('auth.password')]);

        if ($errors !== []) {
            return $this->backWithErrors($errors, []);
        }

        if (!AuthService::resetPassword((string) $input['token'], (string) $input['password'])) {
            return $this->backWithErrors(['token' => [t('auth.reset.token_invalid')]], []);
        }

        Session::flash('success', t('auth.reset.success'));

        return redirect('login');
    }

    /**
     * Ganti tenant/konteks perusahaan yang aktif.
     */
    public function switchCompany(Request $request): Response|string
    {
        $companyId = (int) $request->input('company_id', 0);

        if (TenantContext::id() !== null && TenantContext::setCompany($companyId)) {
            Session::flash('success', t('common.company_switched'));
        } else {
            Session::flash('error', t('common.company_unavailable'));
        }

        return back();
    }

    /**
     * Ganti bahasa antarmuka.
     */
    public function switchLocale(Request $request): Response|string
    {
        $locale = (string) $request->input('locale', '');

        if (in_array($locale, Translator::available(), true)) {
            Translator::setLocale($locale);
            Session::set('mo.locale', $locale);

            if (Auth::check()) {
                Database::updateRow('users', ['locale' => $locale], 'id = ?', [Auth::id()]);
            }
        }

        return back();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function publicPlans(): array
    {
        $rows = Database::select('SELECT code, name, description, monthly_price, annual_price, currency, employee_limit, company_limit FROM plans WHERE is_public = 1 AND is_active = 1 ORDER BY sort_order') ?? [];

        $plans = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $plans[] = [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'description' => (string) ($row['description'] ?? ''),
                'monthly_price' => (int) (float) $row['monthly_price'],
                'annual_price' => (int) (float) $row['annual_price'],
                'currency' => (string) ($row['currency'] ?? 'IDR'),
                'employee_limit' => $row['employee_limit'] !== null ? (int) $row['employee_limit'] : null,
                'company_limit' => $row['company_limit'] !== null ? (int) $row['company_limit'] : null,
            ];
        }

        return $plans;
    }

    /**
     * Pilih tenant pertama yang tersedia agar pengguna tidak terjebak tanpa konteks.
     */
    private function selectFirstTenant(int $userId): void
    {
        $tenants = TenantContext::availableTenants($userId);

        if ($tenants !== []) {
            TenantContext::setTenant((int) $tenants[0]['id'], $userId);
        }
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $input
     */
    private function backWithErrors(array $errors, array $input): Response|string
    {
        Session::flash('errors', $errors);
        Session::flash('old', $input);

        return back();
    }
}
