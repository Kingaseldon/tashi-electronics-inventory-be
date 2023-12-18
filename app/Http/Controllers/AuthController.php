<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try{
            $username = $request->username;
            $credentials = request(['username', 'password']);
            $user = User::where('username', $username)->with('roles.permissions')->first();   
            //if no user
            if($user == null ){
             
                return response([
                    'message' => 'Credentials doesnot match',                 
                ], 404);
            }
            //checking password
            if(Hash::check($request->password, $user->password) == false){               
                 return response([
                'message' => 'Credentials doesnot match',                     
            ],406);
            }
            //authenticate user
            if (! $token = auth()->attempt($credentials)) {
                return response([
                    'message' => 'Unauthorized',                    
                ], 401);
            }
            //get login user detail
            $user = User::where('username', $username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();          
            return response([
                'message' => 'Successfully Login',
                'user' => $user,
                'token' => $token,             
            ],200);

        }catch(\Exception $e){
            return response([
                'message' => $e->getMessage()
            ], 400);
        } 
    }

    public function getUser()
    {
        return response()->json(auth()->user());
    }
}
