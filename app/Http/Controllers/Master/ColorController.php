<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Color;
use Illuminate\Support\Facades\DB;

class ColorController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:colors.view')->only('index', 'show');
        $this->middleware('permission:colors.store')->only('store');
        $this->middleware('permission:colors.update')->only('update');
        $this->middleware('permission:colors.edit-colors')->only('editColor');
    }

    public function index()
    {
        try{

            $colors = Color::orderBy('id')->get();
            if($colors->isEmpty()){
                $colors = [];
            }
                return response([
                    'message' => 'success',
                    'color' =>$colors
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
            'name' => 'required',
        ]);

        DB::beginTransaction();

        try{
            $color = new Color;
            $color->name = $request->name;
            $color->description = $request->description;
            $color->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Color has been created Successfully'
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
    public function editColor($id)
    {
        try{
            $color = Color::find($id);

            if(!$color){
                return response()->json([
                    'message' => 'The Color you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'color' =>$color
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
            'name' => 'required',
        ]);
        DB::beginTransaction();
        try{
            $color = Color::find($id);

            if(!$color){
                return response()->json([
                    'message' => 'The Color you are trying to update doesn\'t exist.'
                ], 404);
            }

            $color->name = $request->name;
            $color->description = $request->description;
            $color->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Color has been updated Successfully'
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

            Color::find($id)->delete();

            return response()->json([
                'message' => 'Color deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Color cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}
