<?php

namespace App\Http\Controllers;

use App\ApiResponses;
use App\Filters\UserFilter;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{

    use ApiResponses;

    public function index(UserFilter $filters)
    {
        $query = (new UserFilter(request()))->apply(User::query());
        $user = $query->paginate(10);
        return $this->success($user, "Filter user success", 200);
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

        return $this->success($user, "Create user success", 201);
    }

    public function update($id)
    {
        $user = User::find($id);
        $user->update([
            'name' => request('name'),
            'email' => request('email'),
        ]);

        return $this->success($user, "User updated successfully", 201);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return $this->success($user, "User deleted successfully", 200);
    }
}
