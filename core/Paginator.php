<?php
/**
 * Paginator
 *   $p = new Paginator($total, 20);            // page read from ?page=
 *   $rows = $db->fetchAll("... LIMIT {$p->perPage} OFFSET {$p->offset()}");
 *   echo $p->links();
 */
declare(strict_types=1);

final class Paginator
{
    public int $page;
    public int $pages;

    public function __construct(public int $total, public int $perPage = 20, ?int $page = null)
    {
        $this->perPage = max(1, min(200, $perPage));
        $this->pages = max(1, (int) ceil($total / $this->perPage));
        $page ??= (int) ($_GET['page'] ?? 1);
        $this->page = max(1, min($this->pages, $page));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** URL of $page keeping the other query parameters. */
    public function url(int $page): string
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $q = $_GET;
        $q['page'] = $page;
        unset($q['lang']);
        return $path . '?' . http_build_query($q);
    }

    public function links(): string
    {
        return $this->pages > 1 ? View::partial('partials/pagination', ['p' => $this]) : '';
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }
}
