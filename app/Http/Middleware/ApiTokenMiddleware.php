<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization');
        
        // Cek apakah token ada di header Authorization
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'API Token is required. Please provide Authorization header.'
            ], 401);
        }

        // Hapus prefix "Bearer " jika ada
        $token = str_replace('Bearer ', '', $token);
        
        // Validasi token dengan yang ada di .env
        $validToken = env('API_TOKEN');
        
        if ($token !== $validToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API Token.'
            ], 403);
        }

        return $next($request);
    }
}