<?php

namespace App\Http\Controllers\SalesAndOrder;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarrantyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:warranties.view')->only('index', 'show');


    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    //search products based on serial number
    public function show($id)
    {

        try {
            // $product = Product::where('serial_no', $id)->get();

           $product = DB::table('products as p')
                ->select(
                    'p.id as id',
                    'p.item_number',
                    'p.sale_type_id',
                    'st.name as product_type',
                    'p.sub_category_id',
                    'sc.name as sub_category',
                    'p.serial_no',
                    'p.batch_no',
                    'p.color_id',
                    'c.name as color',
                    'p.price',
                    'p.description as product_description',
                    'p.sub_inventory',
                    'p.locator',
                    'p.iccid',
                    'p.status',
                    'p.sale_status',
                    'p.main_store_sold_quantity',
                    'pt.id as product_transaction_id',
                    'pt.regional_id',
                    'r.name as region',
                    'pt.region_extension_id',
                    'e.name as extension',
                    'pt.received_date',
                    'pt.receive',
                    'pt.sold_quantity',
                    'sv.id as sale_voucher_id',
                    DB::raw("IF(sv.customer_id IS NOT NULL, cus.customer_name, sv.walk_in_customer) as customerName"),
                    DB::raw("IF(sv.customer_id IS NOT NULL, cus.contact_no, sv.contact_no) as contactNo"),
                    'dt.id as discount_type_id',
                    'dt.discount_name',
                    'dt.discount_type',
                    'dt.discount_value',
                    'sv.invoice_no',
                    'sv.invoice_date',
                    'sv.gross_payable',
                    'sv.net_payable',
                    'sv.remarks as sales_remarks',
                    'sv.status as payment_status'
                )
                ->leftJoin('product_transactions as pt', 'p.id', '=', 'pt.product_id')
                ->leftJoin('sale_types as st', 'p.sale_type_id', '=', 'st.id')
                ->leftJoin('sub_categories as sc', 'p.sub_category_id', '=', 'sc.id') 
                ->leftJoin('colors as c', 'p.color_id', '=', 'c.id')
                ->leftJoin('regions as r', 'pt.regional_id', '=', 'r.id')
                ->leftJoin('extensions as e', 'pt.region_extension_id', '=', 'e.id')
                ->leftJoin('sale_voucher_details as svd', 'p.id', '=', 'svd.product_id')
                ->leftJoin('sale_vouchers as sv', 'sv.id', '=', 'svd.sale_voucher_id')
                ->leftJoin('customers as cus', 'sv.customer_id', '=', 'cus.id')
                ->leftJoin('discount_types as dt', 'sv.discount_type_id', '=', 'dt.id')
                ->where('p.serial_no', $id)
                ->where(function ($query) {
                    $query->where('p.main_store_sold_quantity', '>', 0)
                        ->orWhere('pt.sold_quantity', '>', 0);
                })
                ->get();


            // You can use the $results as needed.


            if (!$product) {
                return response()->json([
                    'message' => 'The Serial no you are trying to find doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'products' => $product
            ], 200);
        } catch (Exception $e) {
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
        //
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