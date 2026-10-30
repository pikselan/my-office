<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pagination server-side.
 *
 * Menghasilkan tautan dengan mempertahankan parameter pencarian dan filter
 * yang sudah dipakai, sehingga pagination benar-benar mengubah data.
 */
final class Paginator
{
    private int $total;
    private int $perPage;
    private int $currentPage;
    private string $baseUrl;
    /** @var array<string, mixed> */
    private array $query;

    /**
     * @param array<string, mixed> $query
     */
    public function __construct(int $total, int $perPage = 25, int $currentPage = 1, string $baseUrl = '', array $query = [])
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->baseUrl = $baseUrl;
        $this->query = $query;
    }

    /**
     * Bangun dari request aktif.
     */
    public static function fromRequest(int $total, ?Request $request = null, int $perPage = 25): self
    {
        $request ??= App::isBooted() ? App::instance()->currentRequest() : null;

        $page = 1;
        $query = [];

        if ($request !== null) {
            $page = max(1, $request->query('page') !== null ? (int) $request->query('page') : 1);
            $perPage = $request->query('per_page') !== null ? max(1, min(100, (int) $request->query('per_page'))) : $perPage;
            $query = array_filter($request->query ?? [], static fn ($value): bool => $value !== null && $value !== '');
            unset($query['page']);
        }

        return new self($total, $perPage, $page, $request !== null ? $request->path() : '', $query);
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return min($this->currentPage, $this->lastPage());
    }

    public function lastPage(): int
    {
        return (int) max(1, ceil($this->total / $this->perPage));
    }

    public function offset(): int
    {
        return ($this->currentPage() - 1) * $this->perPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    public function url(int $page): string
    {
        $params = array_merge($this->query, ['page' => $page]);
        return $this->baseUrl . '?' . http_build_query($params);
    }

    public function render(): string
    {
        if (!$this->hasPages()) {
            if ($this->total > 0) {
                return '<div class="mo-pagination"><span class="mo-pagination__info">'
                    . htmlspecialchars('Menampilkan ' . $this->from() . '–' . $this->to() . ' dari ' . $this->total . ' data', ENT_QUOTES, 'UTF-8')
                    . '</span></div>';
            }
            return '';
        }

        $current = $this->currentPage();
        $last = $this->lastPage();

        $html = '<div class="mo-pagination"><span class="mo-pagination__info">Menampilkan '
            . $this->from() . '–' . $this->to() . ' dari ' . $this->total . ' data</span><div class="mo-pagination__links">';

        $html .= $this->link($current - 1, '‹', $current <= 1);

        $start = max(1, $current - 2);
        $end = min($last, $start + 4);
        $start = max(1, $end - 4);

        if ($start > 1) {
            $html .= $this->link(1, '1', false);
            if ($start > 2) {
                $html .= '<span class="mo-page-link is-disabled">…</span>';
            }
        }

        for ($page = $start; $page <= $end; $page++) {
            $html .= $this->link($page, (string) $page, false, $page === $current);
        }

        if ($end < $last) {
            if ($end < $last - 1) {
                $html .= '<span class="mo-page-link is-disabled">…</span>';
            }
            $html .= $this->link($last, (string) $last, false);
        }

        $html .= $this->link($current + 1, '›', $current >= $last);
        $html .= '</div></div>';

        return $html;
    }

    private function link(int $page, string $label, bool $disabled, bool $active = false): string
    {
        $classes = 'mo-page-link' . ($active ? ' is-active' : '') . ($disabled ? ' is-disabled' : '');

        if ($disabled) {
            return '<span class="' . $classes . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return '<a class="' . $classes . '" href="' . htmlspecialchars($this->url($page), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }
}
