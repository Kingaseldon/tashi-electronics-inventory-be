<?php

namespace App\Http\Controllers\Emi;

use App\Http\Controllers\Controller;
use App\Models\CustomerEmi;
use App\Models\Extension;
use App\Models\Product;
use App\Models\ProductTransaction;
use App\Models\Region;
use App\Models\SaleVoucher;
use App\Models\SaleVoucherDetail;
use App\Models\Store;
use App\Models\User;
use App\Services\SerialNumberGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplyEmi extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function __construct()
    {
        $this->middleware('permission:apply-emi.view')->only('index', 'show');
        $this->middleware('permission:apply-emi.update-product')->only('updateProduct');
        $this->middleware('permission:apply-emi.phone-details')->only('productDetails');
        $this->middleware('permission:apply-emi.store')->only('store');
        $this->middleware('permission:apply-emi.update')->only('update');
    }


    public function index()
    {
        require_once base_path('app/Http/constants.php');
        try {

            $user = auth()->user();
            $roles = $user->roles;
            $employee = User::where('username', $user->username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();
            $roleName = $employee->roles->first()->name;
            $isSuperUser = false;
            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            //get product-details for applying emi
            $products = Product::where('category_id', 1)->select('item_number', 'sub_category_id', 'description', 'price')
                ->groupBy('sub_category_id', 'item_number', 'description', 'price')
                ->get();

            $emi = "";

            if ($isSuperUser) {
                $emi = CustomerEmi::with('user')->join('products', 'customer_emis.item_number', '=', 'products.item_number')
                    ->select('customer_emis.*', 'products.description as product_description')
                    ->distinct()
                    ->get();;
            } else if ($employee->assignAndEmployee->extension_id == B2B_ID || $employee->assignAndEmployee->extension_id == JUNGSHINA) {
                $emi = CustomerEmi::with('user')
                    ->join('products', 'customer_emis.item_number', '=', 'products.item_number')
                    ->select('customer_emis.*', 'products.description as product_description')
                    ->distinct()
                    ->get();
            } else {
                $emi = CustomerEmi::with('user')->join('products', 'customer_emis.item_number', '=', 'products.item_number')
                    ->select('customer_emis.*', 'products.description as product_description')
                    ->where('user_id', '=', $user->id)->distinct()
                    ->get();
            }
            if ($products->isEmpty()) {
                $products = [];
            }
            return response([
                'message' => 'success',
                'product' => $products,
                'emi' => $emi

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

    //apply EMI
    public function store(Request $request)
    {
        $this->validate($request, []);

        DB::beginTransaction();

        try {
            $emi = new CustomerEmi;
            $emi->user_id = $request->user_id;
            $emi->request_date = date('Y-m-d', strtotime($request->request_date));
            $emi->emi_duration = $request->emi_duration;
            $emi->monthly_emi = $request->monthly_emi;
            $emi->total = $request->total;
            $emi->gst = $request->gst;
            $emi->deduction_from = date('Y-m-d', strtotime($request->deduction_from));
            $emi->item_number = $request->item_number;
            $emi->status = 'pending';
            $emi->description = $request->description;
            $emi->save();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'EMI applied successfully'
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

        $emi = CustomerEmi::where('id', '=', $id)->get();

        //if no user
        return response([
            'message' => 'success',
            'emi' => $emi

        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {}

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        DB::beginTransaction();
        try {
            $emi = CustomerEmi::find($id);

            if (!$emi) {
                return response()->json([
                    'message' => 'The EMI you are trying to update doesn\'t exist.'
                ], 404);
            }

            $emi->user_id = $request->user_id;
            $emi->request_date = date('Y-m-d', strtotime($request->request_date));
            $emi->emi_duration = $request->emi_duration;
            $emi->monthly_emi = $request->monthly_emi;
            $emi->gst = $request->gst;
            $emi->total = $request->total;
            $emi->deduction_from = date('Y-m-d', strtotime($request->deduction_from));
            $emi->item_number = $request->item_number;
            $emi->status = 'pending';
            $emi->description = $request->description;
            $emi->save();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'EMI has been updated Successfully'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    //fetch phones based on item number for employee emi to generate sale voucher
    public function productDetails(Request $request)
    {

        try {
            $user = auth()->user();
            $employee = User::where('username', $user->username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();


            $roles = $user->roles;

            $isSuperUser = false;

            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            $phone = Product::where('item_number', '=', $request->item_no)->get();

            if ($isSuperUser) {
                $phone = Product::where('item_number', '=', $request->item_no)->get();
            }

            if ($employee->assignAndEmployee == null) {
                $phone = Product::where('item_number', '=', $request->item_no)->where('main_store_qty', '!=', 0)->get();
            } elseif ($employee->assignAndEmployee['regional_id'] != null) {

                $phone = Product::where('item_number', '=', $request->item_no)->where('region_store_qty', '!=', 0)->get();
            } else {

                $phone = Product::join('product_transactions', 'products.id', '=', 'product_transactions.product_id')
                    ->where('product_transactions.region_extension_id', $employee->assignAndEmployee->extension->id)
                    ->where('products.item_number', '=', $request->item_no)
                    ->where('product_transactions.store_quantity', '!=', 0)
                    ->select('products.*')
                    ->get();
            }



            return response([
                'message' => 'success',
                'product' => $phone,


            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    //generate sale voucher for emi
    public function updateProduct(Request $request, $id, SerialNumberGenerator $invoice)
    {

        DB::beginTransaction();
        try {
            require_once base_path('app/Http/constants.php');


            $user = auth()->user();
            $employee = User::where('username', $user->username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();
            $emi = CustomerEmi::find($id);


            if (!$emi) {
                return response()->json([
                    'message' => 'The EMI you are trying to update doesn\'t exist.'
                ], 404);
            }

            $emi->product_id = $request->product;
            $emi->status = 'sold';
            $emi->save();

            $invoiceNo = "";

            $extension = $employee->assignAndEmployee->extension_id;
            if ($extension == B2B_ID ||  $extension == JUNGSHINA) {
                $extension = $employee->assignAndEmployee->extension_id;
                $extensionName = Extension::where('id', '=', $extension)->first();
                $wordArray = explode(' ', $extensionName->name);
                // The first element of the $wordArray will contain the first word
                $firstWord = $wordArray[0];
                $extensionID = $extensionName->id;
                $invoiceNo = $invoice->extensionInvoiceNumber('SaleVoucher', 'invoice_date', $extensionID, $firstWord);
                $store = Store::where('extension_id', '=', $extension)->first();
                // $storeID = $store->id;
            }

            //generate sale voucher
            $saleVoucher = new SaleVoucher;
            $saleVoucher->invoice_no = $invoiceNo;
            $saleVoucher->invoice_date = date('Y-m-d', strtotime(Carbon::now()));
            $saleVoucher->user_id = $emi->user_id;
            $saleVoucher->gross_payable = $request->gross_payable;
            $saleVoucher->net_payable = $request->net_payable;
            $saleVoucher->total_gst = $request->total_gst;
            $saleVoucher->region_extension_id = $extensionID;
            $saleVoucher->status = "open";
            $saleVoucher->remarks = 'staff emi';
            $saleVoucher->save();

            $saleVoucher->saleVoucherDetails()->insert([
                'sale_voucher_id' => $saleVoucher->id,
                'product_id' => $request->product,
                'quantity' => 1,
                'price' => $request->gross_payable,
                'total' => $request->net_payable,
                'gst' => $request->gst,
                'discount_type_id' => null,
            ]);


            if ($extensionID == B2B_ID || $extensionID == JUNGSHINA) {
                $extensionTransfer = ProductTransaction::where('product_id', $request->product)->where('region_extension_id', $extension)->first();
                $storequantity = $extensionTransfer->store_quantity;
                $soldquantity = $extensionTransfer->sold_quantity;
                $extensionTransfer->update([
                    'store_quantity' => $storequantity - 1,
                    'sold_quantity' => $soldquantity + 1,

                    // 'region_store_quantity' => 0,
                ]);
                $product_table = Product::where('id', $extensionTransfer->product_id)->first();
                $product_table->update([
                    'extension_store_qty' => $storequantity - 1,
                    'extension_store_sold_qty' => $soldquantity + 1,
                    'updated_by' => auth()->user()->id,
                ]);


                DB::table('transaction_audits')->insert([
                    'store_id' => $store->id,
                    'sales_type_id' =>   $product_table->sale_type_id, // Corrected variable name
                    'product_id' =>    $product_table->id,
                    'item_number' =>   $product_table->item_number,
                    'description' =>    $product_table->description,
                    'stock' =>  -$storequantity,
                    'sales' =>   $storequantity,
                    'created_date' => $request->invoice_date ?? now(),
                    'status' => 'sale',
                    'created_at' => now(),
                    'created_by' => auth()->user()->id,
                ]);
            }
            //save sale voucher id to emi table after genertaion
            $emi->sale_voucher_id = $saleVoucher->id;
            $emi->save();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'EMI has been updated Successfully',
            'invoice' => $saleVoucher->invoice_no
        ], 200);
    }
    public function destroy($id)
    {
        //
    }
}
