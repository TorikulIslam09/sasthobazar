<?php
declare(strict_types=1);

/** JSON API for cascading Division > District > Upazila dropdowns. */
class LocationController extends Controller
{
    /** GET /api/locations            -> divisions
     *  GET /api/locations/{parentId} -> children */
    public function index(string $parentId = '0'): void
    {
        $id = (int) $parentId;
        $rows = $id > 0 ? Location::children($id) : Location::divisions();
        $bn = lang() === 'bn';
        $this->json(array_map(static fn($r) => [
            'id'   => (int) $r['id'],
            'name' => $bn && $r['name_bn'] ? $r['name_bn'] : $r['name_en'],
        ], $rows));
    }
}
