<?php

namespace App\Http\Controllers\ExtensionStore;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\Notification;
use App\Models\ProductMovement;
use App\Models\ProductRequisition;
use App\Models\ProductTransaction;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExtensionProductTransferController extends Controller
{


    public function __construct()
    {
        $this->middleware('permission:extension-transfers.view')->only('index', 'show');
        $this->middleware('permission:extension-transfers.update')->only('update');


    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $giveProducts = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('store_quantity', '!=', 0)->LoggedInAssignExtension()->get();

            if ($giveProducts->isEmpty()) {
                $giveProducts = [];
            }
            return response([
                'message' => 'success',
                'transaction' => $giveProducts,
            ], 200);

        } catch (\Exception $e) {
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
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

        } catch (\Exception $e) {
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
    public function edit($id)
    {
        //
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
        DB::beginTransaction();

        try {
            $productTransfer = ProductTransaction::with('product')->findOrFail($request->transaction_id);
            //unique number generator
            // $movementNo = $serial->movementNumber('ProductMovement', 'movement_date');

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
            // $productMovement->product_movement_no = $movementNo;
            $productMovement->status = 'process';
            $productMovement->created_by = auth()->user()->id;
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
    public function destroy($id)
    {
        //
    }
}
