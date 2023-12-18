<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubCategory;  
use App\Models\Category;  
use App\Models\SaleType;  
use DB;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('permission:categories.view')->only('index', 'show');
        $this->middleware('permission:categories.store')->only('store');
        $this->middleware('permission:categories.update')->only('update');       
        $this->middleware('permission:categories.edit-categories')->only('editCategory');       
    }
    public function index()
    {
        try{

            $categories = Category::with('sub_categories', 'saleType')->orderBy('id')->get();
            $saleTypes = SaleType::orderBy('id')->get();
            if($categories->isEmpty()){
                $categories = [];
            }   
                return response([
                    'message' => 'success',
                    'category' =>$categories,
                    'saleTypes' =>$saleTypes,
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
            'sale_type' => 'required',
        ]);

        DB::beginTransaction();

        try{
            $category = new Category;

            $category->sale_type_id = $request->sale_type;
            $category->code = $request->code;
            $category->description = $request->description;
            $category->save();

            $subCategory = [];
            foreach ($request->subCategories as $key => $value) {
                $subCategory[$key]['category_id'] = $category->id;
                $subCategory[$key]['name'] = $value['name'];
                $subCategory[$key]['code'] = $value['code'];
                $subCategory[$key]['description'] = isset($value['description']) == true ? $value['description'] : null ;
                $subCategory[$key]['created_by'] = auth()->user()->id;
            }

            $category->sub_categories()->insert($subCategory);

        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Category has been created Successfully'
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
    public function editCategory($id)
    {
        try{
            $category = Category::with('sub_categories', 'saleType')->find($id);
            $saleTypes = SaleType::orderBy('id')->get();
                
            if(!$category){
                return response()->json([
                    'message' => 'The Category you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'category' =>$category,
                'saleTypes' =>$saleTypes,
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
            'sale_type' => 'required',
        ]);
        DB::beginTransaction();
        try{
            $category = Category::findOrFail($id);
            
            if(!$category){
                return response()->json([
                    'message' => 'The Category you are trying to update doesn\'t exist.'
                ], 404);
            }

            $category->sale_type_id = $request->sale_type;
            $category->code = $request->code;
            $category->description = $request->description;
            $category->save();

            $subModuleIdsFromTheDatabase = SubCategory::where('category_id', $category->id)->pluck('id')->toArray();
            $subModuleIdsFromTheDatabaseCount = sizeof($subModuleIdsFromTheDatabase);

            //count the total number of values in the array
            $subModuleIdsFromRequestCount = sizeof($request->subCategories);

            //store the incoming form values in an array but only the sub modules/menus id
             $subModuleIdsFromRequest =[];
            foreach ($request->subCategories as $value) {
                if(isset($value['sub_category_id'])) {
                $subModuleIdsFromRequest [] = (int) $value['sub_category_id'];
                }
            }

            //get the unique ids by comparing the above two arrays [subModuleIdsFromTheDatabase, subModuleIdsFromRequest]
            $uniqueSubModuleIds = array_merge(array_diff($subModuleIdsFromTheDatabase, $subModuleIdsFromRequest), array_diff($subModuleIdsFromRequest, $subModuleIdsFromTheDatabase));

            //after that remove the deleted sub module from the system_sub_menus table
            SubCategory::whereIn('id', $uniqueSubModuleIds)->delete();

            // foreach($request->subCategories as $key => $value){

            //     if(isset($value['sub_category_id'])) {
            //         $category->sub_categories()->updateOrCreate(
            //             ['id' => $value['sub_category_id']],
            //             [
            //                 'name' => $value['name'],
            //                 'code' => $value['code'],
            //                 'description' => isset($value['description']) == true ? $value['description'] : null ,
            //                 'created_by' => auth()->user()->id,
            //             ]
            //         );
            //     } else {
            //         $category->sub_categories()->create([
            //             'name' => $value['name'],
            //             'code' => $value['code'],
            //             'description' => isset($value['description']) == true ? $value['description'] : null ,
            //             'created_by' => auth()->user()->id,
            //         ]);
            //     }
            // }
            foreach ($request->subCategories as $key => $value) {
                $subCategory = SubCategory::whereId($value['sub_category_id'])->first();
                if ($subCategory) {
                    $subCategory->update([
                        'name' => $value['name'],
                        'code' => isset($value['code']) == true ? $value['code'] : null,
                        'description' => isset($value['description']) == true ? $value['description'] : null,
                        'created_by' => auth()->user()->id,
                    ]);
                } else {
                    $category->sub_categories()->create([
                        'name' => $value['name'],
                        'code' => isset($value['code']) == true ? $value['code'] : null,
                        'description' => isset($value['description']) == true ? $value['description'] : null,
                        'created_by' => auth()->user()->id,
                    ]);
                }
            }
        }catch(\Exception $e)
        {
            DB::rollback();
            return response()->json([  
                'message'=> $e->getMessage(),                                                        
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Category has been updated Successfully'
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

            Category::find($id)->delete(); 

            return response()->json([
                'message' => 'Category deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([                           
                'message' => 'Category cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}
