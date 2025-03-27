<?php

namespace App\Http\Controllers\ReportController;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class PostedSalesInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('permission:postedsalesinvoice.view')->only('index');
    }
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $roles = $user->roles;


            if ($request->region_extension_id == 'ALL' || $request->region_extension_id != 'ALL') {
                $employee = User::where('username', $user->username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();


                if ($employee->assignAndEmployee == null && !$employee->roles->contains('is_super_user', 1)) {
                    // Logic if the employee does not have the super user role


                    $sales = DB::table('sale_vouchers as sv')
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
                            'svd.total AS netpay',
                            'svd.price as price',
                            'svd.quantity',
                            'p.serial_no',
                            'ph.cash_amount_paid',
                            'ph.online_amount_paid',
                            'sv.net_payable',
                            'sv.gross_payable',
                            'd.discount_name',
                            'e.name as store'

                        )
                        ->leftJoin('customers as c', 'sv.customer_id', '=', 'c.id')
                        ->leftJoin('payment_histories as ph', 'sv.id', '=', 'ph.sale_voucher_id')
                        ->leftJoin('banks as b', 'ph.bank_id', '=', 'b.id')
                        ->leftJoin('users as u', 'sv.created_by', '=', 'u.id')
                        ->leftJoin('sale_voucher_details as svd', 'sv.id', '=', 'svd.sale_voucher_id')
                        ->leftJoin('products as p', 'p.id', '=', 'svd.product_id')
                        ->leftJoin('discount_types as d', 'd.id', '=', 'svd.discount_type_id')
                        ->leftJoin('extensions as e', 'sv.region_extension_id', '=', 'e.id')


                        ->where(function ($query) use ($request) { // Use $request in the closure
                            $query->when('ALL' === $request->category_id, function ($subquery) {
                                $subquery->whereRaw('1 = 1');
                            }, function ($subquery) use ($request) {
                                $subquery->where('p.category_id', '=', $request->category_id);
                            });
                        })
                        ->where(function ($query) use ($request) { // Use $request in the closure
                            $query->when('ALL' === null, function ($subquery) {
                                $subquery->whereRaw('1 = 1');
                            }, function ($subquery) use ($request) {
                                $subquery->where('sv.regional_id', '=', null);
                            });
                        })
                        ->where(function ($query) use ($request) { // Use $request in the closure
                            $query->when('ALL' === null, function ($subquery) {
                                $subquery->whereRaw('1 = 1');
                            }, function ($subquery) use ($request) {
                                $subquery->where('sv.region_extension_id', '=', null);
                            });
                        })
                        ->whereBetween(DB::raw('DATE_FORMAT(sv.invoice_date, "%Y-%m-%d")'), [$request->from_date, $request->to_date])
                        ->where('sv.status', 'closed')
                        ->orderBy('sv.invoice_date', 'DESC')
                        ->groupBy(
                            'sv.invoice_no',
                            'sv.invoice_date',
                            DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.customer_name ELSE sv.walk_in_customer END'),
                            DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.contact_no ELSE sv.contact_no END'),
                            DB::raw('COALESCE(ph.receipt_no, "--")'),
                            DB::raw('COALESCE(b.name, "--")'),
                            DB::raw('COALESCE(ph.reference_no, "--")'),
                            DB::raw('COALESCE(ph.paid_at, "--")'),
                            'ph.payment_mode',
                            'u.name',
                            'sv.status',
                            'p.description',
                            'price',
                            'total', // 'price' is an alias and can be used directly in the groupBy clause
                            'p.serial_no',
                            'ph.cash_amount_paid',
                            'ph.online_amount_paid',
                            'sv.net_payable',
                            'sv.gross_payable',
                            'svd.quantity',
                            'd.discount_name',
                            'e.name',
                            'sv.service_charge'
                        )
                        ->get();

                    $salesGrouped = $sales->groupBy(['invoice_no']);


                    // Prepare the grouped data for the API response
                    $responseData = [];

                    // Loop through each group of sales
                    foreach ($salesGrouped as $invoiceNo => $sales) {
                        $invoiceData = [
                            'invoice_no' => $invoiceNo,
                            'invoice_date' => $sales[0]->invoice_date,
                            'receipt_no' => $sales[0]->receiptNo,
                            'customer_name' => $sales[0]->customerName,
                            'payment_mode' => $sales[0]->paymentMode,
                            'online_amount' => $sales[0]->online_amount_paid,
                            'cash_amount' => $sales[0]->cash_amount_paid,
                            'bank_name' => $sales[0]->bankName,
                            'reference_no' => $sales[0]->referenceNo,
                            'customer_contact_no' => $sales[0]->contactNo,
                            'paid_date' => $sales[0]->paidAt,
                            'total_net_payable' => $sales[0]->net_payable,
                            'total_gross_payable' => $sales[0]->gross_payable,
                            'service_charge' => $sales[0]->service_charge,
                            'updated_by' => $sales[0]->name,
                            'discount_name' => $sales[0]->discount_name,
                            'discount_amount' => $sales[0]->gross_payable - $sales[0]->net_payable,
                            'store' => $sales[0]->store,


                        ];

                        // Loop through each sale within the group
                        foreach ($sales as $sale) {

                            $invoiceData['details'][] = [
                                'serialNumbers' => $sale->serial_no,
                                'price' => $sale->price,
                                'net_payable' => $sale->netpay,
                                'description' => $sale->description,
                                'status' => $sale->status,
                                'quantity' => $sale->quantity,
                                'discount_name' => $sales->discount_name,
                                'discount_amount' => $sale->price - $sale->netpay                                // Add other fields as needed
                            ];
                        }

                        // $responseData[] = $invoiceData;
                        $responseData[] = $invoiceData;
                    }
                    return response([
                        'message' => 'success',
                        'sales' => $responseData
                    ], 200);
                }

                $sales = DB::table('sale_vouchers as sv')
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
                        'svd.total AS netpay',
                        'svd.price as price',
                        'svd.quantity',
                        'p.serial_no',
                        'ph.cash_amount_paid',
                        'ph.online_amount_paid',
                        'sv.net_payable',
                        'sv.gross_payable',
                        'd.discount_name',
                        'e.name as store',
                        'sv.service_charge'

                    )
                    ->leftJoin('customers as c', 'sv.customer_id', '=', 'c.id')
                    ->leftJoin('payment_histories as ph', 'sv.id', '=', 'ph.sale_voucher_id')
                    ->leftJoin('banks as b', 'ph.bank_id', '=', 'b.id')
                    ->leftJoin('users as u', 'sv.created_by', '=', 'u.id')
                    ->leftJoin('sale_voucher_details as svd', 'sv.id', '=', 'svd.sale_voucher_id')
                    ->leftJoin('products as p', 'p.id', '=', 'svd.product_id')
                    ->leftJoin('discount_types as d', 'd.id', '=', 'svd.discount_type_id')
                    ->leftJoin('extensions as e', 'sv.region_extension_id', '=', 'e.id')
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
                    ->where('sv.status', 'closed')
                    ->orderBy('sv.invoice_date', 'DESC')
                    ->groupBy(
                        'sv.invoice_no',
                        'sv.invoice_date',
                        DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.customer_name ELSE sv.walk_in_customer END'),
                        DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.contact_no ELSE sv.contact_no END'),
                        DB::raw('COALESCE(ph.receipt_no, "--")'),
                        DB::raw('COALESCE(b.name, "--")'),
                        DB::raw('COALESCE(ph.reference_no, "--")'),
                        DB::raw('COALESCE(ph.paid_at, "--")'),
                        'ph.payment_mode',
                        'u.name',
                        'sv.status',
                        'p.description',
                        'price',
                        'total', // 'price' is an alias and can be used directly in the groupBy clause
                        'p.serial_no',
                        'ph.cash_amount_paid',
                        'ph.online_amount_paid',
                        'sv.net_payable',
                        'sv.gross_payable',
                        'svd.quantity',
                        'd.discount_name',
                        'e.name',
                        'sv.service_charge'
                    )
                    ->get();


                $salesGrouped = $sales->groupBy(['invoice_no']);



                // Prepare the grouped data for the API response
                $responseData = [];

                // Loop through each group of sales
                foreach ($salesGrouped as $invoiceNo => $sales) {
                    $invoiceData = [
                        'invoice_no' => $invoiceNo,
                        'invoice_date' => $sales[0]->invoice_date,
                        'receipt_no' => $sales[0]->receiptNo,
                        'customer_name' => $sales[0]->customerName,
                        'payment_mode' => $sales[0]->paymentMode,
                        'online_amount' => $sales[0]->online_amount_paid,
                        'cash_amount' => $sales[0]->cash_amount_paid,
                        'bank_name' => $sales[0]->bankName,
                        'reference_no' => $sales[0]->referenceNo,
                        'customer_contact_no' => $sales[0]->contactNo,
                        'paid_date' => $sales[0]->paidAt,
                        'total_net_payable' => $sales[0]->net_payable,
                        'total_gross_payable' => $sales[0]->gross_payable,
                        'service_charge' => $sales[0]->service_charge,
                        'updated_by' => $sales[0]->name,
                        'discount_name' => $sales[0]->discount_name,
                        'discount_amount' => $sales[0]->gross_payable - $sales[0]->net_payable,
                        'store' => $sales[0]->store,
                    ];

                    // Loop through each sale within the group
                    foreach ($sales as $sale) {

                        $invoiceData['details'][] = [
                            'serialNumbers' => $sale->serial_no,
                            'price' => $sale->price,
                            'net_payable' => $sale->netpay,
                            'description' => $sale->description,
                            'status' => $sale->status,
                            'quantity' => $sale->quantity,
                            'discount_name' => $sale->discount_name,
                            'discount_amount' => $sale->price - $sale->netpay
                            // Add other fields as needed
                        ];
                    }

                    $responseData[] = $invoiceData;
                }
            } else {

                $sales = DB::table('sale_vouchers as sv')
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
                        'svd.quantity',
                        'svd.total AS netpay',
                        'svd.price as price',
                        'p.serial_no',
                        'ph.cash_amount_paid',
                        'ph.online_amount_paid',
                        'sv.net_payable',
                        'sv.gross_payable',
                        'd.discount_name',
                        'e.name as store',
                        'sv.service_charge'
                    )
                    ->leftJoin('customers as c', 'sv.customer_id', '=', 'c.id')
                    ->leftJoin('payment_histories as ph', 'sv.id', '=', 'ph.sale_voucher_id')
                    ->leftJoin('banks as b', 'ph.bank_id', '=', 'b.id')
                    ->leftJoin('users as u', 'sv.created_by', '=', 'u.id')
                    ->leftJoin('sale_voucher_details as svd', 'sv.id', '=', 'svd.sale_voucher_id')
                    ->leftJoin('products as p', 'p.id', '=', 'svd.product_id')
                    ->leftJoin('discount_types as d', 'd.id', '=', 'svd.discount_type_id')
                    ->leftJoin('extensions as e', 'sv.region_extension_id', '=', 'e.id')


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
                    ->where('sv.status', 'closed')
                    ->where('u.id', auth()->user()->id)
                    ->orderBy('sv.invoice_date', 'DESC')
                    ->groupBy(
                        'sv.invoice_no',
                        'sv.invoice_date',
                        DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.customer_name ELSE sv.walk_in_customer END'),
                        DB::raw('CASE WHEN sv.customer_id IS NOT NULL THEN c.contact_no ELSE sv.contact_no END'),
                        DB::raw('COALESCE(ph.receipt_no, "--")'),
                        DB::raw('COALESCE(b.name, "--")'),
                        DB::raw('COALESCE(ph.reference_no, "--")'),
                        DB::raw('COALESCE(ph.paid_at, "--")'),
                        'ph.payment_mode',
                        'u.name',
                        'sv.status',
                        'p.description',
                        'price',
                        'total', // 'price' is an alias and can be used directly in the groupBy clause
                        'p.serial_no',
                        'ph.cash_amount_paid',
                        'ph.online_amount_paid',
                        'sv.net_payable',
                        'sv.gross_payable',
                        'svd.quantity',
                        'd.discount_name',
                        'e.name',
                        'sv.service_charge'
                    )
                    ->get();

                $salesGrouped = $sales->groupBy(['invoice_no']);


                // Prepare the grouped data for the API response
                $responseData = [];

                // Loop through each group of sales
                foreach ($salesGrouped as $invoiceNo => $sales) {
                    $invoiceData = [
                        'invoice_no' => $invoiceNo,
                        'invoice_date' => $sales[0]->invoice_date,
                        'receipt_no' => $sales[0]->receiptNo,
                        'customer_name' => $sales[0]->customerName,
                        'payment_mode' => $sales[0]->paymentMode,
                        'online_amount' => $sales[0]->online_amount_paid,
                        'cash_amount' => $sales[0]->cash_amount_paid,
                        'bank_name' => $sales[0]->bankName,
                        'reference_no' => $sales[0]->referenceNo,
                        'customer_contact_no' => $sales[0]->contactNo,
                        'paid_date' => $sales[0]->paidAt,
                        'total_net_payable' => $sales[0]->net_payable,
                        'total_gross_payable' => $sales[0]->gross_payable,
                        'service_charge' => $sales[0]->service_charge,
                        'updated_by' => $sales[0]->name,
                        'discount_name' => $sales[0]->discount_name,
                        'discount_amount' => $sales[0]->gross_payable - $sales[0]->net_payable,
                        'store' => $sales[0]->store,

                    ];

                    // Loop through each sale within the group
                    foreach ($sales as $sale) {
                        $invoiceData['details'][] = [
                            'serialNumbers' => $sale->serial_no,
                            'price' => $sale->price,
                            'net_payable' => $sale->netpay,
                            'description' => $sale->description,
                            'status' => $sale->status,
                            'quantity' => $sale->quantity,
                            'discount_name' => $sale->discount_name,
                            'discount_amount' => $sale->price - $sale->netpay                            // Add other fields as needed
                        ];
                    }

                    $responseData[] = $invoiceData;
                }
            }

            // if ($sales->isEmpty()) {
            //     $sales = [];
            // }
            return response([
                'message' => 'success',
                'sales' => $responseData,

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
