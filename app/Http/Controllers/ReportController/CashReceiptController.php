<?php

namespace App\Http\Controllers\ReportController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class CashReceiptController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {

        $this->middleware('permission:cashreceipt.view')->only('index');


    }
    public function index(Request $request)
    {
        try {
            $categoryId = $request->category_id;
            $regionalId = $request->regional_id;
            $regionExtensionId = $request->region_extension_id;
            $startDate = $request->from_date;
            $endDate = $request->to_date;

            $query = DB::table('products AS pr')
                ->leftJoin('product_transactions AS pt', 'pt.product_id', '=', 'pr.id')
                ->leftJoin('sale_voucher_details AS svd', 'svd.product_id', '=', 'pr.id')
                ->leftJoin('sale_vouchers AS sv', 'sv.id', '=', 'svd.sale_voucher_id')
                ->leftJoin('payment_histories AS ph', 'ph.sale_voucher_id', '=', 'sv.id')
                ->leftJoin('users AS u', 'ph.created_by', '=', 'u.id')
                ->leftJoin('banks AS b', 'b.id', '=', 'ph.bank_id')
                ->select(
                    'ph.cash_amount_paid',
                    'ph.online_amount_paid',
                    'ph.total_amount_paid',
                    'ph.payment_mode',
                    'ph.receipt_no',
                    'b.name AS bankName',
                    'ph.reference_no',
                    'sv.status',
                    'sv.invoice_date',
                    'u.name AS createdBy',
                    'pr.description',
                    'pr.price',
                    'pr.serial_no'
                )
                ->where(function ($query) use ($categoryId) {
                    if ($categoryId != 'ALL') {
                        $query->where('pr.category_id', $categoryId);
                    }
                })
                ->where(function ($query) use ($regionalId) {
                    if ($regionalId != 'ALL') {
                        $query->where('sv.regional_id', $regionalId);
                    }
                })
                ->where(function ($query) use ($regionExtensionId) {
                    if ($regionExtensionId != 'ALL') {
                        $query->where('sv.region_extension_id', $regionExtensionId);
                    }
                })
                ->whereBetween(DB::raw('DATE_FORMAT(ph.paid_at, "%Y-%m-%d")'), [$startDate, $endDate])
                ->where(function ($query) {
                    $query->where('ph.payment_mode', 'cash')
                        ->orWhere('ph.payment_mode', 'both');
                });

            $cash = $query->distinct()->get();


            // if ($cash->isEmpty()) {
            //     $cash = [];
            // }
            return response([
                'message' => 'success',
                'cash' => $cash,

            ], 200);
        } catch (Execption $e) {
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
