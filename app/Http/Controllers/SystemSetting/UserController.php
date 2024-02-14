<?php

namespace App\Http\Controllers\SystemSetting;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use App\Models\User;
use DB;

class UserController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:users.view')->only('index', 'show');
        $this->middleware('permission:users.store')->only('store');
        $this->middleware('permission:users.update')->only('update');       
        $this->middleware('permission:users.reset-password')->only('password');       
        $this->middleware('permission:users.edit-users')->only('editUser');             
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{

            $users = User::with('roles')->orderBy('id')->get();
            $roles = Role::orderBy('id')->get();
            if($users->isEmpty()){
                $users = [];
            }   
                return response([
                    'message' => 'success',
                    'user' => $users,
                    'roles' => $roles,
                ],200);
        }catch(Exception $e){
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // return response($request->all());
        $this->validate($request, [
            'full_name' => 'required',
        ]);

        DB::beginTransaction();

        try{
            $user = new User;
            $user->name = $request->full_name;
            $user->contact_no = $request->contact_no;
            $user->email = $request->email;
            $user->username = $request->username;
            $user->password = Hash::make($request->password);
            $user->designation = $request->designation;
            $user->save();

            //to assign role to this user
            $roles = $request->roles;
            $user->syncRoles($roles);

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'User has been created Successfully'
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editUser($id)
    {
        try{
            $user = User::with('roles')->find($id);
                
            if(!$user){
                return response()->json([
                    'message' => 'The User you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'user' =>$user
            ],200);
        }catch(Exception $e){
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'full_name' => 'required',
        ]);
        DB::beginTransaction();
        try{
            $user = User::find($id);
            
            if(!$user){
                return response()->json([
                    'message' => 'The User you are trying to update doesn\'t exist.'
                ], 404);
            }

            $user->name = $request->full_name;
            $user->contact_no = $request->contact_no;
            $user->email = $request->email;
            $user->username = $request->username;
            $user->designation = $request->designation;
            $user->save();

            //to assign role to this user
            $roles = $request->roles;
            $user->syncRoles($roles);

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'User has been updated Successfully'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {

            User::find($id)->delete(); 

            return response()->json([
                'message' => 'User deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([                           
                'message' => 'User cannot be delete. Already used by other records.'
            ], 202);
        }
    }

    public function password($id,Request $request){
        try {

            $user = User::find($id);
          
            if (!$user) {
                return response([
                    'message' => 'User does not exist'                   
                ], 200);
            }
            else{           
                $user->password = Hash::make($request->password);          
                $user->save();

                return response([
                    'message' => 'Password updated successfully'
                ], 200);

            }
        
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
