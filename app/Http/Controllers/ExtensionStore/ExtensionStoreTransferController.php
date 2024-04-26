<?php

namespace App\Http\Controllers\ExtensionStore;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\ProductTransaction;
use App\Services\SerialNumberGenerator;
use App\Models\ProductRequisition;
use App\Models\ProductMovement;
use App\Models\Extension;
use App\Models\Region;
use DB;
use App\Models\Notification;
use Spatie\Permission\Models\Role;

class ExtensionStoreTransferController extends Controller {

    public function __construct() {
        $this->middleware('permission:extension-stores.view')->only('index', 'show');
        $this->middleware('permission:extension-stores.update')->only('update');
        $this->middleware('permission:extension-stores.view-extension-requisitions')->only('viewExtensionRequisition');
        $this->middleware('permission:extension-stores.get-extension-requisitions')->only('getExtensionRequisition');
        $this->middleware('permission:extension-stores.extension-to-extension-transfers')->only('extensionTransfer');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    //extension store history
    public function index() {
        try {
            $user = auth()->user();
            $roles = $user->roles;

            $isSuperUser = false;
            foreach($roles as $role) {
                if($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            if($isSuperUser) {
                // $transactions = ProductMovement::with('product', 'region', 'extension')->orderBy('status')->where('region_extension_id',!null)->get();
                $receiveProducts =
                    ProductTransaction::join('product_movements as Tb1', 'Tb1.id', 'product_transactions.product_movement_id')
                        ->with('product', 'product.saleType', 'region', 'extension')
                        ->select('Tb1.regional_transfer_id', 'product_transactions.*')
                        ->orderBy('product_transactions.id')->where('product_transactions.receive', '!=', 0)
                        ->where('product_transactions.region_extension_id', '!=', null)->get();

                $transactions = ProductMovement::query()
                    ->select('product_movements.requisition_number', 'product_movements.regional_transfer_id', DB::raw('SUM(product_movements.receive) as total_qty'), 'product_movements.status')
                    ->join('products', 'products.id', 'product_movements.product_id')
                    ->where('product_movements.status', 'process')
                    ->where('region_extension_id', '!=', null)
                    ->groupBy('product_movements.requisition_number', 'product_movements.regional_transfer_id', 'product_movements.status')
                    ->get();
            } 
            else {
                $transactions = ProductMovement::query()
                    ->select('product_movements.requisition_number', 'product_movements.regional_transfer_id', DB::raw('SUM(product_movements.receive) as total_qty'), 'product_movements.status')
                    ->join('products', 'products.id', 'product_movements.product_id')
                    ->where('product_movements.status', 'process')
                    ->groupBy('product_movements.requisition_number', 'product_movements.regional_transfer_id', 'product_movements.status')
                    ->LoggedInAssignExtension()
                    ->get();
                $receiveProducts = ProductTransaction::join('product_movements as Tb1', 'Tb1.id', 'product_transactions.product_movement_id')
                    ->with('product', 'product.saleType', 'region', 'extension')
                    ->select('Tb1.regional_transfer_id', 'product_transactions.*')
                    ->orderBy('product_transactions.id')->where('product_transactions.receive', '!=', 0)
                    ->LoggedInAssignExtension()->get();

                if($transactions->isEmpty()) {
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

    //get details of requested product for acknowledging

    public function show($id) {
        try {
            $receiveProduct = ProductMovement::with('product', 'region', 'extension', 'product.SaleType', 'product.color', 'product.subCategory')->where('status', 'process')->where('requisition_number', $id)->get();
            $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);

            if(!$receiveProduct) {
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
    //acknowledging product requisition
    public function update(Request $request, $id) {
        // $this->validate($request, [
        //     'received_date' => 'required',
        // ]);
        DB::beginTransaction();
        try {

            $receiveProduct = ProductMovement::with('product')->where('requisition_number', $id)->get();
            $jsonData = $request->json()->all();
            foreach($jsonData as $item) {
                $item_id = $item['id'];
                $received_date = date('Y-m-d', strtotime($item['received_date']));
                $description = $item['transfer_description'];
                $itemProduct = ProductMovement::with('product')->findOrFail($item_id);

                $itemProduct->received_date = $received_date;
                $itemProduct->status = 'receive';
                $itemProduct->save();

                if($itemProduct->$item_id != null) {
                    $requisition = ProductRequisition::find($receiveProduct->$item_id);

                    $requisition->update([
                        'status' => 'supplied',
                    ]);
                }

                //check if there is  product in that particular regional
                $transaction = ProductTransaction::where('product_id', $itemProduct->product_id)->where('region_extension_id', $itemProduct->region_extension_id)->first();

                if($transaction) {

                    //total receive product so far
                    $totalReceive = $transaction->receive;
                    $StoreQuatity = $transaction->store_quantity;

                    $product = Product::where('id', '=', $itemProduct->product_id)->first();
                    //updating the total product
                    if($product->sale_type_id == 2) {
                        $transaction->update([
                            'receive' => $totalReceive + $itemProduct->receive,
                            'store_quantity' => $StoreQuatity + $itemProduct->receive,
                            'updated_by' => auth()->user()->id,
                        ]);
                    } else {
                        $transaction->update([
                            'receive' => $itemProduct->receive,
                            'store_quantity' => $itemProduct->receive,
                            'updated_by' => auth()->user()->id,
                        ]);
                    }


                } else { //if there is no transaction in that particular extension then create new transaction


                    $productTransaction = new ProductTransaction;
                    $productTransaction->product_movement_id = $itemProduct->id;
                    $productTransaction->product_id = $itemProduct->product_id;
                    $productTransaction->region_extension_id = $itemProduct->region_extension_id;
                    $productTransaction->requisition_number = $itemProduct->requisition_number;
                    $productTransaction->movement_date = $itemProduct->movement_date;
                    $productTransaction->received_date = $received_date;
                    $productTransaction->receive = $itemProduct->receive;
                    $productTransaction->store_quantity = $itemProduct->receive;
                    $productTransaction->region_store_quantity = 0;
                    $productTransaction->region_transfer_quantity = 0;
                    $productTransaction->sold_quantity = 0;
                    $productTransaction->status = 'receive';
                    $productTransaction->sale_status = 'stock';
                    $productTransaction->created_by = auth()->user()->id;
                    $productTransaction->description = $description;
                    $productTransaction->save();


                    $product_requisition = ProductRequisition::where('requisition_number', $id)->first();
                    $product_table = Product::where('id', $itemProduct->product_id)->first();
                    $store = Store::where('extension_id', $itemProduct->region_extension_id)->first();

                    $extensionStoreQty = $product_table->extension_store_qty;
                    $product_table->update([
                        'store_id' => $store->id,
                        'updated_by' => auth()->user()->id,
                    ]);

                    $product_requisition->requested_extension == null ? $product_table->update([
                        'extension_store_qty' => $extensionStoreQty + $itemProduct->receive,
                    ]) : null;

                }
                $notification = Notification::where('requisition_number', '=', $id);
                $notification->update([
                    'status' => 'acknowledged'
                ]);
            }


            DB::commit();
            return response()->json([
                'message' => 'Product has been acknowledged successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    //view extension requisitions
    public function getExtensionRequisition() {
        try {
            $user = auth()->user();
            $roles = $user->roles;

            $isSuperUser = false;
            foreach($roles as $role) {
                if($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            $requisitions = [];
            if($isSuperUser) {
                $requisitions = ProductRequisition::select('region_extension_id', 'requisition_number', DB::raw('SUM(request_quantity - transfer_quantity) as request_quantity'))
                    ->with('saleType', 'region', 'extension')
                    ->where('status', 'requested')
                    ->where('requested_extension', '!=', null)
                    ->groupBy('region_extension_id', 'requisition_number')
                    ->get();


            } else {
                $requisitions = ProductRequisition::select('region_extension_id', 'requisition_number', DB::raw('SUM(request_quantity - transfer_quantity) as request_quantity'))
                    ->with('saleType', 'region', 'extension')
                    ->where('status', 'requested')
                    ->where('requested_extension', $user->assignAndEmployee->extension_id)
                    ->groupBy('region_extension_id', 'requisition_number')
                    ->get();

            }


            //check that paricular reqNo number
            if(!$requisitions) {
                return response()->json([
                    'message' => 'No requisitions found'
                ], 404);
            }

            return response([
                'message' => 'success',
                'requisitions' => $requisitions,

            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    //get extension requisition details using requisition ID
    public function viewExtensionRequisition($reqNo) {
        try {
            $user = auth()->user();
            $roles = $user->roles;

            $isSuperUser = false;
            foreach($roles as $role) {
                if($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            $requisitions = [];
            if($isSuperUser) {
                $requisitions = ProductRequisition::with('region', 'extension', 'saleType')->where('requisition_number', $reqNo)->where('status', 'requested')->get();
                $products = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->where('store_quantity', '!=', 0)->where('region_extension_id', '!=', null)->get();

                $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);

            } else {
                $requisitions = ProductRequisition::with('region', 'extension', 'saleType')->where('requisition_number', $reqNo)->where('status', 'requested')->get();

                $products = ProductTransaction::with('product', 'region', 'extension', 'product.saleType')->orderBy('id')->where('store_quantity', '!=', 0)->LoggedInAssignExtension()->get();

                $regions = Region::with('extensions:id,regional_id,name')->orderBy('name')->get(['id', 'name']);
            }


            //check that paricular reqNo number
            if(!$requisitions) {
                return response()->json([
                    'message' => 'The Requisition Number you are trying to find doesn\'t exist.'
                ], 404);
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



    //transfering from extension to extension
    public function extensionTransfer(Request $request, SerialNumberGenerator $serial) {
        $this->validate($request, []);

        DB::beginTransaction();
        try {

            $date = date('Y-m-d', strtotime($request->transfer_date));
            $regionId = $request->region;
            $extensionId = $request->extension;
            $requisitionId = $request->product_requisition;

            foreach($request->productDetails as $key => $value) {
                $transferQuantity = $value['transfer_quantity'];
                $product = Product::where('serial_no', $value['serial_no'])
                    ->join('product_transactions as Tb1', 'Tb1.product_id', '=', 'products.id')
                    ->first();
                //here we should update the status of requisition after  it is transfer 
                //first search for the requisition using its id                        
                $requisition = ProductRequisition::findOrFail($value['requisition_id']);
                $productTable = Product::where('serial_no', $value['serial_no'])->first();
                $transaction = ProductTransaction::where('product_id', $product->product_id)->where('region_extension_id', $requisition->requested_extension)->first();


        
                $extensionStoreQty = $productTable->extension_store_qty;

                //check when transfer quantity should not be greater than the stock quantity in
                if($transferQuantity > $extensionStoreQty) {
                    return response()->json([
                        'message' => 'Transfer Quantity should not be greater than the quantity in stock'
                    ], 422);
                }

                //if stock quantity is greater than transfer quantity tha saleStatus should be stock and if zero then transfer 
                if($extensionStoreQty > $transferQuantity) {
                    $saleStatus = "stock";
                } else {
                    $saleStatus = "transfer";
                }
                $totalDistribute = $productTable->extension_distributed_qty;
                //product table should be updated after transfer of the product
                $productTable->update([
                    'extension_distributed_qty' => $totalDistribute + $transferQuantity,
                    'sale_status' => $saleStatus,
                ]);
                $storeQty = $transaction->store_quantity;

                $extensionTransferQty = $transaction->extension_transfer_quantity;

                $transaction->update([
                    'store_quantity' => $storeQty - $transferQuantity,
                    'extension_transfer_quantity' => $extensionTransferQty + $transferQuantity
                ]);


                //update
                //increment the count only if the sale type is of either phone or sim
                //if it is not one of them then update transfer_quantity value with the value send via the request
                if($product->sale_type_id == 1 || $product->sale_type_id == 3) {
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

                // Get the 'admin' role instance
                $superUsersRole = Role::where('is_super_user', 1)->get();


                // Get all users with the 'admin' role
                $superUsers = User::role($superUsersRole)->get();

                // Iterate over each user and perform actions and create notification when transfering
                foreach($superUsers as $user) {
                    $notification = new Notification;
                    $notification->user_id = $user->id;
                    $notification->extension_from = $requisition->requested_extension;
                    $notification->extension_to = $request->extension;
                    $notification->requisition_number = $request->product_requisition;
                    $notification->created_date = $date;
                    $notification->product_id = $productTable->id;
                    $notification->quantity = $value['transfer_quantity'];
                    $notification->created_by = auth()->user()->id;
                    $notification->status = 'process';
                    $notification->read = false;
                    $notification->save();
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
}