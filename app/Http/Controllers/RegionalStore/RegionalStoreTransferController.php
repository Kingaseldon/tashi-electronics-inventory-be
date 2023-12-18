<?php

namespace App\Http\Controllers\RegionalStore;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use App\Models\ProductTransaction;
use App\Services\SerialNumberGenerator;
use App\Models\ProductRequisition;
use App\Models\ProductMovement;
use App\Models\Extension;
use App\Models\Region;
use DB;

class RegionalStoreTransferController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:regional-stores.view')->only('index', 'show');
        $this->middleware('permission:regional-stores.update')->only('update');
        $this->middleware('permission:regional-stores.regional-transfer')->only('requestedRegionalTransfer');
        // $this->middleware('permission:regional-stores.edit-regional-stores')->only('editBank');               
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //regional and extension to main store

    public function index()
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
                // $transactions = ProductMovement::where('status', 'process')->with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->get();
                $receiveProducts = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')
                    ->join('product_movements as Tb1', 'Tb1.id', 'product_transactions.product_movement_id')
                    ->select('product_transactions.*', 'Tb1.regional_transfer_id')
                    ->where('product_transactions.receive', '!=', 0)
                    ->where('product_transactions.regional_id','!=',null)
                    ->orderBy('id')
                    ->get();

            //check
                $transactions = ProductMovement::query()
                    ->select('product_movements.requisition_number', 'product_movements.regional_transfer_id', DB::raw('SUM(product_movements.receive) as total_qty'), 'product_movements.status')
                    ->join('products', 'products.id', 'product_movements.product_id')
                    ->where('product_movements.status', 'process')
                    ->groupBy('product_movements.requisition_number', 'product_movements.regional_transfer_id', 'product_movements.status')
                    ->get();

                // $transactions = ProductMovement::with('product')->select('product_movements.requisition_number', 'product_movements.regional_transfer_id', DB::raw('SUM(product_movements.receive) as total_qty'), 'product_movements.status')
                //     ->groupBy('product_movements.requisition_number', 'product_movements.id', 'product_movements.regional_transfer_id', 'product_movements.status')
                //     ->get();

                // The user has a role with is_super_user set to 1
            } else {
                // $transactions = ProductMovement::where('status', 'process')->with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->loggedInAssignRegion()->get();
                $receiveProducts = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')
                    ->join('product_movements as Tb1', 'Tb1.id', 'product_transactions.product_movement_id')
                    ->select('product_transactions.*', 'Tb1.regional_transfer_id')
                    ->where('product_transactions.receive', '!=', 0)
                    ->orderBy('product_transactions.id')
                    ->loggedInAssignRegion()
                    ->get();
                $transactions = ProductMovement::query()
                    ->select('product_movements.requisition_number', 'product_movements.regional_transfer_id', DB::raw('SUM(product_movements.receive) as total_qty'), 'product_movements.status')
                    ->join('products', 'products.id', 'product_movements.product_id')
                    ->where('product_movements.status', 'process')
                    ->groupBy('product_movements.requisition_number', 'product_movements.regional_transfer_id', 'product_movements.status')
                    ->loggedInAssignRegion()
                    ->get();
            }


            if ($transactions->isEmpty()) {
                $transactions = [];
            }
            return response([
                'message' => 'success',
                'transaction' => $transactions,
                'receiveProduct' => $receiveProducts,
            ], 200);
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
    public function requestedRegionalTransfer($reqNo)
    {
        try {
            $requisitions = ProductRequisition::with('region', 'extension', 'saleType')->where('requisition_number', $reqNo)->where('status', 'requested')->get();
            //check that paricular reqNo number
            if (!isset($requisitions)) {
                return response()->json([
                    'message' => 'The Requisition Number you are trying to find doesn\'t exist.'
                ], 404);
            }
            $products = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->where('receive', '!=', 0)->loggedInAssignRegion()->get();

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

    //get details of requested product for acknowledging 
    public function show($id)
    {
    
        try {
            $receiveProduct = ProductMovement::with('product.saleType', 'region', 'extension', 'product.color', 'product.subCategory')->where('requisition_number', $id)->where('status','process')->get();
     
            $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);

            if (!$receiveProduct) {
                return response()->json([
                    'message' => 'The product you are trying to acknowledge doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'regions' => $regions,
                'receiveProduct' => $receiveProduct,
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

    ///product acknowledgement
    public function update(Request $request, $id)
    {

        // $this->validate($request, [
        //     'received_date' => 'required',
        // ]);
        DB::beginTransaction();
        try {
            $receiveProduct = ProductMovement::with('product')->where('requisition_number', $id)->get();
            $jsonData = $request->json()->all();


            foreach ($jsonData as $item) {
              
                $item_id = $item['id'];              
                $received_date =  date('Y-m-d', strtotime($item['received_date']));
                $description = $item['transfer_description'];
                $itemProduct = ProductMovement::with('product')->findOrFail($item_id);             
                $itemProduct->received_date = $received_date;
                $itemProduct->status = 'receive';
                $itemProduct->save();

                if ($itemProduct->$item_id != null) {
                    $requisition = ProductRequisition::find($receiveProduct->$item_id);

                    $requisition->update([
                        'status' => 'supplied',
                    ]);
                }

                

                //check if there is  product in that particular regional
                $transaction = ProductTransaction::where('product_id', $itemProduct->product_id)->where('regional_id', $itemProduct->regional_id)->first();
                
                if ($transaction) {
                   
                    //total receive product so far
                    $totalReceive = $transaction->receive;
                    //updating the total product
                    $transaction->update([
                        'receive' => $totalReceive + $itemProduct->receive,
                        // 'store_quantity' => $totalReceive + $itemProduct->receive,
                        'region_store_quantity' => $totalReceive + $itemProduct->receive,
                        // 'sold_quantity' => 0,
                        'updated_by' => auth()->user()->id,
                    ]);
                } else { //if there is no transaction in that particular regional then create new transaction

                    $productTransaction = new ProductTransaction;

                    $productTransaction->product_movement_id = $itemProduct->id;
                    $productTransaction->product_id = $itemProduct->product_id;
                    $productTransaction->regional_id = $itemProduct->regional_id;
                    $productTransaction->requisition_number = $itemProduct->requisition_number;
                    $productTransaction->movement_date = $itemProduct->movement_date;
                    $productTransaction->received_date = $received_date;
                    $productTransaction->receive = $itemProduct->receive;
                    $productTransaction->store_quantity = 0;
                    $productTransaction->region_store_quantity = $itemProduct->receive;
                    $productTransaction->sold_quantity = 0;
                    $productTransaction->status = 'receive';
                    $productTransaction->sale_status = 'stock';
                    $productTransaction->created_by = auth()->user()->id;
                    $productTransaction->description = $description;
                    $productTransaction->save();
                }

                $product_table = Product::where('id', $itemProduct->product_id)->first();
                $store = Store::where('region_id', $itemProduct->regional_id)->first();
                $product_table->update([
                    'region_store_qty' => $itemProduct->receive,
                    'store_id' => $store->id,
                    'updated_by' => auth()->user()->id,
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
            'message' => 'Product has been Acknowledged Successfully'
        ], 200);

      
    }
}