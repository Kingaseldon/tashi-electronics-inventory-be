<?php

namespace App\Http\Controllers\MainStore;

use App\Http\Controllers\Controller;
use App\Imports\SaleProduct;
use App\Models\DiscountType;
use App\Services\SerialNumberGenerator;
use Illuminate\Http\Request;
use App\Models\PaymentHistory;
use App\Models\SaleVoucher;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Bank;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\DB as FacadesDB;
use Storage;
use Maatwebsite\Excel\Facades\Excel;


class MainStoreSaleController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:main-store-sales.view')->only('index', 'show');
        $this->middleware('permission:main-store-sales.store')->only('store');
        $this->middleware('permission:main-store-sales.update')->only('update');
        $this->middleware('permission:main-store-sales.make-payments')->only('makePayment');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $saleVouchers = SaleVoucher::with('saleVoucherDetails.discount', 'user')->orderBy('created_at', 'DESC')->where('regional_id', null)->where('region_extension_id', null)->get();
            $customers = Customer::with('customerType')->orderBy('id')->get();
            $products = Product::where('main_store_qty', '!=', 0)->with('unit', 'brand', 'store', 'category', 'subCategory', 'saleType')->orderBy('id')->get();

            if ($saleVouchers->isEmpty()) {
                $saleVouchers = [];
            }
            return response([
                'message' => 'success',
                'saleVouchers' => $saleVouchers,
                'products' => $products,
                'customers' => $customers,
            ], 200);
        } catch (Execption $e) {
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

    public function store(Request $request, SerialNumberGenerator $invoice)
    {

        DB::beginTransaction();

        //unique number generator
        $invoiceNo = $invoice->mainInvoiceNumber('SaleVoucher', 'invoice_date');

        try {

            if ($request->customerType == 2) {
                $request->validate([
                    'attachment' => 'required|mimes:xls,xlsx',
                ]);
                if (($request->hasFile('attachment')) == true) {
                    $file = $request->file('attachment');
                    $nestedCollection = Excel::toCollection(new SaleProduct, $file);
                    // Flatten and transform the nested collection into a simple array
                    $flattenedArrays = $nestedCollection->flatten(1)->toArray();

                    // Filter out rows with all null values
                    $flattenedArray = array_filter($flattenedArrays, function ($row) {
                        return !empty(array_filter($row, function ($value) {
                            return !is_null($value);
                        }));
                    });
                    $saleVoucher = new SaleVoucher;
                    $saleVoucher->invoice_no = $invoiceNo;
                    $saleVoucher->invoice_date = $request->invoice_date ?? date('Y-m-d', strtotime(Carbon::now()));
                    $saleVoucher->customer_id = $request->customer;
                    $saleVoucher->status = "open";
                    $saleVoucher->remarks = $request->remarks;
                    $saleVoucher->save();
                    $lastInsertedId = $saleVoucher->id;

                    $netPayable = 0;
                    $grossPayable = 0;

                    $errorMessage = "These serial numbers are not found";
                    $errorSerialNumbers = [];
                    $saleOrderDetails = [];
                    for ($i = 1; $i < count($flattenedArray); $i++) {
                        $product = Product::where('serial_no', $flattenedArray[$i][0])->first(); //serial number of exel

                        if ($product) { // serial number present

                            if ($product->main_store_qty < $flattenedArray[$i][2]) {
                                return response()->json([
                                    'success' => true,
                                    'message' => 'Quantity cannot be greater that store quantity',
                                ], 203);
                            }

                            $saleOrderDetails[$i]['sale_voucher_id'] = $saleVoucher->id;
                            $saleOrderDetails[$i]['product_id'] = $product->id;
                            $saleOrderDetails[$i]['quantity'] = $flattenedArray[$i][2]; //get the quantity data in exel file
                            $saleOrderDetails[$i]['price'] = $product->price;

                            $grossForEachItem = $flattenedArray[$i][2] * $product->price;

                            $discountName = DiscountType::where('discount_name', 'like', trim($flattenedArray[$i][1]))->first(); // search discount id based on name

                            if ($discountName) {
                                if ($discountName->discount_type === 'Percentage') {
                                    $netPay = $grossForEachItem - (($discountName->discount_value / 100) * $grossForEachItem);
                                } else { // lumpsum
                                    $netPay = $grossForEachItem - $discountName->discount_value;
                                }
                            } else { // discount is not defined
                                $netPay = $grossForEachItem;
                            }
                            // net payable
                            $saleOrderDetails[$i]['total'] = $netPay;
                            $netPayable += $netPay; // Accumulate the total in the grand total
                            $netPayable = round($netPayable, 2);
                            // gross payable
                            $saleOrderDetails[$i]['price'] = $grossForEachItem;
                            $grossPayable += $grossForEachItem; // Accumulate the total in the grand total
                            $grossPayable = round($grossPayable, 2);

                            $saleOrderDetails[$i]['discount_type_id'] = $discountName->id ?? null;

                            $quantityafterDistribute = $product->total_quantity;

                            $product->update([
                                'main_store_qty' => $quantityafterDistribute - $flattenedArray[$i][2],
                                'main_store_sold_qty' => $flattenedArray[$i][2],
                            ]);
                        } else {
                            $errorSerialNumbers[] = $flattenedArray[$i][0];
                        }

                        $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);

                        FacadesDB::table('transaction_audits')->insert([
                            'store_id' => 1,
                            'sales_type_id' => $product->sale_type_id, // Corrected variable name
                            'product_id' =>  $product->id,
                            'item_number' => $product->item_number,
                            'description' =>  $product->description,
                            'stock' =>  - ($flattenedArray[$i][2]),
                            'sales' =>  $flattenedArray[$i][2],
                            'created_date' => $request->invoice_date ?? now(),
                            'status' => 'sale',
                            'created_at' => now(),
                            'created_by' => auth()->user()->id,
                        ]);
                    } // foreach ends
                    if (count($errorSerialNumbers) > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => $errorMessage,
                            'serialNumbers' => $errorSerialNumbers
                        ], 203);
                    }

                    // update to sale voucher
                    SaleVoucher::where('id', $lastInsertedId)->update([
                        'net_payable' => $netPayable,
                        'gross_payable' => $grossPayable
                    ]);
                }
            } else { //if no attachment uploaded
                $saleVoucher = new SaleVoucher;
                $saleVoucher->invoice_no = $invoiceNo;
                $saleVoucher->invoice_date = $request->invoice_date ?? date('Y-m-d', strtotime(Carbon::now()));
                $saleVoucher->customer_id = $request->customer;
                $saleVoucher->walk_in_customer = $request->walk_in_customer;
                $saleVoucher->contact_no = $request->contact_no;
                $saleVoucher->gross_payable = $request->gross_payable;
                // $saleVoucher->discount_type = $request->discount_type;
                // $saleVoucher->discount_rate = $request->discount_rate;
                $saleVoucher->net_payable = $request->net_payable;
                $saleVoucher->status = "open";
                $saleVoucher->remarks = $request->remarks;
                $saleVoucher->save();

                $saleOrderDetails = [];

                foreach ($request->productDetails as $key => $value) {
                    $saleOrderDetails[$key]['sale_voucher_id'] = $saleVoucher->id;
                    $saleOrderDetails[$key]['product_id'] = $value['product'];
                    $saleOrderDetails[$key]['quantity'] = $value['quantity'];
                    $saleOrderDetails[$key]['price'] = $value['product_cost'];
                    $saleOrderDetails[$key]['total'] = $value['total_amount'];

                    $saleOrderDetails[$key]['discount_type_id'] = isset($value['discount_type_id']) == true ? $value['discount_type_id'] : null;

                    $mainTransfer = Product::find($value['product']);
                    $quantityafterDistribute = $mainTransfer->total_quantity;
                    $main_sold = $mainTransfer->main_store_sold_qty;

                    $mainTransfer->update([
                        'main_store_qty' => $quantityafterDistribute - $value['quantity'],
                        'main_store_sold_qty' => $main_sold + $value['quantity'],
                    ]);
                    // $saleOrderDetails[$key]['created_by'] = $request->user()->id;


                    FacadesDB::table('transaction_audits')->insert([
                        'store_id' => 1,
                        'sales_type_id' => $mainTransfer->sale_type_id, // Corrected variable name
                        'product_id' =>  $mainTransfer->id,
                        'item_number' => $mainTransfer->item_number,
                        'description' =>  $mainTransfer->description,
                        'stock' =>  - ($value['quantity']),
                        'sales' =>  $value['quantity'],
                        'created_date' => $request->invoice_date ?? now(),
                        'status' => 'sale',
                        'created_at' => now(),
                        'created_by' => auth()->user()->id,
                    ]);
                }
                $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Sale Voucher created Successfully'
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, SerialNumberGenerator $receipt)
    {
        try {
            $saleVoucher = SaleVoucher::with('customer.customerType', 'saleVoucherDetails.product.saleType', 'saleVoucherDetails.discount', 'user.assignAndEmployee.region', 'user.assignAndEmployee.extension')->find($id);

            $paymentHistory = PaymentHistory::where('sale_voucher_id', $saleVoucher->id)->orderBy('receipt_no', 'desc')->get();
            $invoicePayment = $paymentHistory->sum('total_amount_paid');
            $receiptNo = $receipt->mainReceiptNumber('PaymentHistory', 'paid_at');
            $bank = Bank::with('region', 'extension')->orderBy('id')->get();

            if (!$saleVoucher) {
                return response()->json([
                    'message' => 'The Sale Voucher you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'saleVoucher' => $saleVoucher,
                'paymentHistory' => $paymentHistory,
                'invoicePayment' => $invoicePayment,
                'receiptNo' => $receiptNo,
                'bank' => $bank,
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }


    public function makePayment(Request $request)
    {
        DB::beginTransaction();
        try {
            $paymentHistory = new PaymentHistory;

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '.' . $file->extension();
                $file->storeAs('public/images', $fileName);
            } else {
                $fileName = ''; // Handle the case when no file is uploaded.
            }

            $paymentHistory->sale_voucher_id = $request->sale_voucher_id;
            $paymentHistory->receipt_no = $request->receipt_no;
            $paymentHistory->payment_mode = $request->payment_mode;
            $paymentHistory->reference_no = $request->reference_no;
            $paymentHistory->payment_status = $request->payment_status;
            $paymentHistory->bank_id = $request->bank;
            $paymentHistory->cash_amount_paid = $request->cash_amount_paid;
            $paymentHistory->online_amount_paid = $request->online_amount_paid;
            $paymentHistory->total_amount_paid = $request->total_amount_paid;
            $paymentHistory->paid_at = $request->payment_date;
            $paymentHistory->remarks = $request->remarks;
            $paymentHistory->attachment = $fileName;
            $paymentHistory->save();

            //close the payment status when paymet is full
            if ($request->payment_status == "full") {
                $sale = SaleVoucher::findOrFail($request->sale_voucher_id);

                $sale->update([
                    'status' => 'closed'
                ]);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Sale Voucher Payment made Successfully'
        ], 200);
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
