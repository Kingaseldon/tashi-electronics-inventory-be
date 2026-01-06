<?php

namespace App\Http\Controllers\ExtensionStore;

use App\Http\Controllers\Controller;
use App\Imports\SaleProduct;
use App\Models\DiscountType;
use App\Models\Product;
use App\Models\SaleVoucherDetail;
use App\Services\SerialNumberGenerator;
use Illuminate\Http\Request;
use App\Models\PaymentHistory;
use App\Models\ProductTransaction;
use App\Models\SaleVoucher;
use App\Models\Customer;
use App\Models\Bank;
use App\Models\gstMaster;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            $gstTax = GstMaster::pluck('gst_amount')->first();


            if ($isSuperUser) {
                $saleVouchers = SaleVoucher::with('saleVoucherDetails.discount', 'user', 'customer')->orderBy('created_at', 'DESC')->where('region_extension_id', '!=', null)->get();
                $customers = Customer::with('customerType')->orderBy('id')->get();


                $products = ProductTransaction::with('product', 'region', 'extension')->orderBy('id')->where('store_quantity', '>', 0)->where('region_extension_id', '!=', null)->orderBy('id')->get();

                if ($saleVouchers->isEmpty()) {
                    $saleVouchers = [];
                }
                return response([
                    'message' => 'success',
                    'saleVouchers' => $saleVouchers,
                    'products' => $products,
                    'customers' => $customers,
                    'gst' => $gstTax
                ], 200);
            } else {
                $saleVouchers = SaleVoucher::with('saleVoucherDetails.discount', 'user', 'customer')->orderBy('created_at', 'DESC')->LoggedInAssignExtension()->get();
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
                    'gst' => $gstTax
                ], 200);
            }
        } catch (\Exception $e) {
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
                $product = ProductTransaction::with('product', 'region', 'product.category', 'product.subcategory', 'product.color', 'product.saleType')->where('product_id', $id)->where('region_extension_id', '!=', null)->where('store_quantity', '>', 0)->get();
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
        } catch (\Exception $e) {
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
        $invoiceNo = $invoice->extensionInvoiceNumber('SaleVoucher', 'invoice_date', $extensionId, $extensionName);

        // return response()->json($request->all());
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $roles = $user->roles;
            $gstTax = GstMaster::pluck('gst_amount')->first();


            $isSuperUser = false;

            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            if ($isSuperUser) {

                // Create Sale Voucher
                $saleVoucher = new SaleVoucher;
                $saleVoucher->invoice_no = $invoiceNo;
                $saleVoucher->invoice_date = $request->invoice_date ?? now();
                $saleVoucher->region_extension_id = $extensionId;
                $saleVoucher->customer_id = $request->customer;
                $saleVoucher->status = "open";
                $saleVoucher->remarks = $request->remarks;
                $saleVoucher->walk_in_customer = $request->walk_in_customer ?? null;
                $saleVoucher->contact_no = $request->contact_no ?? null;
                $saleVoucher->cid_no = $request->cid_no ?? null;
                $saleVoucher->gross_payable = 0;
                $saleVoucher->net_payable = 0;
                $saleVoucher->service_charge = ($request->service_charge ?? 0);
                $saleVoucher->save();

                $saleOrderDetails = [];
                $netPayable = 0;
                $grossPayable = 0;
                $totalGst = 0;

                $errorSerialNumbers = [];

                // Determine data source: Excel or manual
                $productsData = [];

                if ($request->customerType == 2 && $request->hasFile('attachment')) {
                    $request->validate(['attachment' => 'required|mimes:xls,xlsx']);
                    $file = $request->file('attachment');
                    $nestedCollection = Excel::toCollection(new SaleProduct, $file);
                    $flattenedArray = $nestedCollection->flatten(1)->toArray();
                    $productsData = array_slice($flattenedArray, 1); // Skip header row
                } else {
                    $productsData = $request->productDetails;
                }

                foreach ($productsData as $item) {
                    if ($request->customerType == 2) {
                        // Excel data format
                        $serialNo = $item[1];
                        $quantity = $item[3];
                        $discountNameText = trim($item[2]);
                        $productTransaction = ProductTransaction::with('product')
                            ->join('products as Tb1', 'Tb1.id', '=', 'product_transactions.product_id')
                            ->where('region_extension_id', $extensionId)
                            ->where('Tb1.serial_no', $serialNo)
                            ->first();

                        if (!$productTransaction) {
                            $errorSerialNumbers[] = $serialNo;
                            continue;
                        }

                        if ($productTransaction->store_quantity < $quantity) {
                            return response()->json([
                                'success' => false,
                                'message' => "Quantity for serial $serialNo cannot exceed store quantity",
                            ], 406);
                        }

                        $product = $productTransaction->product;
                        $grossForEachItem = $quantity * $product->price;

                        $discountName = DiscountType::where('discount_name', 'like', $discountNameText)->first();
                        $netPay = $grossForEachItem;
                        if ($discountName) {
                            if ($discountName->discount_type === 'Percentage') {
                                $netPay -= ($discountName->discount_value / 100) * $grossForEachItem;
                            } else {
                                $netPay -= $discountName->discount_value;
                            }
                        }
                    } else {
                        // Manual product data
                        $product = Product::find($item['product']);
                        $quantity = $item['quantity'];
                        $grossForEachItem = $quantity * $product->price;
                        $netPay = $item['total_amount'];
                        $discountName = isset($item['discount_type_id']) ? DiscountType::find($item['discount_type_id']) : null;
                    }

                    $itemGst = $netPay * $gstTax;
                    $itemTotal = $netPay + $itemGst;

                    $saleOrderDetails[] = [
                        'sale_voucher_id' => $saleVoucher->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => round($product->price, 2),
                        'gst' => round($itemGst, 2),
                        'total' => round($itemTotal, 2),
                        'discount_type_id' => $discountName->id ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];


                    $netPayable += $itemTotal + ($request->service_charge ?? 0);
                    $grossPayable += $grossForEachItem + $itemGst + ($request->service_charge ?? 0);

                    $totalGst += $itemGst;

                    // Update store quantities
                    $productTransaction = ProductTransaction::where('product_id', $product->id)
                        ->where('region_extension_id', $extensionId)
                        ->first();

                    if ($productTransaction->store_quantity < $quantity) {
                        return response()->json([
                            'success' => false,
                            'message' => "Quantity for serial $serialNo cannot exceed store quantity",
                        ], 406);
                    }


                    $productTransaction->update([
                        'store_quantity' => $productTransaction->store_quantity - $quantity,
                        'sold_quantity' => $productTransaction->sold_quantity + $quantity,
                    ]);

                    $product->update([
                        'extension_store_qty' => $product->extension_store_qty - $quantity,
                        'extension_store_sold_qty' => $product->extension_store_sold_qty + $quantity,
                        'updated_by' => auth()->id(),
                    ]);

                    // Transaction audit
                    $store = Store::where('extension_id', $extensionId)->first();
                    DB::table('transaction_audits')->insert([
                        'store_id' => $store->id,
                        'sales_type_id' => $product->sale_type_id,
                        'product_id' => $product->id,
                        'item_number' => $product->item_number,
                        'description' => $product->description,
                        'stock' => - ($productTransaction->sold_quantity),
                        'sales' => $productTransaction->sold_quantity,
                        'created_date' => $request->invoice_date ?? now(),
                        'status' => 'sale',
                        'created_at' => now(),
                        'created_by' => auth()->id(),
                    ]);
                }

                if (!empty($errorSerialNumbers)) {
                    return response()->json([
                        'success' => false,
                        'message' => "These serial numbers are not found",
                        'serialNumbers' => $errorSerialNumbers
                    ], 203);
                }
                // dd($grossPayable);
                if (!empty($saleOrderDetails)) {
                    $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);
                }

                // Update sale voucher totals
                // update to sale voucher
                SaleVoucher::where('id', $saleVoucher->id)->update([
                    'net_payable' => $netPayable,
                    'gross_payable' => $grossPayable,
                    'total_gst' => round($totalGst, 2)
                ]);
            }

            //if not a super user
            else { // if not a super user
                if ($request->customerType == 2) {

                    $request->validate([
                        'attachment' => 'required|mimes:xls,xlsx',
                    ]);

                    if ($request->hasFile('attachment')) {
                        $file = $request->file('attachment');
                        $nestedCollection = Excel::toCollection(new SaleProduct, $file);
                        $flattenedArrays = $nestedCollection->flatten(1)->toArray();

                        // Filter out empty rows
                        $flattenedArrays = array_filter($flattenedArrays, function ($row) {
                            return !empty(array_filter($row, fn($value) => !is_null($value)));
                        });

                        // Remove header row
                        array_shift($flattenedArrays);

                        $saleVoucher = SaleVoucher::create([
                            'invoice_no' => $invoiceNo,
                            'invoice_date' => $request->invoice_date ?? now(),
                            'region_extension_id' => $extensionId,
                            'customer_id' => $request->customer,
                            'service_charge' => ($request->service_charge ?? 0),
                            'status' => 'open',
                            'remarks' => $request->remarks,
                        ]);

                        $netPayable = 0;
                        $grossPayable = 0;
                        $totalGst = 0;
                        $saleOrderDetails = [];
                        $errorSerialNumbers = [];
                        $errorMessage = "These serial numbers are not found";

                        foreach ($flattenedArrays as $data) {
                            $product = ProductTransaction::with('product')
                                ->join('products as Tb1', 'Tb1.id', '=', 'product_transactions.product_id')
                                ->LoggedInAssignExtension()
                                ->where('Tb1.serial_no', $data[1])
                                ->where('Tb1.description', $data[0])
                                ->first();

                            if (!$product) {
                                $errorSerialNumbers[] = $data[1];
                                continue;
                            }

                            // Quantity check
                            if ($product->store_quantity < $data[3]) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Quantity cannot be greater than store quantity for serial_no ' . $product->serial_no,
                                ], 406);
                            }

                            // Price and discount calculation
                            $price = $data[4] ?? $product->price;
                            $grossForEachItem = $data[3] * $price; //price * queantity



                            $discountName = DiscountType::where('discount_name', 'like', trim($data[2]))->first();
                            if ($discountName) {
                                $netPay = ($discountName->discount_type === 'Percentage')
                                    ? $grossForEachItem - (($discountName->discount_value / 100) * $grossForEachItem)
                                    : $grossForEachItem - $discountName->discount_value;
                            } else {
                                $netPay = $grossForEachItem;
                            }


                            $gstAmount =  $netPay * $gstTax;
                            $netPayable += $netPay + $gstAmount + ($request->service_charge ?? 0);
                            $grossPayable += $grossForEachItem + $gstAmount + ($request->service_charge ?? 0);

                            $totalGst += $gstAmount;



                            // Update product quantities
                            $regionTransfer = ProductTransaction::where('product_id', $product->id)->LoggedInAssignExtension()->first();
                            $regionTransfer->update([
                                'store_quantity' => $regionTransfer->store_quantity - $data[3],
                                'sold_quantity' => $regionTransfer->sold_quantity + $data[3],
                            ]);

                            $product_table = Product::find($product->id);
                            $product_table->update([
                                'extension_store_qty' => $regionTransfer->store_quantity,
                                'extension_store_sold_qty' => $regionTransfer->sold_quantity,
                                'updated_by' => auth()->user()->id,
                            ]);

                            // Prepare SaleVoucherDetail
                            $saleOrderDetails[] = [
                                'sale_voucher_id' => $saleVoucher->id,
                                'product_id' => $product->id,
                                'quantity' => $data[3],
                                'gst' => $netPay + $gstAmount,
                                'price' => $price,
                                'total' =>  $netPay + $gstAmount,
                                'discount_type_id' => $discountName->id ?? null,
                            ];

                            // Insert transaction audit
                            $store = Store::where('extension_id', $extensionId)->first();
                            DB::table('transaction_audits')->insert([
                                'store_id' => $store->id,
                                'sales_type_id' => $product->sale_type_id,
                                'product_id' => $product->id,
                                'item_number' => $product->item_number,
                                'description' => $product->description,
                                'stock' => -$data[3],
                                'sales' => $data[3],
                                'created_date' => $request->invoice_date ?? now(),
                                'status' => 'sale',
                                'created_at' => now(),
                                'created_by' => auth()->user()->id,
                            ]);
                        }

                        // Insert SaleVoucherDetails once
                        if (!empty($saleOrderDetails)) {
                            SaleVoucherDetail::insert($saleOrderDetails);
                        }

                        // Handle missing serials
                        if (!empty($errorSerialNumbers)) {
                            return response()->json([
                                'success' => false,
                                'message' => $errorMessage,
                                'serialNumbers' => $errorSerialNumbers,
                            ], 203);
                        }

                        // Update SaleVoucher totals
                        $saleVoucher->update([
                            'net_payable' => round($netPayable, 2),
                            'gross_payable' => round($grossPayable, 2),
                            'total_gst' => round($totalGst, 2),

                        ]);
                    }
                } else { // if not a super user and no attachment
                    $saleVoucher = SaleVoucher::create([
                        'invoice_no' => $invoiceNo,
                        'invoice_date' => $request->invoice_date ?? now(),
                        'customer_id' => $request->customer,
                        'region_extension_id' => $extensionId,
                        'walk_in_customer' => $request->walk_in_customer,
                        'contact_no' => $request->contact_no,
                        'cid_no' => $request->cid_no ?? null,
                        'service_charge' => ($request->service_charge ?? 0),
                        'status' => 'open',
                        'remarks' => $request->remarks,
                    ]);

                    $netPayable = 0;
                    $grossPayable = 0;
                    $totalGst = 0;
                    $saleOrderDetails = [];


                    foreach ($request->productDetails as $value) {
                        $product = ProductTransaction::where('product_id', $value['product'])
                            ->LoggedInAssignExtension()
                            ->first();

                        // dd($product);

                        $storequantity = $product->store_quantity;
                        $soldquantity = $product->sold_quantity;



                        if ($storequantity < $value['quantity']) {
                            return response()->json([
                                'success' => false,
                                'message' => "Quantity for serial cannot exceed store quantity",
                            ], 406);
                        }

                        // Update product quantities
                        $product->update([
                            'store_quantity' => $storequantity - $value['quantity'],
                            'sold_quantity' => $soldquantity + $value['quantity'],
                        ]);

                        $product_table = Product::find($value['product']);
                        $product_table->update([
                            'extension_store_qty' => $product->store_quantity,
                            'extension_store_sold_qty' => $product->sold_quantity,
                            'updated_by' => auth()->user()->id,
                        ]);

                        // Calculate totals
                        $price = $value['product_cost'];
                        $grossForEachItem = $value['quantity'] * $price;


                        if (isset($value['discount_type_id']) && !empty($value['discount_type_id'])) {
                            $discountName = DiscountType::find($value['discount_type_id']);
                            $netPay = ($discountName->discount_type === 'Percentage')
                                ? $grossForEachItem - (($discountName->discount_value / 100) * $grossForEachItem)
                                : $grossForEachItem - $discountName->discount_value;
                        } else {
                            $netPay = $grossForEachItem;
                        }

                        $gstAmount = $netPay * $gstTax;
                        $netPayable += $netPay + $gstAmount + ($request->service_charge ?? 0);
                        $grossPayable += $grossForEachItem + $gstAmount + ($request->service_charge ?? 0);

                        $totalGst += $gstAmount;

                        $saleOrderDetails[] = [
                            'sale_voucher_id' => $saleVoucher->id,
                            'product_id' => $value['product'],
                            'quantity' => $value['quantity'],
                            'gst' => $netPay * $gstTax,
                            'price' => $price,
                            'total' => $netPay + $gstAmount,
                            'discount_type_id' => $value['discount_type_id'] ?? null,
                        ];

                        // Insert transaction audit
                        $store = Store::where('extension_id', $extensionId)->first();
                        DB::table('transaction_audits')->insert([
                            'store_id' => $store->id,
                            'sales_type_id' => $product_table->sale_type_id,
                            'product_id' => $product_table->id,
                            'item_number' => $product_table->item_number,
                            'description' => $product_table->description,
                            'stock' => -$value['quantity'],
                            'sales' => $value['quantity'],
                            'created_date' => $request->invoice_date ?? now(),
                            'status' => 'sale',
                            'created_at' => now(),
                            'created_by' => auth()->user()->id,
                        ]);
                    }

                    // Insert SaleVoucherDetails once
                    if (!empty($saleOrderDetails)) {
                        SaleVoucherDetail::insert($saleOrderDetails);
                    }

                    // Update SaleVoucher totals
                    $saleVoucher->update([
                        'net_payable' => round($netPayable, 2),
                        'gross_payable' => round($grossPayable, 2),
                        'total_gst' => round($totalGst, 2),
                    ]);
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
            'message' => 'Sale Voucher created Successfully',
            'invoice' => $invoiceNo
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    //show sale voucher to do payment)
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
            $saleVoucher = SaleVoucher::with('customer.customerType', 'saleVoucherDetails.product.saleType', 'saleVoucherDetails.discount', 'user.assignAndEmployee.extension', 'user.assignAndEmployee.region', 'emi_user')->find($id);
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
        } catch (\Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }


    public function extensionPayment(Request $request)
    {
        DB::beginTransaction();
        try {


            $request->validate([
                'attachment' => 'nullable|file|mimes:jpg,jpeg,png|max:2048', // 2MB max
            ]);

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '.' . $file->extension();

                // Store the file and get the path
                $filePath = $file->storeAs('public/images', $fileName);

                // Convert to accessible URL (optional)
                $filePath = Storage::url($filePath);
            } else {
                $filePath = ''; // Handle the case when no file is uploaded.
            }

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
            $paymentHistory->attachment = $filePath;
            $paymentHistory->save();

            //close the payment status(partial/full) when paymet is full
            if ($request->payment_status == "full") {
                $sale = SaleVoucher::findOrFail($request->sale_voucher_id);

                $sale->update([
                    'status' => 'closed'
                ]);
            }
        } catch (\Exception $e) {
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
