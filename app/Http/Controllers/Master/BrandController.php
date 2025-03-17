<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
     public function __construct()
     {
         $this->middleware('permission:brands.view')->only('index', 'show');
         $this->middleware('permission:brands.store')->only('store');
         $this->middleware('permission:brands.update')->only('update');
         $this->middleware('permission:brands.edit-brands')->only('editBrand');
     }
    public function index()
    {
        try{
            $brands = Brand::orderBy('id')->get();
            if($brands->isEmpty()){
                $brands = [];
            }
                return response([
                    'message' => 'success',
                    'brand' =>$brands
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
            $brand = new Brand;
            $brand->name = $request->name;
            $brand->description = $request->description;
            $brand->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Brand has been created Successfully'
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
    public function editBrand($id)
    {
        try{
            $brand = Brand::find($id);

            if(!$brand){
                return response()->json([
                    'message' => 'The Brand you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'brand' =>$brand
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
            $brand = Brand::find($id);

            if(!$brand){
                return response()->json([
                    'message' => 'The Brand you are trying to update doesn\'t exist.'
                ], 404);
            }

            $brand->name = $request->name;
            $brand->description = $request->description;
            $brand->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([
                'message'=> $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Brand has been updated Successfully'
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

            Brand::find($id)->delete();

            return response()->json([
                'message' => 'Brand deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Brand cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}
