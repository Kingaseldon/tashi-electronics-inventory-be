<?php

namespace App\Http\Controllers\RegionalStore;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\ProductRequisition;
use App\Models\ProductTransaction;
use App\Models\Region;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Services\SerialNumberGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DB;

class RegionProductTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:regional-transfers.view')->only('index', 'show');
        $this->middleware('permission:regional-transfers.update')->only('update');
        $this->middleware('permission:regional-transfers.store')->only('store');
        $this->middleware('permission:regional-transfers.regional-requisitions')->only('requestedRegionalTransfer');
        // $this->middleware('permission:regional-stores.get-transfer-regional')->only('transferRegionalProduct');       
        // $this->middleware('permission:regional-stores.regional-transfer')->only('regionalTransfer');   
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //get regional transfer index page 
    //extention to regional controller
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $roles = $user->roles;

            $isSuperUser = false;
            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            if ($isSuperUser) {
                $giveProducts = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('receive', '!=', 0)->get();
                $requisitions = ProductRequisition::select('region_extension_id', 'requisition_number', DB::raw('SUM(request_quantity) as quantity'))
                ->with('saleType', 'region', 'extension')
                ->where('status', 'requested')                
                ->where('requisition_to', '=', 2)
                ->groupBy('region_extension_id', 'requisition_number')
                ->get();

                // The user has a role with is_super_user set to 1
            } else {
                $giveProducts = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('receive', '!=', 0)->loggedInAssignRegion()->get();             
                $requisitions = ProductRequisition::select('region_extension_id', 'requisition_number', DB::raw('SUM(request_quantity) as quantity'))
                ->with('saleType', 'region', 'extension')
                ->where('status', 'requested')
                ->whereIn('region_extension_id', function ($query) use ($giveProducts) {
                    $query->select('id')
                    ->from('extensions')
                    ->where('regional_id', $giveProducts->first()->regional_id);
                })
                    ->where('requisition_to', '=', 2)
                    ->groupBy('region_extension_id', 'requisition_number')
                    ->get();
                // The user does not have a role with is_super_user set to 1
            }        

            if ($giveProducts->isEmpty()) {
                $giveProducts = [];
            }
            return response([
                'message' => 'success',
                'transaction' => $requisitions,
                'product' => $giveProducts,
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    //create transfering with respect to requisition
    public function requestedRegionalTransfer($reqNo)
    {

        try {
            if (isset($requisitions)) {
                return response()->json([
                    'message' => 'The Requisition Number you are trying to find doesn\'t exist.'
                ], 404);
            }
            $user = auth()->user();
            $roles = $user->roles;

            $isSuperUser = false;

            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            if ($isSuperUser) {

                $requisitions = ProductRequisition::with('region', 'extension', 'saleType')->where('requisition_number', $reqNo)->where('status', 'requested')->get();
                $products = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->where('store_quantity', '!=', 0)->get();
                $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);
                // The user has a role with is_super_user set to 1
            } else {
                $requisitions = ProductRequisition::with('region', 'extension', 'saleType')->where('requisition_number', $reqNo)->where('status', 'requested')->get();
                //check that paricular reqNo number
                $products = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->where('store_quantity', '!=', 0)->loggedInAssignRegion()->get();
                $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);
            }

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

    //get regional specific transfer product
    public function show(Request $request, $id)
    {
        
        try {
            $transferToRegional = ProductTransaction::with('product.unit', 'product.brand', 'product.store', 'product.category', 'product.saleType', 'product.subCategory', 'region', 'extension')->findOrFail($id);
            $particularExtensions = Extension::where('regional_id', $transferToRegional->regional_id)->orderBy('id', 'desc')->get();
            $regions = Region::where('id', '!=', $transferToRegional->regional_id)->with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);
            $requisitions = ProductRequisition::with('extension', 'region', 'product')->orderBy('id')->get();

            if (!$transferToRegional) {
                return response()->json([
                    'message' => 'The product you are trying to transfer doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'transferToRegional' => $transferToRegional,
                'particularExtensions' => $particularExtensions,
                'regions' => $regions,
                'requisitions' => $requisitions,
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
///region to extension transfer
    public function store(Request $request)
    {
        $this->validate($request, []);

        DB::beginTransaction();
        try {
            $date = date('Y-m-d', strtotime($request->transfer_date));
            $regionId = $request->region;
            $extensionId = $request->extension;
            $requisitionId = $request->product_requisition;

            foreach ($request->productDetails as $key => $value) {
                $transferQuantity = $value['transfer_quantity'];
                $product = Product::where('serial_no', $value['serial_no'])
                    ->join('product_transactions as Tb1', 'Tb1.product_id', '=', 'products.id')
                    ->first();


                $quantityafterDistribute = $product->receive;
                $totalDistribute = $product->distributed_quantity;

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
                    'quantity' => $quantityafterDistribute - $transferQuantity,
                    'distributed_quantity' => $totalDistribute + $transferQuantity,
                    'sale_status' => $saleStatus,
                    'region_store_quantity'=> $quantityafterDistribute- $transferQuantity
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
                $requisition->status = 'supplied';
                $requisition->transfer_date = date('Y-m-d', strtotime(Carbon::now()));
                $requisition->save();

                //store all the stock movement details in the product_movements table
                ProductMovement::create([
                    'product_id' => $product->product_id,
                    'regional_transfer_id' => $request->from,
                    'regional_id' => $regionId,
                    'region_extension_id' => $extensionId,
                    'requisition_number' => $requisitionId,
                    'movement_date' => $date,
                    'status' => 'process',
                    'receive' => $transferQuantity,
                    'description' => $value['description'],
                    'created_by' => auth()->user()->id,
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
            'message' => 'Product has been transfered Successfully'
        ], 200);
    }
    //regional transfer
    public function update(Request $request, SerialNumberGenerator $serial)
    {
      
        DB::beginTransaction();

        try {
            $productTransfer = ProductTransaction::with('product')->findOrFail($request->transaction_id);

            //unique number generator
            $movementNo = $serial->movementNumber('ProductMovement', 'movement_date');
            //get total receive product so far
            $transferToRegional = $productTransfer->receive;

            //if quatity is more than 1 for the product type accessory the sale_status is not changed if more then it should be change to transfer
            if ($productTransfer->receive > 1) {
                $saleStatus = "stock";
            } else {
                $saleStatus = "transfer";
            }

            //updating the total product
            $productTransfer->update([
                'receive' => $transferToRegional - $request->transfer_no,
                'give_back' => $request->transfer_no,
                'sale_status' => $saleStatus,
                'updated_by' => auth()->user()->id,
            ]);

            $productMovement = new ProductMovement;

            $productMovement->product_id = $productTransfer->product_id;
            $productMovement->transfer_type = $request->transfer_type;
            $productMovement->regional_transfer_id = $productTransfer->region->name;
            $productMovement->region_extension_id = $request->extension_name;
            $productMovement->regional_id = $request->region_name;
            $productMovement->main_transfer_store = $request->transfer_type != 'transfer to main store' ? null : 'main store';
            $productMovement->movement_date = $productTransfer->movement_date;
            $productMovement->receive = $request->transfer_no;
            $productMovement->product_movement_no = $movementNo;
            $productMovement->status = 'process';
            $productMovement->created_by = auth()->user()->id;
            $productMovement->description = $request->transfer_description;
            $productMovement->save();
        } catch (Exception $e) {
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
}
