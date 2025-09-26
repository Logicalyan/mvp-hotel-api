<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponses;

    public function index() {
        $roles = Role::select('id', 'name', 'slug')->get();
        return $this->success($roles, "Roles retrivied Successfully", 200);
    }
}
