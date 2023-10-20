<?php

namespace App\Http\Controllers\DealerManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CustomerType;
use DB;

class CustomerTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:customer-types.view')->only('index', 'show');
        $this->middleware('permission:customer-types.store')->only('store');
        $this->middleware('permission:customer-types.update')->only('update');       
        $this->middleware('permission:customer-types.edit-customer-types')->only('editCustomerType');       
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $customerTypes = CustomerType::orderBy('id')->get();          
            if($customerTypes->isEmpty()){
                $customerTypes = [];
            }   
                return response([
                    'message' => 'success',
                    'customerType' => $customerTypes,                
                ],200);
        }catch(Execption $e){
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
    public function create()
    {
        //
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
            $customerType = new CustomerType;
            $customerType->name = $request->name;         
            $customerType->description = $request->description;     
            $customerType->save();
 
        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }
 
        DB::commit();
        return response()->json([
            'message' => 'Customer Type has been created Successfully'
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
    public function editCustomerType($id)
    {
        try{
            $customerType = CustomerType::find($id);       
                
            if(!$customerType){
                return response()->json([
                    'message' => 'The Customer Type you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'customerType' => $customerType,            
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
            $customerType = CustomerType::find($id);
            
            if(!$customerType){
                return response()->json([
                    'message' => 'The Customer Type you are trying to update doesn\'t exist.'
                ], 404);
            }
 
            $customerType->name = $request->name;       
            $customerType->description = $request->description;     
            $customerType->save();
 
        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }
 
        DB::commit();
        return response()->json([
            'message' => 'Customer Type has been updated Successfully'
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

            CustomerType::find($id)->delete(); 
    
               return response()->json([
                   'message' => 'Customer Type deleted successfully',
               ], 200);
           } catch (\Exception $e) {
               return response()->json([                           
                   'message' => 'Customer Type cannot be delete. Already used by other records.'
               ], 202);
           }
    }
}
