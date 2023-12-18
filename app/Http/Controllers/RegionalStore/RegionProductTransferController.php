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
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */



    //region store transaction's index history
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
                $giveProducts = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('receive', '!=', 0)->where('regional_id', '!=',null)->get();
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
            if (!isset($reqNo)) {
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
                $products = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->where('region_store_quantity', '!=', 0)->get();
                $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);
                // The user has a role with is_super_user set to 1
            } else {
                $requisitions = ProductRequisition::with('region', 'extension', 'saleType')->where('requisition_number', $reqNo)->where('status', 'requested')->get();
                //check that paricular reqNo number
                $products = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->where('region_store_quantity', '!=', 0)->loggedInAssignRegion()->get();
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
                $productTable = Product::where('serial_no', $value['serial_no'])->first();
                $transaction = ProductTransaction::where('product_id', $product->product_id)->where('regional_id', $product->regional_id)->first();


                // $quantityafterDistribute = $product->receive;

                $totalDistribute = $productTable->region_store_distributed_qty;
                $regionStoreQty = $productTable->region_store_qty;

                //check when transfer quantity should not be greater than the stock quantity in
                if ($transferQuantity > $regionStoreQty) {
                    return response()->json([
                        'message' => 'Transfer Quantity should not be greater than the quantity in stock'
                    ], 422);
                }

                //if stock quantity is greater than transfer quantity tha saleStatus should be stock and if zero then transfer 
                if ($regionStoreQty > $transferQuantity) {
                    $saleStatus = "stock";
                } else {
                    $saleStatus = "transfer";
                }
                //product table should be update after transfer of the product
                $productTable->update([
                    'region_store_distributed_qty' => $totalDistribute + $transferQuantity,
                    'sale_status' => $saleStatus,
                    'region_store_qty' => $regionStoreQty - $transferQuantity
                ]);
                $regionalStoreQty = $transaction->region_store_quantity;

                $regionalTransferQty = $transaction->region_transfer_quantity;

                $transaction->update([
                    'region_store_quantity' => $regionalStoreQty - $transferQuantity,
                    'region_transfer_quantity' => $regionalTransferQty + $transferQuantity
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

}
