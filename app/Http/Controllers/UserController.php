<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Filters\UserFilter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponses;

    public function index(UserFilter $filters)
    {
        $query = $filters->apply(User::query());
        $users = $query->paginate(10);

        return $this->success($users, "Filter user success", 200);
    }

    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->error("User not found", 404);
        }

        return $this->success($user, "User found successfully", 200);
    }

    public function store()
    {
        $user = User::create([
            'name' => request('name'),
            'email' => request('email'),
            'password' => bcrypt(request('password'))
        ]);

        if (request()->has('role')) {
            $role = Role::where('slug', request('role'))->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }
        }

        return $this->success($user->load('roles'), "Create user success", 201);
    }

    public function update($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error("User not found", 404);
        }

        $user->update([
            'name' => request('name'),
            'email' => request('email'),
        ]);

        return $this->success($user, "User updated successfully", 200);
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error("User not found", 404);
        }

        $user->delete();

        $users = User::paginate(10);

        return $this->success($users, "User deleted successfully", 200);
    }
}
