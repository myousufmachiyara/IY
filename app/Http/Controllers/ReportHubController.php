<?php

namespace App\Http\Controllers;

use App\Support\ReportCatalog;
use Illuminate\Http\Request;

class ReportHubController extends Controller
{
    /** Landing page listing every report the user may open, grouped by category. */
    public function index(Request $request)
    {
        $categories = ReportCatalog::visibleTo($request->user());
        abort_if(empty($categories), 403, 'You do not have access to any reports.');

        return view('reports.index', compact('categories'));
    }
}
