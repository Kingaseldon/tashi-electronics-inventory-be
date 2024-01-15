<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\SerialNumberGenerator;
use Illuminate\Http\Request;
use App\Models\ProductRequisition;
use App\Models\ProductTransaction;
use App\Models\Product;
use App\Models\Extension;
use App\Models\Region;
use App\Models\User;
use DB;

class ProductRequisitionController extends Controller {
    public function __construct() {
        $this->middleware('permission:requisitions.view')->only('index', 'show');
        $this->middleware('permission:requisitions.store')->only('store');
        $this->middleware('permission:requisitions.update')->only('update');
        $this->middleware('permission:requisitions.edit-requisitions')->only('editRequisition');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {

        try {
            $employees = User::where('id', auth()->user()->id)->with('assignAndEmployee.region', 'assignAndEmployee.extension')->first();
            $extensionProducts = " ";
            $products = " ";
            $assignType = " ";
            if($employees->assignAndEmployee->regional_id) {
                $assignType = "region";
                $products = Product::with('saleType')->select('item_number', 'description', 'sale_type_id', \DB::raw('SUM(main_store_qty) as total_quantity'))
                    ->groupBy('item_number', 'sale_type_id', 'description')
                    ->get();
            } else {
                $assignType = "extension";
                $extension = Extension::where('id', auth()->user()->assignAndEmployee->extension_id)->first();
                $extensionProducts = ProductTransaction::selectRaw('products.item_number, products.description, SUM(region_store_quantity) as total_quantity, products.sale_type_id')
                    ->join('products', 'product_transactions.product_id', '=', 'products.id')
                    ->groupBy('products.item_number', 'products.description', 'products.sale_type_id')
                    ->where('regional_id', $extension->regional_id)
                    ->get();
                $products = Product::with('saleType')->select('item_number', 'description', 'sale_type_id', \DB::raw('SUM(main_store_qty) as total_quantity'))
                    ->groupBy('item_number', 'sale_type_id', 'description')
                    ->get();
            }
            $requisitions = ProductRequisition::with('saleType', 'region', 'extension', 'createdBy')->where('regional_id', $employees->assignAndEmployee->regional_id)->where('status', 'requested')->where('region_extension_id', $employees->assignAndEmployee->extension_id)->where('requested_extension', null)->orderBy('id')->get();
            $regions = Region::with('dzongkhag')->orderBy('id')->get();

            if($requisitions->isEmpty()) {
                $requisitions = [];
            }
            return response([
                'message' => 'success',
                'product' => $products,
                'requisitions' => $requisitions,
                'regions' => $regions,
                'extensionProducts' => $extensionProducts,
                'assignType' => $assignType,
            ], 200);

        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, SerialNumberGenerator $serial) {
        $this->validate($request, [

        ]);

        DB::beginTransaction();
        $employees = User::where('id', auth()->user()->id)->with('assignAndEmployee.region', 'assignAndEmployee.extension')->first();
        //unique number generator
        $requestedNo = $serial->requisitionNumber('ProductRequisition', 'request_date');
        try {

            $requisition = [];
            $date = date('Y-m-d', strtotime($request->request_date));
            $requisitionTo = $request->requisition_to;
            foreach($request->productDetails as $key => $value) {
                $requisition[$key]['requisition_number'] = $requestedNo;
                $requisition[$key]['product_item_number'] = $value['product_item_number'];
                $requisition[$key]['sale_type_id'] = $value['sale_type'];
                $requisition[$key]['description'] = $value['description'];
                $requisition[$key]['regional_id'] = $employees->assignAndEmployee->regional_id;
                $requisition[$key]['region_extension_id'] = $employees->assignAndEmployee->extension_id;
                $requisition[$key]['request_date'] = $date;
                $requisition[$key]['requisition_to'] = $requisitionTo;
                $requisition[$key]['status'] = 'requested';
                $requisition[$key]['request_quantity'] = $value['request_quantity'];
                $requisition[$key]['created_by'] = auth()->user()->id;
            }
            ProductRequisition::insert($requisition);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
        DB::commit();
        return response()->json([
            'message' => 'Product Requisition submitted Successfully'
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editRequisition($id) {
        try {
            $employees = User::where('id', auth()->user()->id)->with('assignAndEmployee.region', 'assignAndEmployee.extension')->first();
            $products = [];
            if($employees->assignAndEmployee->regional_id) {
                $products = Product::with('saleType')->select('item_number', 'description', 'sale_type_id', DB::raw('SUM(main_store_qty) as total_quantity'))
                    ->groupBy('item_number', 'sale_type_id', 'description')
                    ->get();
            }
            else{
                $products = Product::with('saleType')->select('item_number', 'description', 'sale_type_id', DB::raw('SUM(region_store_qty) as total_quantity'))
                    ->groupBy('item_number', 'sale_type_id', 'description')
                    ->get();
            }
           

            $requisition = ProductRequisition::where('regional_id', $employees->assignAndEmployee->regional_id)->where('status', '=', 'requested')->with('saleType', 'region', 'extension')->find($id);

            if(!$requisition) {
                return response()->json([
                    'message' => 'The Product Requisition you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'requisition' => $requisition,
                'products' => $products,
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
    public function update(Request $request, $id) {

        $this->validate($request, [
        ]);
        DB::beginTransaction();
        try {
            $requisition = ProductRequisition::find($id);
            $employees = User::where('id', auth()->user()->id)->with('assignAndEmployee.region', 'assignAndEmployee.extension')->first();

            if(!$requisition) {
                return response()->json([
                    'message' => 'The Product Requisition you are trying to update doesn\'t exist.'
                ], 404);
            }

            $requisition->product_item_number = $request->product_item_number;
            $requisition->regional_id = $employees->assignAndEmployee->regional_id;
            $requisition->region_extension_id = $employees->assignAndEmployee->extension_id;
            $requisition->request_date = $request->request_date;
            $requisition->sale_type_id = $request->sale_type;
            $requisition->request_quantity = $request->request_quantity;
            $requisition->description = $request->description;
            $requisition->save();

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Product Requisition has been updated Successfully'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        try {

            ProductRequisition::find($id)->delete();

            return response()->json([
                'message' => 'Product Requisition deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Product Requisition cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}
