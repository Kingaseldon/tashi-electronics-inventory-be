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
            $serviceCharge = $request->service_charge ?? 0;

            // Initialize totals
            $netPayable = 0;
            $grossPayable = 0;
            $totalGst = 0;
            $saleOrderDetails = [];
            $errorSerialNumbers = [];

            // Create SaleVoucher (common structure)
            $saleVoucherData = [
                'invoice_no' => $invoiceNo,
                'invoice_date' => $request->invoice_date ?? now(),
                'region_extension_id' => $extensionId,
                'customer_id' => $request->customer,
                'status' => 'open',
                'remarks' => $request->remarks,
                'walk_in_customer' => $request->walk_in_customer ?? null,
                'contact_no' => $request->contact_no ?? null,
                'cid_no' => $request->cid_no ?? null,
                'service_charge' => $serviceCharge,
            ];

            $saleVoucher = SaleVoucher::create($saleVoucherData);

            if ($isSuperUser) {
                // ---------- SUPER USER ----------
                if ($request->customerType == 2 && $request->hasFile('attachment')) {
                    // Excel Upload
                    $request->validate(['attachment' => 'required|mimes:xls,xlsx']);
                    $file = $request->file('attachment');
                    $nestedCollection = Excel::toCollection(new SaleProduct, $file);
                    $flattenedArray = $nestedCollection->flatten(1)->toArray();
                    $productsData = array_slice($flattenedArray, 1); // Skip header
                } else {
                    // Manual entry
                    $productsData = $request->productDetails;
                }

                foreach ($productsData as $item) {
                    // ---------- FETCH PRODUCT ----------
                    if ($request->customerType == 2) {
                        // Excel format
                        $serialNo = $item[1];
                        $quantity = (int)$item[3];

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
                        $price = $product->price;
                        $discountText = trim($item[2] ?? '');
                        $discount = DiscountType::where('discount_name', 'like', $discountText)->first();
                    } else {
                        // Manual format
                        $product = Product::findOrFail($item['product']);
                        $quantity = (int)$item['quantity'];
                        $price = $product->price;
                        $discount = isset($item['discount_type_id']) ? DiscountType::find($item['discount_type_id']) : null;

                        $productTransaction = ProductTransaction::where('product_id', $product->id)
                            ->where('region_extension_id', $extensionId)
                            ->first();

                        if ($productTransaction->store_quantity < $quantity) {
                            return response()->json([
                                'success' => false,
                                'message' => "Quantity cannot exceed store quantity",
                            ], 406);
                        }
                    }

                    // ---------- CALCULATIONS ----------
                    $gross = $price * $quantity;
                    $net = $gross;

                    if ($discount) {
                        $net = ($discount->discount_type === 'Percentage')
                            ? $gross - (($discount->discount_value / 100) * $gross)
                            : $gross - $discount->discount_value;
                    }

                    $gstAmount = round($net * $gstTax, 2);
                    $itemTotal = round($net + $gstAmount, 2);

                    // ---------- ACCUMULATE TOTALS ----------
                    $grossPayable += $gross;
                    $netPayable += $itemTotal;
                    $totalGst += $gstAmount;

                    // ---------- SALE VOUCHER DETAIL ----------
                    $saleOrderDetails[] = [
                        'sale_voucher_id' => $saleVoucher->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => round($price, 2),
                        'gst' => $gstAmount,
                        'total' => $itemTotal,
                        'discount_type_id' => $discount->id ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // ---------- UPDATE STOCK ----------
                    $productTransaction->update([
                        'store_quantity' => $productTransaction->store_quantity - $quantity,
                        'sold_quantity' => $productTransaction->sold_quantity + $quantity,
                    ]);

                    $product->update([
                        'extension_store_qty' => $product->extension_store_qty - $quantity,
                        'extension_store_sold_qty' => $product->extension_store_sold_qty + $quantity,
                        'updated_by' => auth()->id(),
                    ]);

                    // ---------- AUDIT ----------
                    $store = Store::where('extension_id', $extensionId)->first();
                    DB::table('transaction_audits')->insert([
                        'store_id' => $store->id,
                        'sales_type_id' => $product->sale_type_id,
                        'product_id' => $product->id,
                        'item_number' => $product->item_number,
                        'description' => $product->description,
                        'stock' => -$quantity,
                        'sales' => $quantity,
                        'created_date' => $request->invoice_date ?? now(),
                        'status' => 'sale',
                        'created_at' => now(),
                        'created_by' => auth()->id(),
                    ]);
                }
            } else {

                DB::beginTransaction();

                try {

                    // -------------------------------
                    // Product Source (Excel / Manual)
                    // -------------------------------
                    if ($request->customerType == 2 && $request->hasFile('attachment')) {

                        $request->validate([
                            'attachment' => 'required|mimes:xls,xlsx'
                        ]);

                        $file = $request->file('attachment');
                        $nestedCollection = Excel::toCollection(new SaleProduct, $file);

                        $productsData = array_values(array_filter(
                            $nestedCollection->flatten(1)->toArray(),
                            fn($row) => !empty(array_filter($row, fn($v) => !is_null($v)))
                        ));

                        array_shift($productsData); // remove header

                    } else {
                        $productsData = $request->productDetails;
                    }

                    // -------------------------------
                    // Process Products
                    // -------------------------------
                    foreach ($productsData as $item) {

                        // Quantity
                        $quantity = $request->customerType == 2
                            ? (int) $item[3]
                            : (int) $item['quantity'];

                        // -------------------------------
                        // Fetch ProductTransaction (NO JOIN)
                        // -------------------------------
                        if ($request->customerType == 2) {

                            $product = ProductTransaction::with('product')
                                ->LoggedInAssignExtension()
                                ->whereHas('product', function ($q) use ($item) {
                                    $q->where('serial_no', $item[1])
                                        ->where('description', $item[0]);
                                })
                                ->first();
                        } else {

                            $product = ProductTransaction::with('product')
                                ->where('product_id', $item['product'])
                                ->LoggedInAssignExtension()
                                ->first();
                        }

                        // -------------------------------
                        // Stock validation
                        // -------------------------------
                        if (!$product || $product->store_quantity < $quantity) {
                            $errorSerialNumbers[] = $request->customerType == 2
                                ? $item[1]
                                : $item['product'];
                            continue;
                        }

                        $productData = $product->product;

                        // -------------------------------
                        // Price
                        // -------------------------------
                        $price = $request->customerType == 2
                            ? ($item[4] ?? $productData->price)
                            : $item['product_cost'];

                        $price = round((float) $price, 2);

                        // -------------------------------
                        // Gross
                        // -------------------------------
                        $gross = $quantity * $price;

                        // -------------------------------
                        // Discount
                        // -------------------------------
                        if ($request->customerType == 2) {
                            $discountText = trim($item[2]);
                            $discount = DiscountType::where('discount_name', 'like', $discountText)->first();
                        } else {
                            $discount = !empty($item['discount_type_id'])
                                ? DiscountType::find($item['discount_type_id'])
                                : null;
                        }

                        $net = $gross;

                        if ($discount) {
                            $net = $discount->discount_type === 'Percentage'
                                ? $gross - ($gross * ($discount->discount_value / 100))
                                : $gross - $discount->discount_value;
                        }

                        // -------------------------------
                        // GST & Totals
                        // -------------------------------
                        $gstAmount = round($net * $gstTax, 2);
                        $itemTotal = round($net + $gstAmount, 2);

                        $grossPayable += $gross;
                        $netPayable   += $itemTotal;
                        $totalGst     += $gstAmount;

                        // -------------------------------
                        // Sale Order Details
                        // -------------------------------
                        $saleOrderDetails[] = [
                            'sale_voucher_id'  => $saleVoucher->id,
                            'product_id'       => $productData->id,
                            'quantity'         => $quantity,
                            'price'            => $price,
                            'gst'              => $gstAmount,
                            'total'            => $itemTotal,
                            'discount_type_id' => $discount->id ?? null,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ];

                        // -------------------------------
                        // Update ProductTransaction Stock
                        // -------------------------------
                        $product->update([
                            'store_quantity' => $product->store_quantity - $quantity,
                            'sold_quantity'  => $product->sold_quantity + $quantity,
                        ]);

                        // -------------------------------
                        // Update Product Master Stock
                        // -------------------------------
                        if ($productData->extension_store_qty < $quantity) {
                            return response()->json([
                                'success' => false,
                                'message' => "Quantity cannot exceed store quantity",
                            ], 406);
                        }


                        $productData->update([
                            'extension_store_qty'        => $productData->extension_store_qty - $quantity,
                            'extension_store_sold_qty'  => $productData->extension_store_sold_qty + $quantity,
                            'updated_by'                => auth()->id(),
                        ]);

                        // -------------------------------
                        // Audit
                        // -------------------------------
                        $store = Store::where('extension_id', $extensionId)->first();

                        DB::table('transaction_audits')->insert([
                            'store_id'      => $store->id,
                            'sales_type_id' => $productData->sale_type_id,
                            'product_id'    => $productData->id,
                            'item_number'   => $productData->item_number,
                            'description'   => $productData->description,
                            'stock'         => -$quantity,
                            'sales'         => $quantity,
                            'created_date'  => $request->invoice_date ?? now(),
                            'status'        => 'sale',
                            'created_at'    => now(),
                            'created_by'    => auth()->id(),
                        ]);
                    }

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            // ---------- HANDLE ERRORS ----------
            if (!empty($errorSerialNumbers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'These serial numbers are not found',
                    'serialNumbers' => $errorSerialNumbers
                ], 203);
            }

            // ---------- INSERT SALE VOUCHER DETAILS ----------
            if (!empty($saleOrderDetails)) {
                SaleVoucherDetail::insert($saleOrderDetails);
            }

            // ---------- ADD SERVICE CHARGE & UPDATE TOTALS ----------
            $netPayable += $serviceCharge;
            $grossPayable += $totalGst + $serviceCharge;

            $saleVoucher->update([
                'net_payable' => round($netPayable, 2),
                'gross_payable' => round($grossPayable, 2),
                'total_gst' => round($totalGst, 2),
            ]);
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
