<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssignRegionExtension;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssigningController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:assignings.view')->only('index', 'show');
        $this->middleware('permission:assignings.update')->only('update');
        $this->middleware('permission:assignings.edit-assignings')->only('changeAssignRegion');
        $this->middleware('permission:assignings.edit-assignings')->only('editAssigning');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $employees = User::whereHas('roles', function ($query) {
                                        $query->where('is_super_user', '!=', 1);
                                    })->with('assignAndEmployee.region', 'assignAndEmployee.extension')->orderBy('id')->get();
            return response($employees);
            if($employees->isEmpty()){
                $employees = [];
            }
                return response([
                    'message' => 'success',
                    'employees' => $employees,
                ],200);
        }catch(\Exception $e){
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try{
        $assignAndEmployee = AssignRegionExtension::with('user')->findOrFail($id);
        $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);

        if(!$assignAndEmployee){
            return response()->json([
                'message' => 'The assign employee you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'assignAndEmployee' => $assignAndEmployee,
                'regions' => $regions,
            ],200);
        }catch(\Exception $e){
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
    public function editAssigning($id)
    {
        try{
            $employee = User::whereHas('roles', function ($query) {
                                        $query->where('is_super_user', '!=', 1);
                                    })->with('assignAndEmployee')->find($id);
            $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);

            if(!$employee){
                return response()->json([
                    'message' => 'The Employee you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'employee' => $employee,
                'region' => $regions,
            ],200);
        }catch(\Exception $e){
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
            'assign_type' => 'required',
        ]);

        DB::beginTransaction();
        try{
            $employee = User::findOrFail($id);

            if(!$employee){
                return response()->json([
                    'message' => 'The Employee you are trying to update doesn\'t exist.'
                ], 404);
            }

            $assignRegion = new AssignRegionExtension;

            //getting site, region and assign with respect to asign type
            if($request->assign_type == 'assign both')
            {
                $region = $request->region;
                $extension = $request->extension;
                $assign = 1;
            }elseif($request->assign_type == 'assign regional'){
                $region = $request->region;
                $extension = null;
                $assign = 1;
            }elseif($request->assign_type == 'assign extension'){
                $region = null;
                $extension = $request->extension;
                $assign = 1;
            }else{
                $region = null;
                $extension = null;
                $assign = 0;
            }


            $assignRegion->assign_type = $request->assign_type;
            $assignRegion->regional_id = $region;
            $assignRegion->extension_id = $extension;
            $assignRegion->is_assign = $assign;
            $assignRegion->user_id = $employee->id;
            $assignRegion->save();
        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'You have assign the employee Successfully'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function changeAssignRegion($id, Request $request)
    {
        $this->validate($request, [
            'assign_type' => 'required',
        ]);

        DB::beginTransaction();
        try{
            $assignAndEmployee = AssignRegionExtension::findOrFail($id);

            if(!$assignAndEmployee){
                return response()->json([
                    'message' => 'The Employee asssign you are trying to update doesn\'t exist.'
                ], 404);
            }

            //getting site, region and assign with respect to asign type
            if($request->assign_type == 'assign both'){
                $region = $request->region;
                $extension = $request->extension;
                $assign = 1;
            }elseif($request->assign_type == 'assign regional'){
                $region = $request->region;
                $extension = null;
                $assign = 1;
            }elseif($request->assign_type == 'assign extension'){
                $region = null;
                $extension = $request->extension;
                $assign = 1;
            }else{
                $region = null;
                $site = null;
                $assign = 0;
            }
            $assignAndEmployee->assign_type = $request->assign_type;
            $assignAndEmployee->regional_id = $region;
            $assignAndEmployee->extension_id = $extension;
            $assignAndEmployee->is_assign = $assign;
            $assignAndEmployee->save();
        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'You have assign the employee Successfully'
        ], 200);
    }
}
