<?php

namespace App\Http\Controllers;

use App\Services\Catalogue\NavTabs;
use App\Services\Tools\ToolRegistry;

/**
 * /icons, /illustrations, /animations and /3d-assets.
 *
 * Short, permanent addresses for the header tabs. A tab whose asset type
 * already has published assets forwards to the filtered browse page; one
 * that is still empty shows a "coming soon" page instead of an empty grid.
 */
class CatalogueTabController extends Controller
{
    public function __invoke(string $tab, NavTabs $tabs, ToolRegistry $tools)
    {
        $current = $tabs->find($tab) ?? abort(404);

        if ($current['ready']) {
            // Temporary on purpose: the destination depends on what is in
            // the catalogue today, so browsers must not memorise it.
            return redirect()->to($current['url'], 302);
        }

        return view('catalogue.coming-soon', [
            'tab' => $current,
            'readyTabs' => array_values(array_filter($tabs->all(), fn ($t) => $t['ready'])),
            'tools' => $tools->all(),
        ]);
    }
}
