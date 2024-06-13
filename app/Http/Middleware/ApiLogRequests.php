<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Created On-02-07-2022 
 * Created By-Anshu Kumar
 * ----------------------------------------------------------------------------------------------------
 * Middleware Creates logs of the various api responses and request, their start time, end time 
 * 
 */
class ApiLogRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $request->start = microtime(true);
        return $next($request);
    }

    /**
     * Handle an response time.
     * 
     * @param Illuminate\Http\Request $request
     * @return log function 
     */
    public function terminate($request, $response)
    {
        $request->end = microtime(true);
        $this->log($request, $response);
    }
    /**
     * Create Log in storage/logs/laravel.log 
     */

    protected function log($request, $response)
    {
        $request->end = microtime(true);

        $startTimeReadable = date('Y-m-d H:i:s', $request->start);
        $endTimeReadable = date('Y-m-d H:i:s', $request->end);

        $duration = $request->end - $request->start;
        $url = $request->fullUrl();
        $method = $request->getMethod();
        $ip = $request->getClientIp();

        $filteredRequest = $request->except(['authRequired', 'token','auth']);

        // Get authenticated user data
        $user = authUser($request);
        $userId = $user ? $user->id : 'Guest';
        $userName = $user ? $user->name : 'Guest';
        $log = "{$ip}: {$method}@{$url} - {$duration}ms \n" .
            "StartTime: {$startTimeReadable} \n" .
            "EndTime: {$endTimeReadable} \n" .
            "UserId: {$userId} \n" .
            "UserName: {$userName} \n" .
            "Request : " . json_encode($filteredRequest) . " \n" .
            "Response : {$response->getContent()} \n";
        // Log::info($log);
        Log::channel('apilogs')->info($log);
        // Log::channel('apilog')->error($log);
    }
}
