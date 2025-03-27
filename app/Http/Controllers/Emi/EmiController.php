<?php

namespace App\Http\Controllers\Emi;

use App\Http\Controllers\Controller;
use App\Models\CustomerEmi;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Imports\SaleProduct;
use App\Models\DiscountType;
use App\Models\Extension;
use App\Models\ProductTransaction;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;
use App\Services\SerialNumberGenerator;
use App\Models\SaleVoucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class EmiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('permission:emi.view')->only('index', 'show');
        $this->middleware('permission:emi.store')->only('store');
        $this->middleware('permission:emi.update')->only('update');
        $this->middleware('permission:emi.emi-payments')->only('emiPayments');
    }
    public function index()
    {
        try {

            // $customerEmi = CustomerEmi::with('emiDetail')->orderBy('id')->get();
            $products = Product::where('category_id', 1)->select('item_number', 'sub_category_id', 'description', 'price')
                ->groupBy('sub_category_id', 'item_number', 'description', 'price')
                ->get();
            if ($products->isEmpty()) {
                $products = [];
            }
            return response([
                'message' => 'success',
                'product' => $products,
                // 'customerEmi' => $customerEmi,
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
    public function store(Request $request, SerialNumberGenerator $invoice)
    {

        DB::beginTransaction();

        //unique number generator
        try {

            $invoiceNo = "";
            $region = $request->input('region');
            $extension = $request->input('extension');
            $regionName = Region::where('id', '=', $region)->first();
            $extensionName = Extension::where('id', '=', $extension)->first();

            if ($region === null && $extension === null) {
                $invoiceNo = $invoice->mainInvoiceNumber('SaleVoucher', 'invoice_date');
                $storeID = 1;
            } elseif ($region != null) {
                $wordArray = explode(' ', $regionName->name);
                // The first element of the $wordArray will contain the first word
                $firstWord = $wordArray[0];
                $regionID = $regionName->id;
                $invoiceNo = $invoice->invoiceNumber('SaleVoucher', 'invoice_date', $regionID, $firstWord);
                $store = Store::where('region_id', '=', $region)->first();
                $storeID = $store->id;
            } else {
                $wordArray = explode(' ', $extensionName->name);
                // The first element of the $wordArray will contain the first word
                $firstWord = $wordArray[0];
                $extensionID = $extensionName->id;
                $invoiceNo = $invoice->extensionInvoiceNumber('SaleVoucher', 'invoice_date', $extensionID, $firstWord);
                $store = Store::where('extension_id', '=', $extension)->first();
                $storeID = $store->id;
            }

            $user = auth()->user();

            $roles = $user->roles;
            $employee = User::where('username', $user->username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();

            $isSuperUser = false;

            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }

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

                            // gross payable
                            $saleOrderDetails[$i]['price'] = $grossForEachItem;
                            $grossPayable += $grossForEachItem; // Accumulate the total in the grand total


                            $saleOrderDetails[$i]['discount_type_id'] = $discountName->id ?? null;

                            $quantityafterDistribute = $product->total_quantity;
                            if ($employee->assignAndEmployee == null) {
                                $product->update([
                                    'main_store_qty' => $quantityafterDistribute - $flattenedArray[$i][2],
                                    'main_store_sold_qty' => $flattenedArray[$i][2],
                                ]);
                            } elseif ($employee->assignAndEmployee->regional_id != null) {
                                $regionTransfer = ProductTransaction::where('product_id', $product->id)->first();
                                $storequantity = $regionTransfer->store_quantity;
                                $soldquantity = $regionTransfer->sold_quantity;
                                $regionStoreQuantity = $regionTransfer->region_store_quantity;

                                $regionTransfer->update([
                                    // 'store_quantity' => $storequantity - $flattenedArray[$i][2],
                                    'sold_quantity' => $soldquantity + $flattenedArray[$i][2],
                                    'region_store_quantity' => $regionStoreQuantity - $flattenedArray[$i][2],
                                ]);

                                $product_table = Product::where('id', $product->product_id)->first();
                                $product_table->update([
                                    'region_store_qty' => $storequantity - $flattenedArray[$i][2],
                                    'region_store_sold_qty' => $regionStoreQuantity - $flattenedArray[$i][2],
                                    'updated_by' => auth()->user()->id,
                                ]);
                            } else {
                                $extensionTransfer = ProductTransaction::where('product_id', $product->id)->where('region_extension_id', $extension)->first();
                                $storequantity = $extensionTransfer->store_quantity;
                                $soldquantity = $extensionTransfer->sold_quantity;

                                $extensionTransfer->update([
                                    'store_quantity' => $storequantity - $flattenedArray[$i][2],
                                    'sold_quantity' => $soldquantity + $flattenedArray[$i][2],
                                    // 'region_store_quantity' => 0,
                                ]);
                                $product_table = Product::where('id', $extensionTransfer->product_id)->first();
                                $product_table->update([
                                    'extension_store_qty' => $storequantity - $flattenedArray[$i][2],
                                    'extension_store_sold_qty' => $soldquantity + $flattenedArray[$i][2],
                                    'updated_by' => auth()->user()->id,
                                ]);
                            }
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
                    if ($employee->assignAndEmployee == null) {
                        $mainTransfer->update([
                            'main_store_qty' => $quantityafterDistribute - $value['quantity'],
                            'main_store_sold_qty' => $value['quantity'],
                        ]);
                    } elseif ($employee->assignAndEmployee->regional_id != null) {
                        $regionTransfer = ProductTransaction::where('product_id', $value['product'])->first();
                        $storequantity = $regionTransfer->store_quantity;
                        $soldquantity = $regionTransfer->sold_quantity;
                        $regionStoreQuantity = $regionTransfer->region_store_quantity;

                        $regionTransfer->update([
                            // 'store_quantity' => $storequantity - $value['quantity'],
                            'region_store_quantity' => $regionStoreQuantity - $value['quantity'],
                            'sold_quantity' => $soldquantity + $value['quantity'],
                        ]);
                        $product_table = Product::where('id', $regionTransfer->product_id)->first();
                        $product_table->update([
                            'region_store_qty' => $regionStoreQuantity - $value['quantity'],
                            'region_store_sold_qty' => $soldquantity + $value['quantity'],
                            'updated_by' => auth()->user()->id,
                        ]);
                    } else {
                        $extensionTransfer = ProductTransaction::where('product_id', $value['product'])->where('region_extension_id', $extension)->first();
                        $storequantity = $extensionTransfer->store_quantity;
                        $soldquantity = $extensionTransfer->sold_quantity;

                        $extensionTransfer->update([
                            'store_quantity' => $storequantity - $value['quantity'],
                            'sold_quantity' => $soldquantity + $value['quantity'],
                            // 'region_store_quantity' => 0,
                        ]);
                        $product_table = Product::where('id', $extensionTransfer->product_id)->first();
                        $product_table->update([
                            'extension_store_qty' => $storequantity - $value['quantity'],
                            'extension_store_sold_qty' => $soldquantity + $value['quantity'],
                            'updated_by' => auth()->user()->id,
                        ]);
                    }
                    // $saleOrderDetails[$key]['created_by'] = $request->user()->id;
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
            'message' => 'Sale Voucher for EMI created Successfully'
        ], 200);
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
