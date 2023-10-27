<?php

namespace App\Http\Controllers\DealerManagement;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\DiscountType;
use App\Models\Category;
use DB;

class DiscountTypeController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:discount-types.view')->only('index', 'show');
        $this->middleware('permission:discount-types.store')->only('store');
        $this->middleware('permission:discount-types.update')->only('update');       
        $this->middleware('permission:discount-types.edit-discounts')->only('editDiscountType');       
        $this->middleware('permission:discount-types.get-products')->only('getProduct');       
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $discountTypes = DiscountType::with('category.saleType', 'subCategory','region','extension',)->orderBy('id')->get();    
            $categories = Category::with('sub_categories:id,name,category_id,code,description', 'saleType')->orderBy('id')->get(['id','sale_type_id', 'description']);            
            if($discountTypes->isEmpty()){
                $discountTypes = [];
            }   
                return response([
                    'message' => 'success',
                    'discountType' => $discountTypes,                
                    'categories' => $categories,                
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
            'discount_type' => 'required',
        ]);
 
        DB::beginTransaction();
 
        try{
            $discountType = new DiscountType;
            $discountType->category_id = $request->category;
            $discountType->sub_category_id = $request->sub_category;
            $discountType->region_id = $request->region;
            $discountType->extension_id = $request->extension;
            $discountType->discount_name = $request->discount_name;
            $discountType->discount_type = $request->discount_type;
            $discountType->applicable_to = $request->applicable_to;
            $discountType->discount_value = $request->discount_value;
            $discountType->start_date = $request->start_date == null ? null : date('Y-m-d', strtotime($request->start_date));
            $discountType->end_date = $request->end_date == null ? null : date('Y-m-d', strtotime($request->end_date));            
            $discountType->description = $request->description;     
            $discountType->save();

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }
 
        DB::commit();
        return response()->json([
            'message' => 'Discount Type has been created Successfully'
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

    //get products based on sub category id
    public function getProduct($id)
    {
        try {
            $products = Product::orderBy('id')->where('sub_category_id', $id)->get();
           
            if ($products->isEmpty()) {
                $products = [];
            }
            return response([
                'message' => 'success',             
                'products' => $products,
            ], 200);
        } catch (Execption $e) {
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
    public function editDiscountType($id)
    {
        try{
            $discountType = DiscountType::with('category','subCategory','region','extension')->find($id);    
            $categories = Category::with('sub_categories:id,name,category_id,code,description', 'saleType')->orderBy('id')->get(['id','sale_type_id', 'description']);               
                
            if(!$discountType){
                return response()->json([
                    'message' => 'The Discount Type you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'discountType' => $discountType,            
                'categories' => $categories,            
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
            'discount_type' => 'required',
        ]);
        DB::beginTransaction();
        try{
            $discountType = DiscountType::find($id);
            
            if(!$discountType){
                return response()->json([
                    'message' => 'The Discount Type you are trying to update doesn\'t exist.'
                ], 404);
            }
 
            $discountType->category_id = $request->category;
            $discountType->sub_category_id = $request->sub_category;
            $discountType->discount_name = $request->discount_name;
            $discountType->discount_type = $request->discount_type;
            $discountType->applicable_to = $request->applicable_to;
            $discountType->region_id = $request->region;
            $discountType->extension_id = $request->extension;
            $discountType->discount_value = $request->discount_value;
            $discountType->start_date = $request->start_date == null ? null : date('Y-m-d', strtotime($request->start_date));
            $discountType->end_date = $request->end_date == null ? null : date('Y-m-d', strtotime($request->end_date));         
            $discountType->description = $request->description;   
            $discountType->save();
 
        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }
 
        DB::commit();
        return response()->json([
            'message' => 'Discount Type has been updated Successfully'
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
            DiscountType::find($id)->delete();
    
               return response()->json([
                   'message' => 'Discount Type deleted successfully',
               ], 200);
           } catch (\Exception $e) {
               return response()->json([                           
                   'message' => 'Discount Type cannot be delete. Already used by other records.'
               ], 202);
           }
    }
}
