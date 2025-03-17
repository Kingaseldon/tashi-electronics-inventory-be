<?php

namespace App\Http\Controllers\SystemSetting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:permission.refresh')->only('refresh');
        $this->middleware('permission:permission.index')->only('index');
    }

    public function index()
    {
        try{

            $permissions = Permission::with('roles')->orderBy('id')->get();
            if($permissions->isEmpty()){
                $permissions = [];
            }
                return response([
                    'message' => 'success',
                    'permission' =>$permissions
                ],200);

        }catch(\Exception $e){
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function refresh(Request $request)
    {
        try{
        //get the permissions from all routes through their middleware
        $permissions = [];
        $routes = app('router')->getRoutes();
        //loop through each router and gather the middleware for each route
        foreach ($routes as $route) {
            $middlewares = $route->gatherMiddleware();
            if (!empty($middlewares)) {
                foreach ($middlewares as $middleware) {
                    //check if $middleware is string and has permission: pattern
                    if (is_string($middleware) && preg_match('/.*permission:(.*)$/', $middleware, $matches) === 1) {
                        //permission middleware may have many OR operators eg: permission:user_add|user_edit, we separate those individual permission
                        $matches = explode('|', $matches[1]);
                        foreach ($matches as $match) {
                            //if permission not already in the permissions array, add it
                            if (!in_array($match, $permissions)) {
                                $permissions[] = $match;
                            }
                        }
                    }
                }
            }
        }
        //add these permissions to the database if they do not already exist
        if (!empty($permissions)){
            foreach ($permissions as $permission) {
                Permission::updateOrCreate([
                    'name' => $permission
                ], [
                    'name' => $permission
                ]);
            }
        }
        //delete existing permissions in the database that are not found from routes
        Permission::whereNotIn('name', $permissions)->delete();
        return response([
            'message' => 'The permissions table has been regenerated',

        ],200);
    }catch(\Exception $e){
        return response([
            'message' => $e->getMessage()
        ], 400);
    }
    }
}
