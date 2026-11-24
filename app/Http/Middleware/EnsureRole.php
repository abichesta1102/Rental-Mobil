<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_blacklisted) {
            abort(403, 'Akun Anda sedang diblacklist. Alasan: ' . ($user->blacklist_reason ?? 'Pelanggaran ketentuan rental.'));
        }

        if (! in_array($user->role, $roles, true)) {
            $requiredRoles = implode(', ', $roles);
            abort(403, "Akses ditolak: Halaman ini memerlukan hak akses [{$requiredRoles}]. Anda saat ini masuk sebagai [{$user->role}].");
        }

        return $next($request);
    }
}
