<?php

namespace App\Http\Controllers\DealerManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PromotionType;
use Illuminate\Support\Facades\DB;

class PromotionTypeController extends Controller
{
   /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        // $this->middleware('permission:promotion-types.view')->only('index', 'show');
        // $this->middleware('permission:promotion-types.store')->only('store');
        // $this->middleware('permission:promotion-types.update')->only('update');
        // $this->middleware('permission:promotion-types.edit-promotions')->only('editPromotion');
    }


   public function index()
   {
       try{

           $promotionTypes = PromotionType::orderBy('id')->get();
           if($promotionTypes->isEmpty()){
               $promotionTypes = [];
           }
               return response([
                   'message' => 'success',
                   'promotionType' => $promotionTypes,
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
           'promotion_type' => 'required',
       ]);

       DB::beginTransaction();

       try{
           $promotionType = new PromotionType;
           $promotionType->promotion_type = $request->promotion_type;
           $promotionType->start_date = $request->start_date;
           $promotionType->end_date = $request->end_date;
           $promotionType->promotion_percentage = $request->percentage;
           $promotionType->description = $request->description;
           $promotionType->save();

       }catch(\Exception $e)
       {
           DB::rollback();
           return response()->json([
               'message'=> $e->getMessage(),
           ], 500);
       }

       DB::commit();
       return response()->json([
           'message' => 'Promotion Type has been created Successfully'
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
   public function editPromotion($id)
   {
       try{
           $promotionType = PromotionType::find($id);

           if(!$promotionType){
               return response()->json([
                   'message' => 'The Promotion Type you are trying to update doesn\'t exist.'
               ], 404);
           }
           return response([
               'message' => 'success',
               'promotionType' => $promotionType,
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
           'promotion_type' => 'required',
       ]);
       DB::beginTransaction();
       try{
           $promotionType = PromotionType::find($id);

           if(!$promotionType){
               return response()->json([
                   'message' => 'The Promotion Type you are trying to update doesn\'t exist.'
               ], 404);
           }

           $promotionType->promotion_type = $request->promotion_type;
           $promotionType->start_date = $request->start_date;
           $promotionType->end_date = $request->end_date;
           $promotionType->promotion_percentage = $request->percentage;
           $promotionType->description = $request->description;
           $promotionType->save();

       }catch(\Exception $e)
       {
           DB::rollback();
           return response()->json([
               'message'=> $e->getMessage(),
           ], 500);
       }

       DB::commit();
       return response()->json([
           'message' => 'Promotion Type has been updated Successfully'
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

        PromotionType::find($id)->delete();

           return response()->json([
               'message' => 'Promotion Type deleted successfully',
           ], 200);
       } catch (\Exception $e) {
           return response()->json([
               'message' => 'Promotion Type cannot be delete. Already used by other records.'
           ], 202);
       }
   }
}
