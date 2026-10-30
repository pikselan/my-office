<?php

declare(strict_types=1);

/**
 * Definisi menu sidebar.
 *
 * Item hanya ditampilkan bila:
 *  1. route-nya sudah terdaftar (mencegah menu yang tidak berfungsi), dan
 *  2. pengguna memiliki permission yang disyaratkan (bila ada).
 *
 * Dengan aturan ini, menu bertambah otomatis seiring selesainya setiap modul.
 */

use App\Core\App;
use App\Core\Authorization;
use App\Core\Request;

if (!function_exists('mo_icon')) {
    /**
     * Ikon SVG inline (tanpa dependensi eksternal).
     */
    function mo_icon(string $name, int $size = 18): string
    {
        $paths = [
            'dashboard'  => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
            'building'   => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10"/><path d="M8 7h4M8 11h4M8 15h4M2 21h20"/>',
            'sitemap'    => '<rect x="9" y="2" width="6" height="5" rx="1"/><rect x="2" y="17" width="6" height="5" rx="1"/><rect x="16" y="17" width="6" height="5" rx="1"/><path d="M12 7v5M5 17v-2a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/>',
            'users'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
            'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>',
            'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
            'megaphone'  => '<path d="m3 11 18-6v12L3 15Z"/><path d="M11 6v12"/><path d="M7 12v6a2 2 0 0 0 2 2h1"/>',
            'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'sun'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/>',
            'briefcase'  => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M2 13h20"/>',
            'wallet'     => '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M16 12h4"/>',
            'chart'      => '<path d="M3 3v18h18"/><path d="m7 14 3-4 3 3 5-6"/>',
            'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
            'server'     => '<rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 7h.01M6 17h.01"/>',
            'bell'       => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
            'chevron'    => '<path d="m6 9 6 6 6-6"/>',
            'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
            'globe'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
            'menu'       => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'check'      => '<path d="m5 13 4 4L19 7"/>',
        ];

        $path = $paths[$name] ?? $paths['dashboard'];

        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }
}

if (!function_exists('mo_menu')) {
    /**
     * Kelompok menu yang boleh ditampilkan untuk pengguna aktif.
     *
     * @return list<array{label: string, icon: string, items: list<array{label: string, href: string, icon: string, active: bool}>}>
     */
    function mo_menu(string $currentPath): array
    {
        $router = App::instance()->router();

        $groups = [
            [
                'label' => t('nav.dashboard'),
                'items' => [
                    ['label' => t('nav.dashboard'), 'route' => 'dashboard', 'icon' => 'dashboard', 'permission' => null],
                    ['label' => t('nav.announcements'), 'route' => 'announcements.index', 'icon' => 'megaphone', 'permission' => 'announcement.view'],
                ],
            ],
            [
                'label' => t('nav.companies'),
                'items' => [
                    ['label' => t('nav.companies'), 'route' => 'companies.index', 'icon' => 'building', 'permission' => 'company.view'],
                    ['label' => t('nav.organization'), 'route' => 'organization.index', 'icon' => 'sitemap', 'permission' => 'organization.view'],
                ],
            ],
            [
                'label' => t('nav.employees'),
                'items' => [
                    ['label' => t('nav.employees'), 'route' => 'employees.index', 'icon' => 'users', 'permission' => 'employee.view'],
                    ['label' => t('nav.onboarding'), 'route' => 'onboarding.index', 'icon' => 'user-check', 'permission' => 'onboarding.view'],
                    ['label' => t('nav.users'), 'route' => 'users.index', 'icon' => 'shield', 'permission' => 'user.manage'],
                    ['label' => t('nav.roles'), 'route' => 'roles.index', 'icon' => 'shield', 'permission' => 'role.manage'],
                ],
            ],
            [
                'label' => t('nav.attendance'),
                'items' => [
                    ['label' => t('nav.attendance'), 'route' => 'attendance.index', 'icon' => 'clock', 'permission' => 'attendance.self.view'],
                    ['label' => t('nav.schedules'), 'route' => 'schedules.index', 'icon' => 'calendar', 'permission' => 'schedule.manage'],
                    ['label' => t('nav.holidays'), 'route' => 'holidays.index', 'icon' => 'sun', 'permission' => 'holiday.manage'],
                    ['label' => t('nav.leave'), 'route' => 'leave.index', 'icon' => 'calendar', 'permission' => 'leave.self.view'],
                ],
            ],
            [
                'label' => t('nav.recruitment'),
                'items' => [
                    ['label' => t('nav.recruitment'), 'route' => 'recruitment.index', 'icon' => 'briefcase', 'permission' => 'recruitment.view'],
                ],
            ],
            [
                'label' => t('nav.payroll'),
                'items' => [
                    ['label' => t('nav.payroll'), 'route' => 'payroll.index', 'icon' => 'wallet', 'permission' => 'payroll.view'],
                    ['label' => t('nav.finance'), 'route' => 'finance.index', 'icon' => 'wallet', 'permission' => 'finance.reimbursement.manage'],
                ],
            ],
            [
                'label' => t('nav.reports'),
                'items' => [
                    ['label' => t('nav.reports'), 'route' => 'reports.index', 'icon' => 'chart', 'permission' => 'report.view'],
                    ['label' => t('nav.settings'), 'route' => 'settings.index', 'icon' => 'settings', 'permission' => 'tenant.view'],
                ],
            ],
        ];

        if (Authorization::isPlatformAdmin()) {
            $groups[] = [
                'label' => t('nav.platform'),
                'items' => [
                    ['label' => t('nav.platform'), 'route' => 'platform.index', 'icon' => 'server', 'permission' => null],
                ],
            ];
        }

        $result = [];

        foreach ($groups as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                // Menu hanya muncul bila route sudah terdaftar.
                if (!$router->hasRoute($item['route'])) {
                    continue;
                }

                if ($item['permission'] !== null && !Authorization::can($item['permission'])) {
                    continue;
                }

                $href = $router->url($item['route']);
                $items[] = [
                    'label' => $item['label'],
                    'href' => $href === '' ? '/' : $href,
                    'icon' => $item['icon'],
                    'active' => $currentPath === $href || ($href !== '/' && str_starts_with($currentPath, $href)),
                ];
            }

            if ($items !== []) {
                $result[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $result;
    }
}
