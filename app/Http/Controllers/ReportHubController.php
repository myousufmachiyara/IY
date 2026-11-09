<?php

namespace App\Http\Controllers;

use App\Support\ReportCatalog;
use Illuminate\Http\Request;

class ReportHubController extends Controller
{
    /** Landing page listing every report the user may open, grouped by category. */
    public function index(Request $request)
    {
        ReportCatalog::syncPermissions();
        $categories = ReportCatalog::visibleTo($request->user());
        abort_if(empty($categories), 403, 'You do not have access to any reports.');

        return view('reports.index', compact('categories'));
    }

    /** Sidebar category link: opens the category's first report, whose tabs list the rest. */
    public function category(Request $request, string $category)
    {
        $first = ReportCatalog::firstFor($request->user(), $category);
        abort_unless($first, 403, 'You do not have access to any report in this category.');

        return redirect()->route($first['route'], $request->query());
    }
}
