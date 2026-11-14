<?php

namespace App\Http\Controllers;

use App\Services\Tools\ToolRegistry;

/**
 * The free tools: converter, compressor, QR generator and editor.
 *
 * These pages are static shells. All of the work happens in the visitor's
 * browser (see public/ico-ext/js), so there is nothing to validate, store
 * or rate-limit here — no file ever reaches this server.
 */
class ToolController extends Controller
{
    public function __construct(
        private readonly ToolRegistry $tools,
    ) {}

    public function index()
    {
        return view('tools.index', [
            'tools' => $this->tools->all(),
        ]);
    }

    public function show(string $tool)
    {
        // The route already restricts {tool} to the registry's keys; this
        // guards against the two lists drifting apart.
        $current = $this->tools->find($tool) ?? abort(404);

        return view('tools.'.$tool, [
            'tool' => $current,
            'tools' => $this->tools->all(),
        ]);
    }
}
