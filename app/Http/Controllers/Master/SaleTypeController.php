<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SaleType;
use DB;

class SaleTypeController extends Controller
{
    public function __construct()
    {
         $this->middleware('permission:sale-types.view')->only('index', 'show');
         $this->middleware('permission:sale-types.store')->only('store');
         $this->middleware('permission:sale-types.update')->only('update');       
         $this->middleware('permission:sale-types.edit-sale-types')->only('editSaleType');       
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{

            $saleTypes = SaleType::orderBy('id')->get();
            if($saleTypes->isEmpty()){
                $saleTypes = [];
            }   
                return response([
                    'message' => 'success',
                    'saleType' =>$saleTypes
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
        $this->validate($request, [
            'name' => 'required',
        ]);

        DB::beginTransaction();

        try{
            $saleType = new SaleType;
            $saleType->name = $request->name;
            $saleType->description = $request->description;
            $saleType->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Sale Type has been created Successfully'
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
    public function editSaleType($id)
    {
        try{
            $saleType = SaleType::find($id);
                
            if(!$saleType){
                return response()->json([
                    'message' => 'The Sale Type you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'saleType' =>$saleType
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
            'name' => 'required',
        ]);
        DB::beginTransaction();
        try{
            $saleType = SaleType::find($id);
            
            if(!$saleType){
                return response()->json([
                    'message' => 'The Sale Type you are trying to update doesn\'t exist.'
                ], 404);
            }

            $saleType->name = $request->name;
            $saleType->description = $request->description;
            $saleType->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Sale Type has been updated Successfully'
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

            SaleType::find($id)->delete(); 

            return response()->json([
                'message' => 'Sale Type deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([                           
                'message' => 'Sale Type cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}
