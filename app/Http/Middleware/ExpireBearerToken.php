<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

/**
 * | Author Name-Anshu Kumar
 * | Date-09-05-2023
 * | Status-Closed(09-05-2023)
 */

class ExpireBearerToken
{
    private $_user;
    private $_currentTime;
    private $_token;
    private $_lastActivity;
    private $_key;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $citizenUserType = Config::get('workflow-constants.USER_TYPES.1');
        $this->_user = auth()->user();
        $this->_token = $request->bearerToken();
        $this->_currentTime = Carbon::now();

        if ($this->_user && $this->_token) {
            if ($this->_user->user_type == $citizenUserType) {                             // If the User type is citizen
                $this->_key = 'last_activity_citizen_' . $this->_user->id;
                $this->_lastActivity = Redis::get($this->_key);                                   // Function (1.1)
                $this->validateToken();
            } else {                                                                       // If the User type is not a Citizen
                $this->_key = 'last_activity_' . $this->_user->id;
                $this->_lastActivity = Redis::get($this->_key);
                $this->validateToken();                                                     // Function (1.1)
            }

            if (!$request->has('key') && !$request->input('heartbeat'))
                Redis::set($this->_key, $this->_currentTime);            // Caching
        }
        return $next($request);
    }


    /**
     * | Validate Token (1.1)
     */
    public function validateToken()
    {
        $timeDiff = $this->_currentTime->diffInMinutes($this->_lastActivity);
        if ($this->_lastActivity && ($timeDiff > 10)) {            // for 5 Minutes
            Redis::del($this->_key);
            $this->_user->currentAccessToken()->delete();
            abort(response()->json(
                [
                    'status' => true,
                    'authenticated' => false,
                    'sessionTime' => $timeDiff
                ]
            ));
        }
    }
}
