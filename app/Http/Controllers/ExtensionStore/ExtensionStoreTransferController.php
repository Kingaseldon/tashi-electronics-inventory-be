<?php

namespace App\Http\Controllers\ExtensionStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductTransaction;
use App\Services\SerialNumberGenerator;
use App\Models\ProductRequisition;
use App\Models\ProductMovement;
use App\Models\Extension;
use App\Models\Region;
use DB;

class ExtensionStoreTransferController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:extension-stores.view')->only('index', 'show');
        $this->middleware('permission:extension-stores.update')->only('update');
        // $this->middleware('permission:extension-stores.get-extension-transfer')->only('getExtensionTransfer');       
        // $this->middleware('permission:extension-stores.get-transfer-extension')->only('transferExtensionProduct');       
        // $this->middleware('permission:extension-stores.extension-transfer')->only('extensionTransfer');       
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
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
                $transactions = ProductMovement::with('product', 'region', 'extension')->orderBy('status')->where('region_extension_id',!null)->get();
                $receiveProducts = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('receive', '!=', 0)->where('region_extension_id',!null)->get();
            }
            else {
                $transactions = ProductMovement::with('product', 'region', 'extension')->orderBy('status')->LoggedInAssignExtension()->get();
                $receiveProducts = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('receive', '!=', 0)->LoggedInAssignExtension()->get();
                if ($transactions->isEmpty()) {
                    $transactions = [];
                }

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
    public function show($id)
    {
        try {
            $receiveProduct = ProductMovement::with('product', 'region', 'extension', 'product.SaleType','product.color', 'product.subCategory')->findOrFail($id);
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

            //check if there is  product in that particular extension
            $transaction = ProductTransaction::where('product_id', $receiveProduct->product_id)->where('region_extension_id', $receiveProduct->region_extension_id)->first();
            if ($transaction) {
                //total receive product so far
                $totalReceive = $transaction->receive;
                $regionStoreQuatity = $transaction->region_store_quantity;
                //updating the total product
                $transaction->update([
                    'receive' => $totalReceive + $receiveProduct->receive,
                    'store_quantity' => $totalReceive + $receiveProduct->receive,
                    'region_store_quantity' => $regionStoreQuatity - $receiveProduct->receive,
                    'region_transfer_quantity' => $receiveProduct->receive,
                    'sold_quantity' => 0,
                    'updated_by' => auth()->user()->id,
                ]);
            } 
            else { //if there is no transaction in that particular extension then create new transaction
             
                //update existing region store quantity 
                $productTransactions = ProductTransaction::where('product_id', $receiveProduct->product_id)->first();
             
                if($productTransactions==null){
                    //inserting new row
                    $productTransaction = new ProductTransaction;
                    $productTransaction->product_movement_id = $receiveProduct->id;
                    $productTransaction->product_id = $receiveProduct->product_id;
                    $productTransaction->region_extension_id = $receiveProduct->region_extension_id;
                    $productTransaction->requisition_number = $receiveProduct->requisition_number;
                    $productTransaction->movement_date = $receiveProduct->movement_date;
                    $productTransaction->received_date = $request->received_date;
                    $productTransaction->receive = $receiveProduct->receive;
                    $productTransaction->store_quantity = $receiveProduct->receive;
                    $productTransaction->region_store_quantity = 0;
                    $productTransaction->region_transfer_quantity = 0;
                    $productTransaction->sold_quantity = 0;
                    $productTransaction->status = 'receive';
                    $productTransaction->sale_status = 'stock';
                    $productTransaction->created_by = auth()->user()->id;
                    $productTransaction->description = $request->transfer_description;
                    $productTransaction->save();
                }
                else{
                    $regionStoreQuatity = $productTransactions->region_store_quantity;

                    $productTransactions->update([
                        'region_store_quantity' => $regionStoreQuatity - $receiveProduct->receive,
                        'store_quantity' => $regionStoreQuatity - $receiveProduct->receive,
                        'region_transfer_quantity' => $receiveProduct->receive,
                        'updated_by' => auth()->user()->id,
                    ]);
                    //inserting new row
                    $productTransaction = new ProductTransaction;
                    $productTransaction->product_movement_id = $receiveProduct->id;
                    $productTransaction->product_id = $receiveProduct->product_id;
                    $productTransaction->region_extension_id = $receiveProduct->region_extension_id;
                    $productTransaction->requisition_number = $receiveProduct->requisition_number;
                    $productTransaction->movement_date = $receiveProduct->movement_date;
                    $productTransaction->received_date = $request->received_date;
                    $productTransaction->receive = $receiveProduct->receive;
                    $productTransaction->store_quantity = $receiveProduct->receive;
                    $productTransaction->region_store_quantity = 0;
                    $productTransaction->region_transfer_quantity = 0;
                    $productTransaction->sold_quantity = 0;
                    $productTransaction->status = 'receive';
                    $productTransaction->sale_status = 'stock';
                    $productTransaction->created_by = auth()->user()->id;
                    $productTransaction->description = $request->transfer_description;
                    $productTransaction->save();
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
            'message' => 'Product has been acknowledged successfully'
        ], 200);
    }

    //get extension transfer index page
    public function getExtensionTransfer(Request $request)
    {
        try {
             $extensionId = auth()->user()->assignAndEmployee->extension_id;
            $giveProducts = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('receive', '!=', 0)->LoggedInAssignExtension()->get();

            if ($giveProducts->isEmpty()) {
                $giveProducts = [];
            }
            return response([
                'message' => 'success',
                'transaction' => $giveProducts,
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    //get extension specific transfer product
    public function transferExtensionProduct(Request $request, $id)
    {
        try {
            $transferToRegional = ProductTransaction::with('product.unit', 'product.brand', 'product.store', 'product.category', 'product.saleType', 'product.subCategory', 'region', 'extension')->findOrFail($id);
            $particularExtension = Extension::where('id', $transferToRegional->region_extension_id)->first();
            $regions = Region::where('id', $particularExtension->regional_id)->get();
            $particularExtensions = Extension::where('id', '!=', $transferToRegional->region_extension_id)->where('regional_id', $particularExtension->regional_id)->orderBy('id', 'desc')->get();
            $requisitions = ProductRequisition::with('product')->orderBy('id')->get();


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
    //transfering from extension
    public function extensionTransfer(Request $request, SerialNumberGenerator $serial)
    {
        DB::beginTransaction();

        try {
            $productTransfer = ProductTransaction::with('product')->findOrFail($request->transaction_id);
            //unique number generator
            $movementNo = $serial->movementNumber('ProductMovement', 'movement_date');

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
            $productMovement->extension_transfer_id = $productTransfer->extension->name;
            $productMovement->regional_id = $request->region_name;
            $productMovement->region_extension_id = $request->extension_name;
            $productMovement->movement_date = $request->transfer_date;
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
