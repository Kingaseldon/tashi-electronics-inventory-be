<?php

namespace App\Http\Controllers\ExtensionStore;

use App\Http\Controllers\Controller;
use App\Imports\SaleProduct;
use App\Models\DiscountType;
use App\Models\Product;
use App\Services\SerialNumberGenerator;
use Illuminate\Http\Request;
use App\Models\PaymentHistory;
use App\Models\ProductTransaction;
use App\Models\SaleVoucher;
use App\Models\Customer;
use App\Models\Bank;
use Carbon\Carbon;
use DB;
use Maatwebsite\Excel\Facades\Excel;

class ExtensionStoreSaleController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:extension-store-sales.view')->only('index', 'show');
        $this->middleware('permission:extension-store-sales.store')->only('store');
        $this->middleware('permission:extension-store-sales.update')->only('update');
        $this->middleware('permission:extension-store-sales.extension-payments')->only('extensionPayment');
        $this->middleware('permission:extension-store-sales.extension-product-details')->only('ExtensionProductDetails');
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
                $saleVouchers = SaleVoucher::with('saleVoucherDetails.discount')->orderBy('id')->where('region_extension_id', !null)->get();
                $customers = Customer::with('customerType')->orderBy('id')->get();
                $products = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('store_quantity', '>', 0)->where('region_extension_id', !null)->orderBy('id')->get();

                if ($saleVouchers->isEmpty()) {
                    $saleVouchers = [];
                }
                return response([
                    'message' => 'success',
                    'saleVouchers' => $saleVouchers,
                    'products' => $products,
                    'customers' => $customers,
                ], 200);

            } else {
                $saleVouchers = SaleVoucher::with('saleVoucherDetails.discount')->orderBy('id')->LoggedInAssignExtension()->get();
                $customers = Customer::with('customerType')->orderBy('id')->get();
                $giveProducts = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('store_quantity', '!=', 0)->LoggedInAssignExtension()->get();

                if ($saleVouchers->isEmpty()) {
                    $saleVouchers = [];
                }
                return response([
                    'message' => 'success',
                    'saleVouchers' => $saleVouchers,
                    'products' => $giveProducts,
                    'customers' => $customers,
                ], 200);
            }

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

    //product details for sale voucher
    public function ExtensionProductDetails($id)
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
                $product = ProductTransaction::with('product', 'region', 'product.category', 'product.subcategory', 'product.color', 'product.saleType')->where('product_id', $id)->where('region_extension_id', !null)->where('store_quantity','>',0)->get();

            } else {
                $extensionId = auth()->user()->assignAndEmployee->extension_id;
                $product = ProductTransaction::with('product', 'region', 'product.category', 'product.subcategory', 'product.color', 'product.saleType')->where('product_id', $id)->where('store_quantity', '>', 0)->LoggedInAssignExtension()->get();
            }
            if (!$product) {
                return response()->json([
                    'message' => 'The Product you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'product' => $product,

            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function store(Request $request, SerialNumberGenerator $invoice)
    {
        $extensionId = '';
        $extensionName = '';
        if ($request->extension == null && $request->extensionName == "") {
            $extensionId = auth()->user()->assignAndEmployee->extension_id;
            $extensionName = auth()->user()->assignAndEmployee->extension->name;
        } else {
            $extensionId = $request->extension;
            $extensionName = $request->extensionName;
        }

        $wordArray = explode(' ', $extensionName);
        // The first element of the $wordArray will contain the first word
        $firstWord = $wordArray[0];
        //unique number generator
        $invoiceNo = $invoice->invoiceNumber('SaleVoucher', 'invoice_date', $extensionId, $firstWord);

        // return response()->json($request->all());
        DB::beginTransaction();

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
                if ($request->customerType == 2) {
                    $request->validate([
                        'attachment' => 'required|mimes:xls,xlsx',
                    ]);

                    if (($request->hasFile('attachment')) == true) {
                        $file = $request->file('attachment');
                        $nestedCollection = Excel::toCollection(new SaleProduct, $file);
                        // Flatten and transform the nested collection into a simple array
                        $flattenedArray = $nestedCollection->flatten(1)->toArray();

                        $saleVoucher = new SaleVoucher;
                        $saleVoucher->invoice_no = $invoiceNo;
                        $saleVoucher->invoice_date = date('Y-m-d', strtotime(Carbon::now()));
                        $saleVoucher->region_extension_id = $request->region;
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

                            $product = ProductTransaction::with('product')
                                ->join('products as Tb1', 'Tb1.id', '=', 'product_transactions.product_id')
                                ->where('regional_id', $extensionId)
                                ->where('Tb1.serial_no', $flattenedArray[$i][0])
                                ->first(); //serial number of exel

                            if ($product) { // serial number present


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

                                // gross payable
                                $saleOrderDetails[$i]['price'] = $grossForEachItem;
                                $grossPayable += $grossForEachItem; // Accumulate the total in the grand total


                                $saleOrderDetails[$i]['discount_type_id'] = $discountName->id ?? null;

                                $regionTransfer = ProductTransaction::where('product_id', $product->id)->where('region_extension_id', $extensionId)->first();
                                $storequantity = $regionTransfer->store_quantity;
                                $soldquantity = $regionTransfer->sold_quantity;

                                $regionTransfer->update([
                                    'store_quantity' => $storequantity - $flattenedArray[$i][2],
                                    'sold_quantity' => $soldquantity + $flattenedArray[$i][2],
                                    'region_store_quantity' => 0,
                                ]);



                            } else {
                                $errorSerialNumbers[] = $flattenedArray[$i][0];
                            }

                            $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);

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
                    $saleVoucher->invoice_date = date('Y-m-d', strtotime(Carbon::now()));
                    $saleVoucher->customer_id = $request->customer;
                    $saleVoucher->region_extension_id = $extensionId;
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

                        $regionTransfer = ProductTransaction::where('product_id', $value['product'])->where('region_extension_id', $extensionId)->first();
                        $storequantity = $regionTransfer->store_quantity;
                        $soldquantity = $regionTransfer->sold_quantity;

                        $regionTransfer->update([
                            'store_quantity' => $storequantity - $value['quantity'],
                            'sold_quantity' => $soldquantity + $value['quantity'],
                            'region_store_quantity' => 0,
                        ]);
                        // $saleOrderDetails[$key]['created_by'] = $request->user()->id;
                    }
                    $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);

                }

            } else { //if not a super user
                if ($request->customerType == 2) {
                    $request->validate([
                        'attachment' => 'required|mimes:xls,xlsx',
                    ]);

                    if (($request->hasFile('attachment')) == true) {
                        $file = $request->file('attachment');
                        $nestedCollection = Excel::toCollection(new SaleProduct, $file);
                        // Flatten and transform the nested collection into a simple array
                        $flattenedArray = $nestedCollection->flatten(1)->toArray();

                        $saleVoucher = new SaleVoucher;
                        $saleVoucher->invoice_no = $invoiceNo;
                        $saleVoucher->invoice_date = date('Y-m-d', strtotime(Carbon::now()));
                        $saleVoucher->region_extension_id = $extensionId;
                        $saleVoucher->customer_id = $request->customer;
                        $saleVoucher->status = "open";
                        $saleVoucher->remarks = $request->remarks;
                        $saleVoucher->save();

                        $lastInsertedId = $saleVoucher->id;

                        $netPayable = 0;
                        $grossPayable = 0;

                        $errorMessage = "These serial numbers are not found";
                        $errorSerialNumbers = [];
                        for ($i = 1; $i < count($flattenedArray); $i++) {

                            $product = ProductTransaction::with('product')
                                ->join('products as Tb1', 'Tb1.id', '=', 'product_transactions.product_id')
                                ->LoggedInAssignExtension()
                                ->where('Tb1.serial_no', $flattenedArray[$i][0])
                                ->first();

                            $saleOrderDetails = [];
                            if ($product) { // serial number present
                                $saleOrderDetails[$i]['sale_voucher_id'] = $saleVoucher->id;
                                $saleOrderDetails[$i]['product_id'] = $product->id;
                                $saleOrderDetails[$i]['quantity'] = $flattenedArray[$i][2]; //get the quantity data in exel file
                                $saleOrderDetails[$i]['price'] = $product->price;

                                $grossForEachItem = $flattenedArray[$i][2] * $product->price;

                                // $discountName = DiscountType::where('discount_name', 'like', '%' . $flattenedArray[$i][1] . '%')->first();
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

                                // gross payable
                                $saleOrderDetails[$i]['price'] = $grossForEachItem;
                                $grossPayable += $grossForEachItem; // Accumulate the total in the grand total


                                $saleOrderDetails[$i]['discount_type_id'] = $discountName->id ?? null;

                                $regionTransfer = ProductTransaction::where('product_id', $product->id)->LoggedInAssignExtension()->first();
                                $storequantity = $regionTransfer->store_quantity;
                                $soldquantity = $regionTransfer->sold_quantity;
                            

                                $regionTransfer->update([
                                    'store_quantity' => $storequantity - $flattenedArray[$i][2],
                                    'sold_quantity' => $soldquantity + $flattenedArray[$i][2],
                                    'region_store_quantity' => 0,
                                ]);


                            } else {
                                $errorSerialNumbers[] = $flattenedArray[$i][0];
                            }

                            $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);

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
                    $saleVoucher->invoice_date = date('Y-m-d', strtotime(Carbon::now()));
                    $saleVoucher->customer_id = $request->customer;
                    $saleVoucher->region_extension_id = $extensionId;
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

                        $regionTransfer = ProductTransaction::where('product_id', $value['product'])->LoggedInAssignExtension()->first();
                        $storequantity = $regionTransfer->store_quantity;
                        $soldquantity = $regionTransfer->sold_quantity;

                        $regionTransfer->update([
                            'store_quantity' => $storequantity - $value['quantity'],
                            'sold_quantity' => $soldquantity + $value['quantity'],
                             'region_store_quantity' => 0,
                        ]);
                        // $saleOrderDetails[$key]['created_by'] = $request->user()->id;
                    }
                    $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);
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
            $user = auth()->user();
            $roles = $user->roles;

            $isSuperUser = false;

            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            $saleVoucher = SaleVoucher::with('customer.customerType', 'saleVoucherDetails.product.saleType', 'saleVoucherDetails.discount', 'user.assignAndEmployee.extension', 'user.assignAndEmployee.region')->find($id);
            $paymentHistory = PaymentHistory::where('sale_voucher_id', $saleVoucher->id)->orderBy('receipt_no', 'desc')->get();
            $invoicePayment = $paymentHistory->sum('total_amount_paid');

            $extensionName = "";
            //for invoice name //super admin
            if ($isSuperUser) {
                $extensionName = $saleVoucher->extension->name;
            } else {
                $extensionName = auth()->user()->assignAndEmployee->extension->name;
            }
            //for invoice name
            // $extensionName = auth()->user()->assignAndEmployee->extension->name;
            $wordArray = explode(' ', $extensionName);
            // The first element of the $wordArray will contain the first word
            $firstWord = $wordArray[0];
            $receiptNo = $receipt->extensionReceiptNumber('PaymentHistory', 'paid_at', $firstWord);

            // return response()->json($receiptNo);
            $bank = Bank::orderBy('id')->get();

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


    public function extensionPayment(Request $request)
    {
        DB::beginTransaction();
        try {
            $paymentHistory = new PaymentHistory;

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
            $paymentHistory->save();

            //close the payment status(partial/full) when paymet is full
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