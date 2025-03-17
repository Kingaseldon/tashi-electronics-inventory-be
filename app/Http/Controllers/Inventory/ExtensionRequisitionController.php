<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\Product;
use App\Models\ProductRequisition;
use App\Models\ProductTransaction;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SerialNumberGenerator;

class ExtensionRequisitionController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function __construct() {
        $this->middleware('permission:extension-requisitions.view')->only('index', 'show');
        $this->middleware('permission:extension-requisitions.requisition-lists')->only('requisitionList');
        $this->middleware('permission:extension-requisitions.update')->only('update');
        $this->middleware('permission:extension-requisitions.store')->only('store');


    }
    public function index() {
        try {
            $user = auth()->user();
            $assignExtension = $user->assignAndEmployee;

            $products = DB::table('product_transactions as pt')
                ->select(
                    'pt.id as id',
                    'p.id as product_id',
                    'pt.region_extension_id as extension_id',
                    'e.name as extension',
                    'pt.store_quantity as qty',
                    'p.description',
                    'p.item_number',
                    'p.category_id',
                    's.name as category',
                    'p.sub_category_id',
                    'sub.name as sub_category',
                    'c.name as color',
                    'p.price'
                )
                ->leftJoin('products as p', 'pt.product_id', '=', 'p.id')
                ->leftJoin('extensions as e', 'pt.region_extension_id', '=', 'e.id')
                ->leftJoin('sale_types as s', 'p.category_id', '=', 's.id')
                ->leftJoin('sub_categories as sub', 'p.sub_category_id', '=', 'sub.id')
                ->leftJoin('colors as c', 'p.color_id', '=', 'c.id')
                ->where('pt.region_extension_id', '!=', null)->where('pt.region_extension_id', '!=', $assignExtension->extension_id)
                ->where('pt.store_quantity', '>', 0)
                ->get();

            // Access the result as an array or object
// For example, you can use $result->id, $result->product_id, etc.
            ;
            return response([
                'message' => 'success',
                'products' => $products

            ], 200);

        } catch (\Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function requisitionList() {

        try {
            $employees = User::where('id', auth()->user()->id)->with('assignAndEmployee.region', 'assignAndEmployee.extension')->first();
            $extensionProducts = " ";
            $products = " ";
            $assignType = " ";
            if($employees->assignAndEmployee->regional_id) {
                $assignType = "region";
                $products = Product::with('saleType')->select('item_number', 'description', 'sale_type_id', DB::raw('SUM(main_store_qty) as total_quantity'))
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
                $products = Product::with('saleType')->select('item_number', 'description', 'sale_type_id', DB::raw('SUM(main_store_qty) as total_quantity'))
                    ->groupBy('item_number', 'sale_type_id', 'description')
                    ->get();
            }
            $requisitions = ProductRequisition::with('saleType', 'region', 'extension', 'createdBy')->where('status', 'requested')->where('region_extension_id', $employees->assignAndEmployee->extension_id)->where('requested_extension', '!=',null)->orderBy('id')->get();
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
    public function create() {
        //
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
            $requestedExtension = $request->requested_extension;
            foreach($request->productDetails as $key => $value) {
                $requisition[$key]['requisition_number'] = $requestedNo;
                $requisition[$key]['product_item_number'] = $value['product_item_number'];
                $requisition[$key]['sale_type_id'] = $value['sale_type'];
                $requisition[$key]['description'] = $value['description'];
                $requisition[$key]['regional_id'] = $employees->assignAndEmployee->regional_id;
                $requisition[$key]['region_extension_id'] = $employees->assignAndEmployee->extension_id;
                $requisition[$key]['request_date'] = $date;
                $requisition[$key]['requisition_to'] = $requisitionTo;
                $requisition[$key]['requested_extension'] = $requestedExtension;
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
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        try {

            $products = DB::table('product_transactions as pt')
                ->select(
                    'pt.region_extension_id as extension_id',
                    'e.name as extension',
                    DB::raw('SUM(pt.store_quantity) as qty'),
                    'p.description',
                    'p.item_number',
                    'p.category_id',
                    's.name as category',
                    'p.sub_category_id',
                    'sub.name as sub_category',
                    DB::raw('COALESCE(c.name, "--") as color'),
                    'p.price'
                )
                ->leftJoin('products as p', 'pt.product_id', '=', 'p.id')
                ->leftJoin('extensions as e', 'pt.region_extension_id', '=', 'e.id')
                ->leftJoin('sale_types as s', 'p.category_id', '=', 's.id')
                ->leftJoin('sub_categories as sub', 'p.sub_category_id', '=', 'sub.id')
                ->leftJoin('colors as c', 'p.color_id', '=', 'c.id')
                ->where('pt.region_extension_id', $id)
                ->where('pt.store_quantity', '>', 0)
                ->groupBy('p.sub_category_id', 'pt.region_extension_id', 'e.name', 'p.description', 'p.item_number', 'p.category_id', 's.name', 'sub.name', 'c.name', 'p.price')
                ->get();


            return response([
                'message' => 'success',
                'products' => $products

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
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }
}
