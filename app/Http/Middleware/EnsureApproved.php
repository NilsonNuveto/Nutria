<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->status === 'approved' && $request->user()->setup_token === null, 403, 'Seu cadastro ainda não está liberado.');

        return $next($request);
    }
}
