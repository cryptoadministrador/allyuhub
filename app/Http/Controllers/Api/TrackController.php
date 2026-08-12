<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Models\TrackPhase;

/**
 * API de trayectos: ordinaria (ORD) y PCEI/EPJA.
 * El mismo grafo de objetivos, recorridos distintos.
 */
class TrackController extends Controller
{
    /** GET /api/v1/tracks — todos los trayectos con sus fases. */
    public function index()
    {
        return Track::with(['phases' => fn ($q) => $q
            ->select('id', 'track_id', 'seq', 'label', 'duration_months',
                'is_propedeutic', 'grade_node_id')
            ->withCount('objectives')])
            ->get();
    }

    /** GET /api/v1/tracks/{code} — un trayecto por código (ORD, PCEI-BI…). */
    public function show(string $code)
    {
        return Track::where('code', $code)
            ->with(['phases' => fn ($q) => $q->withCount('objectives')
                ->with('gradeNode:id,title,native_code,attrs')])
            ->firstOrFail();
    }

    /** GET /api/v1/phases/{phase}/objectives — la dosificación de una fase. */
    public function phaseObjectives(TrackPhase $phase)
    {
        return $phase->objectives()
            ->with('node:id,title,path')
            ->paginate(50);
    }
}
