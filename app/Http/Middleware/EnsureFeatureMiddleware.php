<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Menu, rute, dan job modul add-on hanya tersedia bila modul aktif untuk tenant (S14). */
final class EnsureFeatureMiddleware
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $school = $request->user()?->school;
        if ($school === null || ! $school->hasFeature($feature)) {
            return redirect()->route('admin.modules')->withErrors([
                'feature' => 'Modul '.Features::label($feature).' belum aktif untuk sekolah ini. Data lama tetap tersimpan dan dapat diekspor.',
            ]);
        }

        return $next($request);
    }
}
