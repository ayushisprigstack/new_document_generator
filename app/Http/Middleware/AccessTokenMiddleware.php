<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AccessTokenMiddleware
{
    public function handle($request, Closure $next)
    {       
        $accessToken = $request->header('Authorization') ?: $request->query('access_token');
        if (!$accessToken) {          
            return response()->json(['error' => 'Access token not provided.'], 401);
        }      
   
        $user = User::where('remember_token', $accessToken)->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid access token.'], 401);
        }

        Auth::setUser($user);

 
        
        return $next($request);
    }
}
