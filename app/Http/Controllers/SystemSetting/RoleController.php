<?php

namespace App\Http\Controllers\SystemSetting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:roles.view')->only('index', 'show');
        $this->middleware('permission:roles.store')->only('store');
        $this->middleware('permission:roles.update')->only('update');
        $this->middleware('permission:roles.edit-roles')->only('editRole');
        // $this->middleware('permission:roles.roles-base')->only('getRoleBase');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $roles = Role::with('users')->orderBy('is_super_user', 'desc')->orderBy('name')->get();
            if($roles->isEmpty()){
                $roles = [];
            }
                return response([
                    'message' => 'success',
                    'role' => $roles
                ],200);
        }catch(\Exception $e){
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
        $this->validate($request, [
            'role_name' => 'required',
        ]);

        DB::beginTransaction();

        try{
            $role = new Role;
            $role->name = $request->role_name;
            $role->description = $request->description;
            $role->save();

        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Role  has been created Successfully'
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
        try{
            $role = Role::with('permissions')->findOrFail($id);

            //get all permissions
            $rawPermissions = Permission::orderBy('name')->get();
            $permissions = [];
            if ($rawPermissions->count()) {
                foreach ($rawPermissions as $rawPermission) {
                    $permission = explode('.', $rawPermission->name);
                    $permissions[$permission[0]][] = [
                        'id' => $rawPermission->id,
                        'name' => isset($permission[1]) ? $permission[1] : 'ALL' //some permissions may not have an action, so set the name to ALL for displaying
                    ];
                }
            }
            $rolePermissions = $role->permissions->pluck('id')->toArray();
            return response([
                'message' => 'success',
                'role' => $role,
                'permissions' => $permissions,
                'rolePermission' => $rolePermissions,
            ],200);

        } catch(\Exception $e){
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editRole($id)
    {
        //
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
        // return response()->json($id);
        DB::beginTransaction();
        try{
            $role = Role::findOrFail($id);
            $validPermissionIds = Permission::get()->pluck('id')->toArray();
            // $this->validate($request, [
            //     'role_name' => 'required|unique:roles,name,' . $role->id,
            //     'description' => 'required',
            //     'permissions' => 'array|nullable'
            // ]);

            $role->name = $request->role_name;
            $role->description = $request->description;
            $role->save();

            //create permissions
            // return response($permissions);
            $permissions = [];
            if ($request->has('permissions') && is_array($request->permissions)) {
                foreach ($request->permissions as $permission) {
                    if (in_array($permission, $validPermissionIds)) {
                        $permissions[] = $permission;
                    }
                }
            }
            $role->syncPermissions($permissions);

        } catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Role has been updated Successfully'
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

            $role = Role::findOrFail($id);
            $role->permissions()->delete();
            $role->delete();

            return response()->json([
                'message' => 'Role has been deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Role cannot be delete. Already used by other records.'
            ], 202);
        }
    }

    public function getRoleBase($id)
    {
        try {
            $roleBases = Role::with('permissions')->findOrFail($id);

            if(!$roleBases){
                return response()->json([
                    'message' => 'The Role you are searching  doesn\'t exist.'
                ], 404);
            }
                return response([
                    'message' => 'success',
                    'roleBases' => $roleBases
                ],200);
        }catch(\Exception $e){
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
