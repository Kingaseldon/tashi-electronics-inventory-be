<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\SerialNumberGenerator;
use Illuminate\Http\Request;
use App\Imports\AccessoryImport;
use App\Imports\ProductImport;
use App\Imports\SimImport;
use App\Models\ProductPrizeHistory;
use App\Models\Color;
use App\Models\Category;
use App\Models\Product;
use App\Models\Brand;
// use App\Models\Store;
use App\Models\Unit;
use Carbon\Carbon;
use DB;

class ProductController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:products.view')->only('index', 'show');
        $this->middleware('permission:products.store')->only('store');
        $this->middleware('permission:products.update')->only('update');
        $this->middleware('permission:products.edit-products')->only('editProduct');
        $this->middleware('permission:products.uploads')->only('importProduct');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $products = Product::with('unit', 'brand', 'store', 'category', 'subCategory', 'saleType', 'color')->orderBy('id')->get();
            $categories = Category::with('sub_categories:id,name,category_id,code,description', 'saleType')->orderBy('id')->get(['id', 'sale_type_id', 'description']);
            $brands = Brand::orderBy('id')->get();
            // $stores = Store::with('dzongkhag')->orderBy('id')->get();
            $colors = Color::orderBy('id')->get();
            $units = Unit::orderBy('id')->get();
            if ($products->isEmpty()) {
                $products = [];
            }
            return response([
                'message' => 'success',
                'product' => $products,
                'category' => $categories,
                'brand' => $brands,
                'colors' => $colors,
            ], 200);
        } catch (Execption $e) {
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
    public function store(Request $request, SerialNumberGenerator $invoice)
    {
        $batchNo = $invoice->batchNumber('Product', 'created_date');
        // return response()->json($request->all());
        $this->validate($request, [
            // 'products.*.model_name' => 'required',
        ]);

        DB::beginTransaction();

        try {
            //creating products
            $itemProduct = [];
            foreach ($request->products as $key => $value) {
                foreach ($request->products as $key => $value) {
                    $itemProduct[$key]['item_number'] = empty($value['item_number']) == 'true' ? null : $value['item_number'];
                    $itemProduct[$key]['serial_no'] = $value['serial_no'];
                    $itemProduct[$key]['batch_no'] = $batchNo;
                    $itemProduct[$key]['category_id'] = empty($value['category']) == 'true' ? null : $value['category'];
                    $itemProduct[$key]['sub_category_id'] = empty($value['sub_category']) == 'true' ? null : $value['sub_category'];
                    // $itemProduct[$key]['unit_id'] = $value['unit']; 
                    $itemProduct[$key]['price'] = $value['price'];
                    // $itemProduct[$key]['brand_id'] = empty($value['brand']) != true ? null : $value['brand']; 
                    $itemProduct[$key]['sale_type_id'] = $value['type'];
                    $itemProduct[$key]['sub_inventory'] = $value['sub_inventory'];
                    $itemProduct[$key]['locator'] = $value['locator'];
                    $itemProduct[$key]['iccid'] = $value['iccid'];
                    $itemProduct[$key]['status'] = "new";
                    $itemProduct[$key]['sale_status'] = "stock";
                    $itemProduct[$key]['description'] = empty($value['description']) == 'true' ? null : $value['description'];
                    $itemProduct[$key]['quantity'] = $value['quantity'];
                    $itemProduct[$key]['total_quantity'] = $value['quantity'];
                                 // $itemProduct[$key]['store_id'] = empty($value['store']) != true ? null : $value['store']; 
                    $itemProduct[$key]['created_by'] = auth()->user()->id;
                }
            }

            DB::table('products')->insert($itemProduct);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Product has been created Successfully'
        ], 200);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function importProduct(Request $request, SerialNumberGenerator $invoice)
    {
        try {
            //unique number generator
            $batchNo = $invoice->batchNumber('Product', 'created_date');

            if (($request->hasFile('attachment')) == true) {
                if ($request->product_category == '1') { //when product category is phone
                    $filePath = $request->file('attachment');
                    $import = new ProductImport($request, $filePath, $batchNo);
                    Excel::import($import, $filePath);
                } elseif ($request->product_category == '2') { //when product category is accessory
                    $filePath = $request->file('attachment');
                    $import = new AccessoryImport($request, $filePath, $batchNo);
                    Excel::import($import, $filePath);
                } else { //when product category is sim
                    $filePath = $request->file('attachment');
                    $import = new SimImport($request, $filePath, $batchNo);
                    Excel::import($import, $filePath);
                }
                return response()->json([
                    'message' => 'Product has been uploaded Successfully'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Please attach the file'
                ], 200);
            }
        } catch (Execption $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
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
    public function editProduct($id)
    {
        try {
            $product = Product::with('unit', 'brand', 'store', 'category.saleType', 'subCategory', 'saleType', 'color')->find($id);
            $categories = Category::with('sub_categories:id,name,category_id,code,description', 'saleType')->orderBy('id')->get(['id', 'sale_type_id', 'description']);
            $brands = Brand::orderBy('id')->get();
            // $stores = Store::with('dzongkhag')->orderBy('id')->get();
            $colors = Color::orderBy('id')->get();
            $units = Unit::orderBy('id')->get();

            if (!$product) {
                return response()->json([
                    'message' => 'The Product you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'product' => $product,
                'categories' => $categories,
                'brands' => $brands,
                'colors' => $colors,
                'units' => $units,
            ], 200);
        } catch (Exception $e) {
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
        // $this->validate($request, [
        //     'model_name' => 'required',
        // ]);
        DB::beginTransaction();
        try {
            //get the product with same item number, quntity not zero and sale status stock 
            $products = Product::where('item_number', $id)->where('quantity', '!=', 0)->where('sale_status', 'stock')->get();
            // return response()->json($product);
            if (!$products) {
                return response()->json([
                    'message' => 'The Product you are trying to update doesn\'t exist.'
                ], 404);
            }
            //updating product with same item number, quntity not zero and sale status stock 
            Product::where('item_number', $id)->where('quantity', '!=', 0)->where('sale_status', 'stock')->update(['price' => $request->new_price]);

            $prize = new ProductPrizeHistory;

            $prize->price = $request->old_prize;
            $prize->product_item_number = $id;
            $prize->change_date = date('Y-m-d', strtotime(Carbon::now()));
            $prize->save();

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Product has been updated Successfully'
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

            Product::find($id)->delete();

            return response()->json([
                'message' => 'Product has been deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Product cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}