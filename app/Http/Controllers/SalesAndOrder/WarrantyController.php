<?php

namespace App\Http\Controllers\SalesAndOrder;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Extension;
use App\Models\Product;
use App\Models\ProductTransaction;
use App\Models\Region;
use App\Models\Repair;
use App\Models\Replace;
use App\Models\ReturnProduct;
use App\Models\SaleVoucher;
use App\Models\SaleVoucherDetail;
use App\Models\Store;
use App\Models\Warranty;
use App\Services\SerialNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WarrantyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:warranties.view')->only('index', 'show');
        $this->middleware('permission:warranties.replace')->only('Replace');
        $this->middleware('permission:warranties.repair')->only('Repair');
        $this->middleware('permission:warranties.search-warranties')->only('searchForWarranty');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            //for printing warranty in invoice
            $warranties = Warranty::all();
            return response([
                'message' => 'success',
                'products' => $warranties
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
    public function store(Request $request) {}
    
    public function Replace(Request $request, SerialNumberGenerator $invoice)
    {

        $oldDetails = $request->input('oldDetails');
        $newDetails = $request->input('newDetails');
        $region = $request->input('region');
        $extension = $request->input('extension');
        $customerType = $request->input('customerType');
        $customerName = $request->input('customerName');
        $customerNo = $request->input('customerNo');


        DB::beginTransaction();

        try {
            $productID = Product::where('serial_no', '=', $newDetails['serialNumber'])->first();

            if ($productID == null) {
                return response()->json([
                    'message' => 'Serial number not found',
                ], 203);
            }

            //insert in returnproducts table
            $return = new ReturnProduct;
            $return->item_number = $oldDetails['item_number'];
            $return->category_id = $oldDetails['sale_type_id'];
            $return->sale_type_id = $oldDetails['sale_type_id'];
            $return->sub_category_id = $oldDetails['sub_category_id'];
            $return->serial_no = $oldDetails['serial_no'];
            $return->color_id = $oldDetails['color_id'];
            $return->total_quantity = 1;
            $return->price = $oldDetails['price'];
            $return->created_date = date('Y-m-d', strtotime(Carbon::now()));
            $return->description = $oldDetails['product_description'];
            $return->sub_inventory = $oldDetails['sub_inventory'];
            $return->locator = $oldDetails['locator'];
            $return->iccid = $oldDetails['iccid'];
            $return->status = 'return';
            $return->sale_status = $oldDetails['sale_status'];
            $return->invoice_number = $oldDetails['invoice_no'];
            $return->save();


            //salevoucher
            $regionName = Region::where('id', '=', $region)->first();
            $extensionName = Extension::where('id', '=', $extension)->first();
            $storeID = "";



            //unique number generator
            $invoiceNo = "";
            if ($region === null && $extension === null) {
                $invoiceNo = $invoice->mainInvoiceNumber('SaleVoucher', 'invoice_date');
                $storeID = 1;
            } elseif ($region != null) {
                $wordArray = explode(' ', $regionName->name);
                // The first element of the $wordArray will contain the first word
                $firstWord = $wordArray[0];
                $regionID = $regionName->id;
                $invoiceNo = $invoice->invoiceNumber('SaleVoucher', 'invoice_date', $regionID, $regionName->name);
                $store = Store::where('region_id', '=', $region)->first();
                $storeID = $store->id;
            } else {
                $wordArray = explode(' ', $extensionName->name);
                // The first element of the $wordArray will contain the first word
                $firstWord = $wordArray[0];
                $extensionID = $extensionName->id;
                $invoiceNo = $invoice->extensionInvoiceNumber('SaleVoucher', 'invoice_date', $extensionID, $extensionName->name);
                $store = Store::where('extension_id', '=', $extension)->first();
                $storeID = $store->id;
            }
            $customer = "";
            if ($customerType == 2) {
                $customer = Customer::where('customer_name', '=', $customerName)->where('contact_no', '=', $customerNo)->first();
            }

            $saleVoucher = new SaleVoucher;
            $saleVoucher->invoice_no = $invoiceNo;
            $saleVoucher->invoice_date = date('Y-m-d', strtotime(Carbon::now()));
            $saleVoucher->customer_id = $customerType == 2 ? $customer->id : null;
            $saleVoucher->sale_type = $newDetails['category'];
            $saleVoucher->regional_id = $region;
            $saleVoucher->region_extension_id = $extension;
            $saleVoucher->walk_in_customer = $customerType == 2 ? null : $customerName;
            $saleVoucher->contact_no = $customerType == 2 ? null : $customerNo;
            $saleVoucher->gross_payable = 0;
            $saleVoucher->net_payable = 0;
            $saleVoucher->status = "closed";
            $saleVoucher->remarks = $newDetails['description'];
            $saleVoucher->save();
            $saleOrderDetails = "";


            $saleOrderDetails = [
                'sale_voucher_id' => $saleVoucher->id,
                'product_id' => $productID->id,
                'quantity' => 1,
                'price' => 0,
                'total' => 0,
                'discount_type_id' => null,
            ];

            // Insert sale order details
            $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);
            //replaces table
            $replace = new Replace;
            $replace->item_number = $newDetails['itemNumber'];
            $replace->category_id = $newDetails['category'];
            $replace->return_id = $return->id;
            $replace->sale_voucher_id = $saleVoucher->id;
            $replace->store_id = $storeID;
            $replace->sale_type_id = $newDetails['category'];
            $replace->sub_category_id = $newDetails['subCategory'];
            $replace->serial_no = $newDetails['serialNumber'];
            $replace->total_quantity = $newDetails['qty'];
            $replace->price = $newDetails['price'];
            $replace->created_date = date('Y-m-d', strtotime(Carbon::now()));
            $replace->description = $newDetails['description'];
            $replace->sub_inventory = $newDetails['subInventory'];
            $replace->locator = $newDetails['locator'];
            $replace->iccid = $newDetails['iccid'];
            $replace->status = "replaced";
            $replace->save();

            $productTable = Product::where('serial_no', '=', $newDetails['serialNumber'])->first();

            if ($region === null && $extension === null) {
                $mainStoreQty = $productTable->main_store_qty;
                $mainStoreSoldQty = $productTable->main_store_sold_qty;

                $productTable->update([
                    'main_store_qty' => $mainStoreQty - $newDetails['qty'],
                    'main_store_sold_qty' => $mainStoreSoldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
                ]);
            } elseif ($region != null) {
                $Transaction = ProductTransaction::where('product_id', '=', $productTable->id)->loggedInAssignRegion()->first();
                if ($Transaction == null) {
                    return response()->json([
                        'message' => 'Failed to locate this product: ' . $newDetails['serialNumber'],
                    ], 203);
                }
                $regionStoreQty = $productTable->region_store_qty;
                $regionStoreSoldQty = $productTable->region_store_sold_qty;
                $productTable->update([
                    'region_store_qty' => $regionStoreQty - $newDetails['qty'],
                    'region_store_sold_qty' => $regionStoreSoldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
                ]);
                $regionQty = $Transaction->region_store_quantity;
                $soldQty = $Transaction->sold_quantity;

                $Transaction->update([
                    'region_store_quantity' => $regionQty - $newDetails['qty'],
                    'sold_quantity' => $soldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
                ]);
            } else {
                $Transaction = ProductTransaction::where('product_id', '=', $productTable->id)->LoggedInAssignExtension()->first();

                if ($Transaction == null) {
                    return response()->json([
                        'message' => 'Failed to locate this product: ' . $newDetails['serialNumber'],
                    ], 203);
                }

                $extensionStoreQty = $productTable->extension_store_qty;
                $extensionStoreSoldQty = $productTable->extension_store_sold_qty;
                $productTable->update([
                    'extension_store_qty' => $extensionStoreQty - $newDetails['qty'],
                    'extension_store_sold_qty' => $extensionStoreSoldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
                ]);
                $Qty = $Transaction->store_quantity;
                $soldQty = $Transaction->sold_quantity;

                $Transaction->update([
                    'store_quantity' => $Qty - $newDetails['qty'],
                    'sold_quantity' => $soldQty + $newDetails['qty'],
                    'sale_status' => "replaced"
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
            'message' => 'Product has been replaced Successfully',
            'invoice_no' => $invoiceNo,
        ], 200);
    }

    public function Repair(Request $request, SerialNumberGenerator $invoice)
    {

        $oldDetails = $request->input('oldDetails');
        $newDetails = $request->input('newDetails');
        $region = $request->input('region');
        $extension = $request->input('extension');
        $customerType = $request->input('customerType');
        $customerName = $request->input('customerName');
        $customerNo = $request->input('customerNo');
        $description = $request->input('description');


        DB::beginTransaction();
        try {

            //insert in return products table
            $return = new ReturnProduct;
            $return->item_number = $oldDetails['item_number'];
            $return->category_id = $oldDetails['sale_type_id'];
            $return->sale_type_id = $oldDetails['sale_type_id'];
            $return->sub_category_id = $oldDetails['sub_category_id'];
            $return->serial_no = $oldDetails['serial_no'];
            $return->color_id = $oldDetails['color_id'];
            $return->total_quantity = 1;
            $return->price = $oldDetails['price'];
            $return->created_date = date('Y-m-d', strtotime(Carbon::now()));
            $return->description = $oldDetails['product_description'];
            $return->sub_inventory = $oldDetails['sub_inventory'];
            $return->locator = $oldDetails['locator'];
            $return->iccid = $oldDetails['iccid'];
            $return->status = 'repair';
            $return->sale_status = $oldDetails['sale_status'];
            $return->invoice_number = $oldDetails['invoice_no'];
            $return->save();


            //salevoucher
            $regionName = Region::where('id', '=', $region)->first();
            $extensionName = Extension::where('id', '=', $extension)->first();


            //unique number generator
            $invoiceNo = "";
            $storeID = "";
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
            $customer = "";
            if ($customerType == 2) {
                $customer = Customer::where('customer_name', '=', $customerName)->where('contact_no', '=', $customerNo)->first();
            }


            $saleVoucher = new SaleVoucher;
            $saleVoucher->invoice_no = $invoiceNo;
            $saleVoucher->invoice_date = date('Y-m-d', strtotime(Carbon::now()));
            $saleVoucher->customer_id = $customerType == 2 ? $customer->id : null;
            $saleVoucher->sale_type = $oldDetails['sale_type_id'];
            $saleVoucher->regional_id = $region;
            $saleVoucher->region_extension_id = $extension;
            $saleVoucher->walk_in_customer = $customerType == 2 ? null : $customerName;
            $saleVoucher->contact_no = $customerType == 2 ? null : $customerNo;
            $saleVoucher->gross_payable = 0;
            $saleVoucher->net_payable = 0;
            $saleVoucher->status = "closed";
            $saleVoucher->remarks = $description;
            $saleVoucher->save();

            // $saleOrderDetails = [];
            // $jsonData = $newDetails->json()->all();

            foreach ($newDetails as $value) {
                // dd('insode');
                $product = Product::where('serial_no', '=', $value['serial'])->first();

                if ($product == null) {
                    return response()->json([
                        'message' => 'Product not found for this ' . $value['serial'],
                    ], 203);
                }

                $saleOrderDetails = "";

                $saleOrderDetails = [
                    'sale_voucher_id' => $saleVoucher->id,
                    'product_id' => $product->id,
                    'quantity' => $value['quantity'],
                    'price' => 0,
                    'total' => 0,
                    'discount_type_id' => null,
                ];

                // Insert sale order details
                $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);

                //repair
                $repair = new Repair;
                $repair->item_number = $value['itemNo'];
                $repair->category_id = $value['category'];
                $repair->return_id = $return->id;
                $repair->sale_voucher_id = $saleVoucher->id;
                $repair->sale_type_id = $value['category'];
                $repair->store_id = $storeID;
                $repair->sub_category_id = $value['subCategory'];
                $repair->serial_no = $value['serial'];
                $repair->total_quantity = $value['quantity'];
                $repair->price = $value['price'];
                $repair->created_date = date('Y-m-d', strtotime(Carbon::now()));
                $repair->description = $description;
                $repair->sub_inventory = isset($value['sub_inventory']) ? $value['sub_inventory'] : null;
                $repair->locator = isset($value['locator']) ? $value['locator'] : null;
                $repair->iccid = isset($value['iccid']) ? $value['iccid'] : null;
                $repair->status = "repaired";
                $repair->save();

                $productTable = Product::where('serial_no', '=', $value['serial'])->first();
                // $Transaction = ProductTransaction::where('product_id', '=', $productTable->id)->first();

                if ($region === null && $extension === null) {

                    $mainStoreQty = $productTable->main_store_qty;
                    $mainStoreSoldQty = $productTable->main_store_sold_qty;

                    $productTable->update([
                        'main_store_qty' => $mainStoreQty - $value['quantity'],
                        'main_store_sold_qty' => $mainStoreSoldQty + $value['quantity'],
                        'sale_status' => "replaced"
                    ]);
                } elseif ($region != null) {
                    $Transaction = ProductTransaction::where('product_id', '=', $productTable->id)->loggedInAssignRegion()->first();

                    if ($Transaction == null) {
                        return response()->json([
                            'message' => 'Failed to locate this product: ' . $value['serial'],
                        ], 203);
                    }
                    $regionStoreQty = $productTable->region_store_qty;
                    $regionStoreSoldQty = $productTable->region_store_sold_qty;
                    $productTable->update([
                        'region_store_qty' => $regionStoreQty - $value['quantity'],
                        'region_store_sold_qty' => $regionStoreSoldQty + $value['quantity'],
                        'sale_status' => "replaced"
                    ]);
                    $regionQty = $Transaction->region_store_quantity;
                    $soldQty = $Transaction->sold_quantity;

                    $Transaction->update([
                        'region_store_quantity' => $regionQty - $value['quantity'],
                        'sold_quantity' => $soldQty + $value['quantity'],
                        'sale_status' => "replaced"
                    ]);
                } else {
                    $Transaction = ProductTransaction::where('product_id', '=', $productTable->id)->LoggedInAssignExtension()->first();
                    if ($Transaction == null) {

                        return response()->json([
                            'message' => 'Failed to locate this product: ' . $value['serial'],
                        ], 203);
                    }

                    $extensionStoreQty = $productTable->extension_store_qty;
                    $extensionStoreSoldQty = $productTable->extension_store_sold_qty;
                    $productTable->update([
                        'extension_store_qty' => $extensionStoreQty - $value['quantity'],
                        'extension_store_sold_qty' => $extensionStoreSoldQty + $value['quantity'],
                        'sale_status' => "replaced"
                    ]);
                    $Qty = $Transaction->store_quantity;
                    $soldQty = $Transaction->sold_quantity;

                    $Transaction->update([
                        'store_quantity' => $Qty - $value['quantity'],
                        'sold_quantity' => $soldQty + $value['quantity'],
                        'sale_status' => "replaced"
                    ]);
                }
            }
            // $saleVoucher->saleVoucherDetails()->insert($saleOrderDetails);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();

        return response()->json([
            'message' => 'Product has been replaced Successfully',
            'invoice_no' => $invoiceNo,
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    //search products based on serial number
    public function searchForWarranty(Request $request)
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
                    'p.main_store_sold_qty',
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
                ->leftJoin('customer_types as ct', 'cus.customer_type_id', '=', 'ct.id')
                ->where('p.serial_no', $request->serial_no)
                ->where(function ($query) {
                    $query->where('p.main_store_sold_qty', '>', 0)
                        ->orWhere('pt.sold_quantity', '>', 0);
                })
                ->where(function ($query) use ($request) {
                    $query->where(function ($innerQuery) use ($request) {
                        $innerQuery->where('ct.id', $request->customer_type)
                            ->where('cus.customer_name', 'LIKE', '%' . $request->customer_name . '%');
                    })
                        ->orWhere(function ($innerQuery) use ($request) {
                            $innerQuery->where('sv.walk_in_customer', 'LIKE', '%' . $request->customer_name . '%');
                        });
                })
                ->where(function ($query) use ($request) {
                    $query->where(function ($innerQuery) use ($request) {
                        $innerQuery->where('ct.id', $request->customer_type)
                            ->where('cus.contact_no', $request->customer_number);
                    })
                        ->orWhere(function ($innerQuery) use ($request) {
                            $innerQuery->where('sv.contact_no', $request->customer_number);
                        });
                })
                ->where('p.sale_type_id', $request->product_type)
                ->orderBy('sv.created_at', 'desc')
                ->get();

            // You can use the $results as needed.
            if (!$product) {
                return response()->json([
                    'message' => 'The Serial no. you are trying to find doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'products' => $product
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
