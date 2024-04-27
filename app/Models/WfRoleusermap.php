<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WfRoleusermap extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $connection = "pgsql_master";
    /**
     * |
     */
    public function store($req)
    {
        return WfRoleusermap::create($req);
    }

    /**
     * |
     */
    public function getRoleIdByUserId($userId)
    {
        $roles = WfRoleusermap::select('id', 'wf_role_id', 'user_id')
            ->where('user_id', $userId)
            ->where('is_suspended', false)
            ->get();
        return $roles;
    }

    /**
     * | Get Role details by User Id
     */
    public function getRoleDetailsByUserId($userId)
    {
        return WfRoleusermap::select(
            'wf_roles.role_name AS role',
            'wf_roles.id AS roleId'
        )
            ->join('wf_roles', 'wf_roles.id', '=', 'wf_roleusermaps.wf_role_id')
            ->where('wf_roleusermaps.user_id', $userId)
            ->where('wf_roleusermaps.is_suspended', false)
            ->orderByDesc('wf_roles.id')
            ->first();
    }
}
