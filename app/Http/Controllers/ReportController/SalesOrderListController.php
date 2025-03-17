<?php

namespace App\Http\Controllers\ReportController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SalesOrderListController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {

        $this->middleware('permission:salesorderlist.view')->only('index');
    }
    public function index(Request $request)
    {
        try {
            if ($request->region_extension_id == 'ALL') {
                $orderlist = DB::table('sale_vouchers as sv')
                    ->select(
                        'sv.invoice_no',
                        'sv.invoice_date',
                        DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.customer_name ELSE sv.walk_in_customer END AS customerName'),
                        DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.contact_no ELSE sv.contact_no END AS contactNo'),
                        DB::raw('COALESCE(ph.payment_mode, "--") AS paymentMode'),
                        DB::raw('COALESCE(ph.receipt_no, "--") AS receiptNo'),
                        DB::raw('COALESCE(b.name, "--") AS bankName'),
                        DB::raw('COALESCE(ph.reference_no, "--") AS referenceNo'),
                        DB::raw('COALESCE(ph.paid_at, "--") AS paidAt'),
                        'u.name',
                        'sv.status',
                        'p.description',
                        'p.price',
                        'p.serial_no'
                    )
                    ->leftJoin('customers as c', 'sv.customer_id', '=', 'c.id')
                    ->leftJoin('payment_histories as ph', 'sv.id', '=', 'ph.sale_voucher_id')
                    ->leftJoin('banks as b', 'ph.bank_id', '=', 'b.id')
                    ->leftJoin('users as u', 'sv.created_by', '=', 'u.id')
                    ->leftJoin('sale_voucher_details as svd', 'sv.id', '=', 'svd.sale_voucher_id')
                    ->leftJoin('products as p', 'p.id', '=', 'svd.product_id')
                    ->where(function ($query) use ($request) { // Use $request in the closure
                        $query->when('ALL' === $request->category_id, function ($subquery) {
                            $subquery->whereRaw('1 = 1');
                        }, function ($subquery) use ($request) {
                            $subquery->where('p.category_id', '=', $request->category_id);
                        });
                    })
                    ->where(function ($query) use ($request) { // Use $request in the closure
                        $query->when('ALL' === $request->regional_id, function ($subquery) {
                            $subquery->whereRaw('1 = 1');
                        }, function ($subquery) use ($request) {
                            $subquery->where('sv.regional_id', '=', $request->regional_id);
                        });
                    })
                    ->where(function ($query) use ($request) { // Use $request in the closure
                        $query->when('ALL' === $request->region_extension_id, function ($subquery) {
                            $subquery->whereRaw('1 = 1');
                        }, function ($subquery) use ($request) {
                            $subquery->where('sv.region_extension_id', '=', $request->region_extension_id);
                        });
                    })
                    ->whereBetween(DB::raw('DATE_FORMAT(sv.invoice_date, "%Y-%m-%d")'), [$request->from_date, $request->to_date])
                    ->where('sv.status', 'open')
                    ->get();
            } else {
                $orderlist = DB::table('sale_vouchers as sv')
                    ->select(
                        'sv.invoice_no',
                        'sv.invoice_date',
                        DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.customer_name ELSE sv.walk_in_customer END AS customerName'),
                        DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.contact_no ELSE sv.contact_no END AS contactNo'),
                        DB::raw('COALESCE(ph.payment_mode, "--") AS paymentMode'),
                        DB::raw('COALESCE(ph.receipt_no, "--") AS receiptNo'),
                        DB::raw('COALESCE(b.name, "--") AS bankName'),
                        DB::raw('COALESCE(ph.reference_no, "--") AS referenceNo'),
                        DB::raw('COALESCE(ph.paid_at, "--") AS paidAt'),
                        'u.name',
                        'sv.status',
                        'p.description',
                        'p.price',
                        'p.serial_no'
                    )
                    ->leftJoin('customers as c', 'sv.customer_id', '=', 'c.id')
                    ->leftJoin('payment_histories as ph', 'sv.id', '=', 'ph.sale_voucher_id')
                    ->leftJoin('banks as b', 'ph.bank_id', '=', 'b.id')
                    ->leftJoin('users as u', 'sv.created_by', '=', 'u.id')
                    ->leftJoin('sale_voucher_details as svd', 'sv.id', '=', 'svd.sale_voucher_id')
                    ->leftJoin('products as p', 'p.id', '=', 'svd.product_id')
                    ->where(function ($query) use ($request) { // Use $request in the closure
                        $query->when('ALL' === $request->category_id, function ($subquery) {
                            $subquery->whereRaw('1 = 1');
                        }, function ($subquery) use ($request) {
                            $subquery->where('p.category_id', '=', $request->category_id);
                        });
                    })
                    ->where(function ($query) use ($request) { // Use $request in the closure
                        $query->when('ALL' === $request->regional_id, function ($subquery) {
                            $subquery->whereRaw('1 = 1');
                        }, function ($subquery) use ($request) {
                            $subquery->where('sv.regional_id', '=', $request->regional_id);
                        });
                    })
                    ->where(function ($query) use ($request) { // Use $request in the closure
                        $query->when('ALL' === $request->region_extension_id, function ($subquery) {
                            $subquery->whereRaw('1 = 1');
                        }, function ($subquery) use ($request) {
                            $subquery->where('sv.region_extension_id', '=', $request->region_extension_id);
                        });
                    })
                    ->whereBetween(DB::raw('DATE_FORMAT(sv.invoice_date, "%Y-%m-%d")'), [$request->from_date, $request->to_date])
                    ->where('sv.status', 'open')
                    ->where('u.id', auth()->user()->id)
                    ->get();
            }

            if ($orderlist->isEmpty()) {
                $orderlist = [];
            }
            return response([
                'message' => 'success',
                'orderlist' => $orderlist,

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
    public function show($id)
    {
        //
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
