<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Extension;
use App\Models\Region;
use App\Models\Bank;
use DB;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('permission:banks.view')->only('index', 'show');
        $this->middleware('permission:banks.store')->only('store');
        $this->middleware('permission:banks.update')->only('update');       
        $this->middleware('permission:banks.edit-banks')->only('editBank');       
        $this->middleware('permission:banks.get-banks')->only('getBanks');       
    }
   public function index()
   {
       try{

           $banks = Bank::with('region', 'extension')->orderBy('id')->get();
         
           $extensions = Extension::orderBy('id')->get();
           $regions = Region::orderBy('id')->get();           
           if($banks->isEmpty()){
               $banks = [];
           }   
               return response([
                   'message' => 'success',
                   'bank' => $banks,
                   'extension' => $extensions,
                   'region' => $regions,
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
           'bank_name' => 'required',
       ]);

       DB::beginTransaction();

       try{
           $bank = new Bank;
           $bank->name = $request->bank_name;
           $bank->code = $request->code;
           $bank->account_number = $request->account_number;
           $bank->region_id = $request->region;
           $bank->extension_id = $request->extension;
           $bank->description = $request->description;
           $bank->save();

       }catch(\Exception $e)
       {
           DB::rollback();
           return response()->json([  
               'message'=> $e->getMessage(),                                                        
           ], 500);
       }

       DB::commit();
       return response()->json([
           'message' => 'Bank has been created Successfully'
       ], 200);
   }
    public function getBanks(Request $request)
    {
        $region = $request->region;
        $extension = $request->extension;
        $banks = Bank::where('region_id', $region)->where('extension_id', $extension)->get();

        //if no user
        return response([
            'message' => 'success',
            'banks'=>$banks
           
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
   public function editBank($id)
   {
       try{
           $bank = Bank::with('region','extension')->find($id);
        //    $extensions = Extension::orderBy('id')->get();
        //    $regions = Region::orderBy('id')->get();    
               
           if(!$bank){
               return response()->json([
                   'message' => 'The Bank you are trying to update doesn\'t exist.'
               ], 404);
           }
           return response([
               'message' => 'success',
               'bank' => $bank,
            //    'extension' => $extensions,
            //    'region' => $regions,
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
           'bank_name' => 'required',
       ]);
       DB::beginTransaction();
       try{
           $bank = bank::find($id);
           
           if(!$bank){
               return response()->json([
                   'message' => 'The Bank you are trying to update doesn\'t exist.'
               ], 404);
           }

           $bank->name = $request->bank_name;
           $bank->code = $request->code;
           $bank->account_number = $request->account_number;
           $bank->region_id = $request->region;
           $bank->extension_id = $request->extension;
           $bank->description = $request->description;
           $bank->save();

       }catch(\Exception $e)
       {
           DB::rollback();
           return response()->json([  
               'message'=> $e->getMessage(),                                                        
           ], 500);
       }

       DB::commit();
       return response()->json([
           'message' => 'Bank has been updated Successfully'
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

           Bank::find($id)->delete(); 

           return response()->json([
               'message' => 'Bank deleted successfully',
           ], 200);
       } catch (\Exception $e) {
           return response()->json([                           
               'message' => 'Bank cannot be delete. Already used by other records.'
           ], 202);
       }
   }
}
