<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Filters\UserFilter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    use ApiResponses;

    public function index(UserFilter $filters)
    {
        $baseQuery = User::query()->with('roles');
        $query = $filters->apply($baseQuery);
        $perPage = request()->get('per_page', 10);
        $perPage = min(max((int) $perPage, 1), 100);
        $users = $query->paginate($perPage);
        return $this->success($users, "Filter user success", 200);
    }

    public function show($id)
    {
        $query = User::where("id", $id);
        $user = $query->first();

        if (!$user) {
            return $this->error("User not found", 404);
        }

        return $this->success($user, "User found successfully", 200);
    }

    public function store(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $validate['password'] = Hash::make($validate['password']);
        $user = User::create($validate);

        if (request()->has('role')) {
            $role = Role::where('slug', $request->role)->first();
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

        $query = User::where("id", $user->id);
        $user = $query->first();

        return $this->success($user, "User updated successfully", 200);
    }

    public function destroy(UserFilter $filters, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->error("User not found", 404);
        }

        $user->delete();

        $query = $filters->apply(User::query());
        $users = $query->paginate(10);

        return $this->success($users, "User deleted successfully", 200);
    }
}
