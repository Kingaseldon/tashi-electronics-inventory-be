<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;

use Exception;
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
            $itemProduct = new Product;
            $jsonData = $request->json()->all();

            foreach ($jsonData as $item) {
                if ($request->product_category_name == $itemProduct['product_type'] && $request->product_sub_category_name == $itemProduct['sub_category']) {
                    $itemProduct->item_number = empty($item['item_number']) == 'true' ? null : $item['item_number'];
                    $itemProduct->serial_no = $item['serial_no'];
                    $itemProduct->batch_no = $batchNo;
                    $itemProduct->category_id = empty($item['category']) == 'true' ? null : $item['category'];
                    $itemProduct->sub_category_id = empty($item['sub_category']) == 'true' ? null : $item['sub_category'];
                    $itemProduct->color_id = empty($item['color']) == 'true' ? null : $item['color'];
                    // $itemProduct['unit_id'] =$item['unit'];
                    $itemProduct->price = $item['price'];
                    // $itemProduct['brand_id'] = empty($value['brand']) != true ? null :$item['brand'];
                    $itemProduct->sale_type_id = $item['type'];
                    $itemProduct->sub_inventory = empty($item['sub_inventory']) == 'true' ? null : $item['sub_inventory'];
                    $itemProduct->locator = empty($item['locator']) == 'true' ? null : $item['locator'];
                    $itemProduct->iccid = empty($item['iccid']) == 'true' ? null : $item['iccid'];
                    $itemProduct->status = "new";
                    $itemProduct->sale_status = "stock";
                    $itemProduct->description = empty($item['description']) == 'true' ? null : $item['description'];
                    $itemProduct->main_store_qty = $item['quantity'];
                    $itemProduct->total_quantity = $item['quantity'];
                    $itemProduct->created_date = date('Y-m-d', strtotime(Carbon::now()));
                    // $itemProduct['store_id'] = empty($item['store']) != true ? null : $item['store'];
                    $itemProduct->created_by = auth()->user()->id;
                    $itemProduct->save();


                    DB::table('transaction_audits')->insert([
                        'store_id' => 1,
                        'sales_type_id' => $itemProduct->sale_type_id,
                        'product_id' => $itemProduct->id,
                        'item_number' => $itemProduct->item_number,
                        'description' => $itemProduct->description,
                        'received' =>  $itemProduct->main_store_qty,
                        'stock' =>  $itemProduct->main_store_qty,
                        'created_date' => now(),
                        'status' => 'upload',
                        'created_at' => now(),
                        'created_by' => auth()->user()->id,
                    ]);
                } else {
                    return response()->json([
                        'message' => 'Category and sub-category doesnot match',
                    ], 500);
                }
            }
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

            if ($request->hasFile('attachment')) {
                if ($request->product_category == '1') {
                    // When the product category is 'phone'
                    $filePath = $request->file('attachment');
                    $import = new ProductImport($request, $filePath, $batchNo);
                    Excel::import($import, $filePath);
                    $rowCount = $import->getRowCount();

                    // Retrieve validation errors from the import class
                    $validationErrors = $import->getValidationErrors();
                    $serialNoValidationError = $import->serialNovalidation();


                    if (!empty($validationErrors)) {
                        // Return error messages if any rows failed validation.
                        return response()->json([
                            'message' => 'Category and sub-category doesnot match',
                            'errors' => $validationErrors
                        ], 201);
                    } elseif (!empty($serialNoValidationError)) {
                        $serialNumbersString = implode(', ', $serialNoValidationError);


                        // Return error messages if any rows failed validation.
                        return response()->json([
                            // 'message' => 'FAILED! Please check Product Type and Sub Category',
                            'message' => 'Some Serial Numbers already exists ' . $serialNumbersString
                        ], 201);
                    } else {
                        return response()->json([
                            'message' => 'You have Successfully uploaded ' . $rowCount . ' number of products',

                        ], 200);
                    }
                } elseif ($request->product_category == '2') {

                    // When the product category is 'accessory'
                    $filePath = $request->file('attachment')->store('temporary');

                    $import = new AccessoryImport($request, $filePath, $batchNo);


                    // Import data
                    Excel::import($import, storage_path("app/{$filePath}")); // Use storage_path to get the full path

                    // Get total quantity after the import
                    $totalQuantity = $import->getTotalQuantity();
                    //delete the file from storage folder after getting the total counts
                    Storage::delete($filePath);

                    $validationErrors = $import->getValidationErrors();
                    $addedQuantity = $import->getQuantity();

                    if (!empty($validationErrors)) {
                        // Return error messages if any rows failed validation.
                        return response()->json([
                            'message' => 'Category and sub-category do not match',
                            'errors' => $validationErrors
                        ], 201);
                    } elseif (!empty($addedQuantity)) {
                        // Convert the array to a string
                        $addedQuantityMessage = implode(', ', $addedQuantity);
                        // Return message for added quantity
                        $message = 'You have successfully uploaded ' . $totalQuantity . ' new products. ' . $addedQuantityMessage;
                    } else {
                        // Return success message with total quantity
                        $message = 'You have successfully uploaded ' . $totalQuantity . ' new products';
                    }
                    return response()->json([
                        'message' => $message,
                    ], 200);
                } else {
                    // When the product category is 'sim'
                    $filePath = $request->file('attachment');
                    $import = new SimImport($request, $filePath, $batchNo);
                    Excel::import($import, $filePath);
                    $rowCount = $import->getRowCount();

                    $validationErrors = $import->getValidationErrors();
                    $validation = $import->getValidation();

                    if (!empty($validationErrors)) {
                        // Return error messages if any rows failed validation.
                        return response()->json([
                            'message' => 'Category and sub category doesnot match',
                            'errors' => $validationErrors
                        ], 201);
                    } elseif (!empty($validation)) {
                        $serialNumbersString = implode(', ', $validation);

                        return response()->json([
                            // 'message' => 'serial number already exists',
                            'message' => 'Some Serial Numbers already exists ' . $serialNumbersString
                        ], 201);
                    } else {
                        return response()->json([
                            'message' => 'You have Successfully uploaded ' . $rowCount . ' number of products',
                        ], 200);
                    }
                }
            } else {
                return response()->json([
                    'message' => 'Please attach the file'
                ], 200);
            }
        } catch (Exception $e) {
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

    public function checkStock(Request $request)
    {
        try {
            $product = Product::select(
                'sale_types.name as category',
                'sub_categories.name as sub_category',
                \DB::raw('CASE WHEN products.sale_type_id != 2 AND products.store_id = 1 THEN stores.store_name END AS store_name'),
                \DB::raw('SUM(products.main_store_qty) AS total_quantity')
            )
                ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
                ->leftJoin('product_transactions', 'product_transactions.product_id', '=', 'products.id')
                ->leftJoin('regions', 'regions.id', '=', 'product_transactions.regional_id')
                ->leftJoin('extensions', 'extensions.id', '=', 'product_transactions.region_extension_id')
                ->groupBy('sale_types.name', 'sub_categories.name', 'stores.store_name', 'products.sale_type_id', 'products.store_id')
                ->whereNotNull(\DB::raw('CASE WHEN products.sale_type_id != 2 AND products.store_id = 1 THEN stores.store_name END'))
                ->where('products.price', $request->price) // Filter by price
                ->havingRaw('SUM(products.main_store_qty) != 0')
                ->union(
                    Product::select(
                        'sale_types.name as category',
                        'sub_categories.name as sub_category',
                        \DB::raw("'main store' AS store_name"),
                        \DB::raw('SUM(products.main_store_qty) AS total_quantity')
                    )
                        ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                        ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                        ->where('products.main_store_qty', '>', 0)
                        ->where('products.price', $request->price) // Filter by price
                        ->groupBy('sale_types.name', 'sub_categories.name', 'store_name')
                )
                ->union(
                    Product::select(
                        'sale_types.name as category',
                        'sub_categories.name as sub_category',
                        'regions.name as store_name',
                        \DB::raw('SUM(product_transactions.region_store_quantity) AS total_quantity')
                    )
                        ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                        ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                        ->leftJoin('product_transactions', 'product_transactions.product_id', '=', 'products.id')
                        ->leftJoin('regions', 'regions.id', '=', 'product_transactions.regional_id')
                        ->whereNotNull('product_transactions.regional_id')
                        ->where('product_transactions.region_store_quantity', '>', 0)
                        ->where('products.price', $request->price) // Filter by price
                        ->groupBy('sale_types.name', 'sub_categories.name', 'store_name')
                )
                ->union(
                    Product::select(
                        'sale_types.name as category',
                        'sub_categories.name as sub_category',
                        'extensions.name as store_name',
                        \DB::raw('SUM(product_transactions.store_quantity) AS total_quantity')
                    )
                        ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                        ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                        ->leftJoin('product_transactions', 'product_transactions.product_id', '=', 'products.id')
                        ->leftJoin('extensions', 'extensions.id', '=', 'product_transactions.region_extension_id')
                        ->whereNotNull('product_transactions.region_extension_id')
                        ->where('product_transactions.store_quantity', '>', 0)
                        ->where('products.price', $request->price) // Filter by price
                        ->groupBy('sale_types.name', 'sub_categories.name', 'store_name')
                )
                ->get();


            if (!$product) {
                return response()->json([
                    'message' => 'The Product you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'product' => $product,

            ], 200);
        } catch (Exception $e) {
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
