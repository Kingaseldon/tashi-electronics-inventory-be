<?php

namespace App\Http\Controllers\MainStore;

use App\Http\Controllers\Controller;
use App\Imports\TransferProduct;
use Illuminate\Http\Request;
use App\Services\SerialNumberGenerator;
use App\Models\ProductMovement;
use App\Models\ProductRequisition;
use App\Models\Product;
use App\Models\Region;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class MainStoreTransferController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:main-stores.view')->only('index', 'show');
        $this->middleware('permission:main-stores.store')->only('store');
        $this->middleware('permission:main-stores.update')->only('update');
        $this->middleware('permission:main-stores.main-transfers')->only('mainStoreTransfer');
        $this->middleware('permission:main-stores.request-transfers')->only('requestedTransfer');
        // $this->middleware('permission:main-stores.verify-products')->only('physicalVerification');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $products = Product::with('unit', 'brand', 'store', 'category', 'subCategory', 'saleType')->orderBy('id')->where('main_store_qty', '!=', 0)->get();
            $transactions = ProductMovement::where('status', 'process')->with('product', 'region', 'extension')->orderBy('status')->get();
            $transferProducts = Product::with('saleType')->select('item_number', 'description', 'sale_type_id', \DB::raw('SUM(main_store_qty) as total_quantity',))

                ->groupBy('item_number', 'sale_type_id', 'description')
                ->get();
            $requisitions = ProductRequisition::select('regional_id', 'region_extension_id', 'requisition_number', DB::raw('SUM(request_quantity - transfer_quantity) as quantity'))
                ->with('saleType', 'region', 'extension')
                ->where('status', 'requested')->where('requisition_to', '=', 1)
                ->groupBy('regional_id', 'region_extension_id', 'requisition_number')
                ->get();
            $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);
            if ($products->isEmpty()) {
                $products = [];
            }
            return response([
                'message' => 'success',
                'product' => $products,
                'regions' => $regions,
                'requisitions' => $requisitions,
                'transferProducts' => $transferProducts,
                'transactions' => $transactions,
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    //get requisition details
    public function requestedTransfer($reqNo)
    {
        try {

            //check that paricular reqNo number
            if (!$reqNo) {
                return response()->json([
                    'message' => 'The Requisition Number you are trying to find doesn\'t exist.'
                ], 404);
            }
            $requisitions = ProductRequisition::with('region', 'extension', 'saleType')->where('requisition_number', $reqNo)->where('status', 'requested')->where('requisition_to', '=', 1)->get();
            $products = Product::with('saleType')->where('main_store_qty', '>', 0)->get();
            $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);

            return response([
                'message' => 'success',
                'requisitions' => $requisitions,
                'products' => $products,
                'regions' => $regions,
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }


    //get
    //tranfer to stores(regional and extension) not in use now
    public function mainStoreTransfer($id)
    {
        try {
            $product = Product::with('unit', 'brand', 'store', 'category', 'subCategory', 'saleType')->find($id);
            $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);
            $requisitions = ProductRequisition::with('region', 'extension', 'saleType')->orderBy('id')->get();

            if (!$product) {
                return response()->json([
                    'message' => 'The Product you are trying to update doesn\'t exist.'
                ], 404);
            }

            return response([
                'message' => 'success',
                'product' => $product,
                'region' => $regions,
                'requisitions' => $requisitions,
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage(),
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

    //trasfer from main store
    public function store(Request $request, SerialNumberGenerator $serial)
    {
        $this->validate($request, []);

        DB::beginTransaction();
        try {


            $date = date('Y-m-d', strtotime($request->transfer_date));

            $regionId = $request->region === 'null' ? null : $request->region;
            $extensionId = $request->extension;
            $requisitionId = $request->product_requisition;
            $movementNo = $serial->movementNumber('ProductMovement', 'movement_date');



            if (($request->hasFile('attachment')) == true) {

                $file = $request->file('attachment');

                $nestedCollection = Excel::toCollection(new TransferProduct, $file);

                // Flatten and transform the nested collection into a simple array
                $flattenedArray = $nestedCollection->flatten(1)->toArray();

                $errorMessage = "These serial numbers are not found";
                $errorSerialNumbers = [];

                for ($i = 1; $i < count($flattenedArray); $i++) {
                    $product = Product::where('serial_no', $flattenedArray[$i][0])->where('main_store_qty', '!=', 0)->first();


                    if ($product) { // serial number present
                        $transferQuantity = $flattenedArray[$i][1];
                        // $product = Product::where('serial_no', $value['serial_no'])->where('main_store_qty', '!=', 0)->first();
                        $quantityafterDistribute = $product->main_store_qty;

                        $totalDistribute = $product->main_store_distributed_qty;


                        //check when transfer quantity should not be greater than the stock quantity in
                        if ($transferQuantity > $quantityafterDistribute) {
                            return response()->json([
                                'message' => 'Transfer Quantity should not be greater than the quantity in stock'
                            ], 422);
                        }

                        //if stock quantity is greater than transfer quantity tha saleStatus should be stock and if zero then transfer
                        if ($quantityafterDistribute > $transferQuantity) {
                            $saleStatus = "stock";
                        } else {
                            $saleStatus = "transfer";
                        }
                        //product table should be update after transfer of the product

                        $product->update([
                            'main_store_qty' => $quantityafterDistribute - $transferQuantity,
                            'main_store_distributed_qty' => $totalDistribute + $transferQuantity,
                            'sale_status' => $saleStatus,
                        ]);

                        //here we should update the status of requisition after  it is transfer
                        //first search for the requisition using its id

                        // $requisition = ProductRequisition::findOrFail($value['requisition_id']);
                        $requisition = ProductRequisition::where('description', $product->description)
                            ->where('sale_type_id', $product->sale_type_id)
                            ->where('requisition_number', $requisitionId)
                            ->first();

                        //update
                        //increment the count only if the sale type is of either phone or sim
                        //if it is not one of them then update transfer_quantity value with the value send via the request
                        if ($product->sale_type_id == 1 || $product->sale_type_id == 3) {
                            $requisition->increment('transfer_quantity');
                        } else {
                            $requisition->transfer_quantity = $transferQuantity;
                        }
                        if ($requisition->request_quantity == $requisition->transfer_quantity) {
                            $requisition->status = 'supplied';
                        } else {
                            $requisition->status = 'requested';
                        }

                        $requisition->transfer_date = date('Y-m-d', strtotime(Carbon::now()));
                        $requisition->save();


                        //store all the stock movement details in the product_movements table
                        ProductMovement::create([
                            'product_id' => $product->id,
                            'regional_transfer_id' => $request->from,
                            'product_movement_no' => $movementNo,
                            'regional_id' => $regionId,
                            'region_extension_id' => $extensionId,
                            'requisition_number' => $requisitionId,
                            'movement_date' => $date,
                            'status' => 'process',
                            'receive' => $transferQuantity,
                            'description' => $product->description,
                            'created_by' => auth()->user()->id,
                        ]);

                        DB::table('transaction_audits')->insert([
                            'store_id' => 1,
                            'sales_type_id' => $product->sale_type_id, // Corrected variable name
                            'product_id' =>  $product->id,
                            'item_number' => $product->item_number,
                            'description' =>  $product->description,
                            'stock' =>  - ($transferQuantity),
                            'transfer' =>  $transferQuantity,
                            'created_date' => now(),
                            'status' => 'transfer',
                            'created_at' => now(),
                            'created_by' => auth()->user()->id,
                        ]);
                    } else {
                        $errorSerialNumbers[] = $flattenedArray[$i][0];
                    }
                } // foreach ends
                if (count($errorSerialNumbers) > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'serialNumbers' => $errorSerialNumbers
                    ], 203);
                }
            } else {

                foreach ($request->productDetails as $key => $value) {

                    $transferQuantity = $value['transfer_quantity'];
                    $product = Product::where('serial_no', $value['serial_no'])->where('main_store_qty', '!=', 0)->first();

                    $quantityafterDistribute = $product->main_store_qty;
                    $totalDistribute = $product->main_store_distributed_qty;

                    //check when transfer quantity should not be greater than the stock quantity in
                    if ($transferQuantity > $quantityafterDistribute) {
                        return response()->json([
                            'message' => 'Transfer Quantity should not be greater than the quantity in stock'
                        ], 422);
                    }

                    //if stock quantity is greater than transfer quantity tha saleStatus should be stock and if zero then transfer
                    if ($quantityafterDistribute > $transferQuantity) {
                        $saleStatus = "stock";
                    } else {
                        $saleStatus = "transfer";
                    }
                    //product table should be update after transfer of the product
                    $product->update([
                        'main_store_qty' => $quantityafterDistribute - $transferQuantity,
                        'main_store_distributed_qty' => $totalDistribute + $transferQuantity,
                        'sale_status' => $saleStatus,
                    ]);


                    //here we should update the status of requisition after  it is transfer
                    //first search for the requisition using its id
                    $requisition = ProductRequisition::findOrFail($value['requisition_id']);
                    //update
                    //increment the count only if the sale type is of either phone or sim
                    //if it is not one of them then update transfer_quantity value with the value send via the request
                    if ($product->sale_type_id == 1 || $product->sale_type_id == 3) {
                        $requisition->increment('transfer_quantity');
                    } else {
                        $requisition->transfer_quantity = $transferQuantity;
                    }
                    if ($requisition->request_quantity == $requisition->transfer_quantity) {
                        $requisition->status = 'supplied';
                    } else {
                        $requisition->status = 'requested';
                    }

                    $requisition->transfer_date = date('Y-m-d', strtotime(Carbon::now()));
                    $requisition->save();

                    //store all the stock movement details in the product_movements table
                    ProductMovement::create([
                        'product_id' => $product->id,
                        'regional_transfer_id' => $request->from,
                        'product_movement_no' => $movementNo,
                        'regional_id' => $regionId,
                        'region_extension_id' => $extensionId,
                        'requisition_number' => $requisitionId,
                        'movement_date' => $date,
                        'status' => 'process',
                        'receive' => $transferQuantity,
                        'description' => $value['description'],
                        'created_by' => auth()->user()->id,
                    ]);

                    DB::table('transaction_audits')->insert([
                        'store_id' => 1,
                        'sales_type_id' => $product->sale_type_id, // Corrected variable name
                        'product_id' =>  $product->id,
                        'item_number' => $product->item_number,
                        'description' =>  $product->description,
                        'stock' =>  - ($transferQuantity),
                        'transfer' =>  $transferQuantity,
                        'created_date' => now(),
                        'status' => 'transfer',
                        'created_at' => now(),
                        'created_by' => auth()->user()->id,
                    ]);
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
            'message' => 'Product has been transfered Successfully'
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


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    //not in use now
    public function update(Request $request, $id, SerialNumberGenerator $serial)
    {
        $this->validate($request, [
            'region_name' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $mainTransfer = Product::with('unit', 'brand', 'store', 'category', 'subCategory', 'saleType')->find($id);
            //unique number generator
            $movementNo = $serial->movementNumber('ProductMovement', 'movement_date');

            if (!$mainTransfer) {
                return response()->json([
                    'message' => 'The Product you are trying to update doesn\'t exist.'
                ], 404);
            }
            $quantityafterDistribute = $mainTransfer->total_quantity;
            $totalDistribute = $mainTransfer->distributed_quantity;

            //if quatity is more than 1 for the product type accessory the sale_status is not changed if more then it should be change to transfer
            if ($mainTransfer->quantity > 1) {
                $saleStatus = "stock";
            } else {
                $saleStatus = "transfer";
            }


            //total_quantity is total purchase and quantity is after it is distrbuted and distributed_quantity is total product distributed
            $mainTransfer->update([
                'main_store_qty' => $quantityafterDistribute - $request->transfer_no,
                'main_store_distributed_qty' => $totalDistribute + $request->transfer_no,
                'sale_status' => $saleStatus,
            ]);
            //sending the product if requsition is there
            if ($request->product_requisition != null) {
                $requisition = ProductRequisition::find($request->product_requisition);

                $requisition->update([
                    'status' => 'supplied',
                ]);
            }

            $productMovement = new ProductMovement;

            $productMovement->product_id = $mainTransfer->id;
            $productMovement->regional_transfer_id = "Main Store";
            $productMovement->regional_id = $request->region_name;
            $productMovement->product_movement_no = $request->region_name;
            $productMovement->product_requisition_id = $request->product_requisition;
            $productMovement->movement_date = $request->movement_date;
            $productMovement->receive = $request->transfer_no;
            $productMovement->status = 'process';
            $productMovement->description = $request->transfer_description;
            $productMovement->save();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Product has been transfered Successfully'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {}

    //product verification with physical product
    public function physicalVerification(Request $request)
    {
        DB::beginTransaction();
        try {
            foreach ($request->productDetails as $key => $value) {

                $productVerify = ProductMovement::find($value['product_id']);

                $productVerify->update([
                    'status' => 'verified',
                ]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Product has been verified Successfully'
        ], 200);
    }
}
