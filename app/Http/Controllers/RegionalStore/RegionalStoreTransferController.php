<?php

namespace App\Http\Controllers\RegionalStore;

use App\Http\Controllers\Controller;
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
                $transactions = ProductMovement::where('status', 'process')->with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->get();
                $receiveProducts = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')
                    ->join('product_movements as Tb1', 'Tb1.id', 'product_transactions.product_movement_id')
                    ->select('product_transactions.*', 'Tb1.regional_transfer_id')
                    ->where('product_transactions.receive', '!=', 0)
                    ->orderBy('id')
                    ->get();

                // The user has a role with is_super_user set to 1
            } else {
                $transactions = ProductMovement::where('status', 'process')->with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->loggedInAssignRegion()->get();
                $receiveProducts = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')
                    ->join('product_movements as Tb1', 'Tb1.id', 'product_transactions.product_movement_id')
                    ->select('product_transactions.*', 'Tb1.regional_transfer_id')
                    ->where('product_transactions.receive', '!=', 0)
                    ->orderBy('product_transactions.id')
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
            if (isset($requisitions)) {
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
    public function show($id)
    {
        try {
            $receiveProduct = ProductMovement::with('product.saleType', 'region', 'extension', 'product.color', 'product.subCategory')->findOrFail($id);
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

    ///product acknowledge
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'received_date' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $receiveProduct = ProductMovement::with('product')->findOrFail($id);

            $receiveProduct->received_date = $request->received_date;
            $receiveProduct->status = 'receive';
            $receiveProduct->save();

            //sending the product if requsition is there
            if ($receiveProduct->product_requisition_id != null) {
                $requisition = ProductRequisition::find($receiveProduct->product_requisition_id);

                $requisition->update([
                    'status' => 'supplied',
                ]);
            }

            //check if there is  product in that particular regional
            $transaction = ProductTransaction::where('product_id', $receiveProduct->product_id)->where('regional_id', $receiveProduct->regional_id)->first();
            if ($transaction) {
                //total receive product so far
                $totalReceive = $transaction->receive;
                //updating the total product
                $transaction->update([
                    'receive' => $totalReceive + $receiveProduct->receive,
                    'store_quantity' => $totalReceive + $receiveProduct->receive,
                    'region_store_quantity' => $totalReceive + $receiveProduct->receive,
                    'sold_quantity' => 0,
                    'updated_by' => auth()->user()->id,
                ]);
            } else { //if there is no transaction in that particular regional then create new transaction

                $productTransaction = new ProductTransaction;

                $productTransaction->product_movement_id = $receiveProduct->id;
                $productTransaction->product_id = $receiveProduct->product_id;
                $productTransaction->regional_id = $receiveProduct->regional_id;
                $productTransaction->requisition_number = $receiveProduct->requisition_number;
                $productTransaction->movement_date = $receiveProduct->movement_date;
                $productTransaction->received_date = $request->received_date;
                $productTransaction->receive = $receiveProduct->receive;
                $productTransaction->store_quantity = $receiveProduct->receive;
                $productTransaction->region_store_quantity = $receiveProduct->receive;
                $productTransaction->sold_quantity = 0;
                $productTransaction->status = 'receive';
                $productTransaction->sale_status = 'stock';
                $productTransaction->created_by = auth()->user()->id;
                $productTransaction->description = $request->transfer_description;
                $productTransaction->save();
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