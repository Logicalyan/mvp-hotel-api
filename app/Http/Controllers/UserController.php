<?php

namespace App\Http\Controllers;

use App\Filters\UserFilter;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(UserFilter $filters)
    {
        $query = (new UserFilter(request()))->apply(User::query());
        return $query->paginate(10);
    }
}
