<?php

namespace App\Http\Controllers\DashboardController;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Extension;
use App\Models\PaymentHistory;
use App\Models\Product;
use App\Models\Region;
use App\Models\SaleVoucher;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\DashboardController\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:dashboards.view')->only('index');
        $this->middleware('permission:dashboards.invoices')->only('invoice');
        $this->middleware('permission:dashboards.payments')->only('payment');
        $this->middleware('permission:dashboards.sales')->only('sale');
        $this->middleware('permission:dashboards.product-list')->only('productList');
        $this->middleware('permission:dashboards.repair-list')->only('repair');
        $this->middleware('permission:dashboards.replace-list')->only('replace');

    }
    public function index()
    {
        try {
            $regions = Region::count();
            $extensions = Extension::count();
            $customers = Customer::count();
            $employees = User::count();
            $invoices = SaleVoucher::count();

            $stores = $regions + $extensions + 1;
            $statusData = [
                [
                    'icon' => 'nc-icon nc-cart-simple text-warning',
                    'title' => 'Stores',
                    'number' => $stores,
                ],
                [
                    'icon' => 'nc-icon nc-single-02 text-success',
                    'title' => 'Customers',
                    'number' => $customers,
                ],
                [
                    'icon' => 'nc-icon nc-paper-2 text-danger',
                    'title' => 'Invoices',
                    'number' => $invoices,
                ],
                [
                    'icon' => 'nc-icon nc-circle-09 text-primary',
                    'title' => 'Employees',
                    'number' => $employees - 1,
                ],
            ];
            return response([
                'message' => 'success',

                'status' => $statusData,

            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function invoice()
    {
        try {
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
            $invoice = [];
            if ($isSuperUser) {
                $invoice = SaleVoucher::with('saleVoucherDetails', 'customer','user')->orderBy('invoice_date', 'desc')->get();
            } elseif ($employee->assignAndEmployee == null) {
                $invoice = SaleVoucher::with('saleVoucherDetails', 'customer','user')->orderBy('invoice_date', 'desc')
                    ->where('regional_id', null)
                    ->where('region_extension_id', null)
                    ->get();

            } elseif ($employee->assignAndEmployee->regional_id != null) {
                $invoice = SaleVoucher::with('saleVoucherDetails', 'customer','user')->orderBy('invoice_date', 'desc')->LoggedInAssignRegion()->get();

            } else {

                $invoice = SaleVoucher::with('saleVoucherDetails', 'customer','user')->orderBy('invoice_date', 'desc')->LoggedInAssignExtension()->get();

            }

            return response([
                'message' => 'success',
                'invoices' => $invoice,


            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function payment()
    {
        try {
            $user = auth()->user();

            $roles = $user->roles;
            $employee = User::where('username', $user->username)->with('roles.permissions', 'roles', 'assignAndEmployee.region', 'assignAndEmployee.extension')->first();
            // dd($employee);
            $isSuperUser = false;

            foreach ($roles as $role) {
                if ($role->is_super_user == 1) {
                    $isSuperUser = true;
                    break;
                }
            }
            $payment = [];
            if ($isSuperUser) {

                $payment = \DB::table('payment_histories')
                    ->select('*')
                    ->leftJoin('sale_vouchers', 'payment_histories.sale_voucher_id', '=', 'sale_vouchers.id')
                    ->leftJoin('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
                    ->get();

            } elseif ($employee->assignAndEmployee == null) {

                $payment = \DB::table('payment_histories')
                    ->select('*')
                    ->leftJoin('sale_vouchers', 'payment_histories.sale_voucher_id', '=', 'sale_vouchers.id')
                    ->where('sale_vouchers.regional_id', null)
                    ->where('sale_vouchers.region_extension_id', null)
                    ->get();
            } elseif ($employee->assignAndEmployee->regional_id != null) {


                $payment = \DB::table('payment_histories')
                    ->select('*')
                    ->leftJoin('sale_vouchers', 'payment_histories.sale_voucher_id', '=', 'sale_vouchers.id')
                    ->where('sale_vouchers.regional_id', auth()->user()->assignAndEmployee->regional_id)
                    ->get();
            } else {


                $payment = \DB::table('payment_histories')
                    ->select('*')
                    ->leftJoin('sale_vouchers', 'payment_histories.sale_voucher_id', '=', 'sale_vouchers.id')
                    ->leftJoin('customers', 'sale_vouchers.customer_id', '=', 'customers.id')
                    ->where('sale_vouchers.region_extension_id', auth()->user()->assignAndEmployee->extension_id)
                    ->get();

            }

            return response([
                'message' => 'success',
                'payments' => $payment,


            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function sale(Request $request)
    {
        try {
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
            $sales = [];

            if ($isSuperUser) {
                $sales = Product::query()
                    ->whereYear('invoice_date', $request->year)
                    ->leftJoin('sale_voucher_details as svd', 'products.id', '=', 'svd.product_id')
                    ->leftJoin('sale_vouchers as sv', 'svd.sale_voucher_id', '=', 'sv.id')
                    ->leftJoin('sale_types as st', 'products.sale_type_id', '=', 'st.id')
                    ->groupBy('month', 'st.name')
                    ->selectRaw('MONTH(invoice_date) AS month, st.name, COUNT(*) AS count')
                    ->orderBy('month', 'asc')
                    ->orderBy('st.name', 'asc')
                    ->get();
            } elseif ($employee->assignAndEmployee == null) {
                $sales = Product::query()
                    ->whereYear('invoice_date', $request->year)
                    ->leftJoin('sale_voucher_details as svd', 'products.id', '=', 'svd.product_id')
                    ->leftJoin('sale_vouchers as sv', 'svd.sale_voucher_id', '=', 'sv.id')
                    ->leftJoin('sale_types as st', 'products.sale_type_id', '=', 'st.id')
                    ->groupBy('month', 'st.name')
                    ->selectRaw('MONTH(invoice_date) AS month, st.name, COUNT(*) AS count')
                    ->where('sv.regional_id', null)
                    ->where('sv.region_extension_id', null)
                    ->orderBy('month', 'asc')
                    ->orderBy('st.name', 'asc')
                    ->get();
            } elseif ($employee->assignAndEmployee->regional_id != null) {
                $sales = Product::query()
                    ->whereYear('invoice_date', $request->year)
                    ->leftJoin('sale_voucher_details as svd', 'products.id', '=', 'svd.product_id')
                    ->leftJoin('sale_vouchers as sv', 'svd.sale_voucher_id', '=', 'sv.id')
                    ->leftJoin('sale_types as st', 'products.sale_type_id', '=', 'st.id')
                    ->groupBy('month', 'st.name')
                    ->selectRaw('MONTH(invoice_date) AS month, st.name, COUNT(*) AS count')
                    ->where('sv.regional_id', auth()->user()->assignAndEmployee->regional_id)
                    ->orderBy('month', 'asc')
                    ->orderBy('st.name', 'asc')
                    ->get();
            } else {
                $sales = Product::query()
                    ->whereYear('invoice_date', $request->year)
                    ->leftJoin('sale_voucher_details as svd', 'products.id', '=', 'svd.product_id')
                    ->leftJoin('sale_vouchers as sv', 'svd.sale_voucher_id', '=', 'sv.id')
                    ->leftJoin('sale_types as st', 'products.sale_type_id', '=', 'st.id')
                    ->groupBy('month', 'st.name')
                    ->selectRaw('MONTH(invoice_date) AS month, st.name, COUNT(*) AS count')
                    ->where('sv.region_extension_id', auth()->user()->assignAndEmployee->extension_id)
                    ->orderBy('month', 'asc')
                    ->orderBy('st.name', 'asc')
                    ->get();
            }

            return response([
                'message' => 'success',
                'sales' => $sales,


            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function productList()
    {
        try {
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
            if ($isSuperUser) {
                $productTable = Product::select(
                    'sale_types.name as category',
                    'sub_categories.name as sub_category',
                    \DB::raw('CASE WHEN products.sale_type_id != 2 AND products.store_id = 1 THEN stores.store_name END AS store_name'),
                    \DB::raw('SUM(products.main_store_qty) AS total_quantity')
                )
                    ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                    ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                    ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
                    ->leftJoin('product_transactions', 'product_transactions.product_id', '=', 'products.id')
                    ->leftJoin('regions', 'regions.id', '=', 'product_transactions.regional_id')
                    ->leftJoin('extensions', 'extensions.id', '=', 'product_transactions.region_extension_id')
                    ->groupBy('sale_types.name', 'sub_categories.name', 'stores.store_name', 'products.sale_type_id', 'products.store_id')
                    ->whereNotNull(\DB::raw('CASE WHEN products.sale_type_id != 2 AND products.store_id = 1 THEN stores.store_name END'))
                    ->havingRaw('SUM(products.main_store_qty) != 0')
                    ->union(
                        Product::select(
                            'sale_types.name as category',
                            'sub_categories.name as sub_category',
                            \DB::raw("'main store' AS store_name"),
                            \DB::raw('SUM(products.main_store_qty) AS total_quantity')
                        )
                            ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                            // ->where('products.sale_type_id', '=', 2)
                            // ->where('products.store_id', '!=', 1)
                            // ->whereNotNull('products.main_store_qty')
                            ->where('products.main_store_qty', '>', 0)
                            ->groupBy('sale_types.name', 'sub_categories.name', 'store_name')
                    )
                    ->union(
                        Product::select(
                            'sale_types.name as category',
                            'sub_categories.name as sub_category',
                            'regions.name as store_name',
                            \DB::raw('SUM(product_transactions.region_store_quantity) AS total_quantity')
                        )
                            ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                            ->leftJoin('product_transactions', 'product_transactions.product_id', '=', 'products.id')
                            ->leftJoin('regions', 'regions.id', '=', 'product_transactions.regional_id')
                            // ->where('products.sale_type_id', '=', 2)
                            // ->where('products.store_id', '!=', 1)
                            ->whereNotNull('product_transactions.regional_id')
                            ->where('product_transactions.region_store_quantity', '>', 0)
                            ->groupBy('sale_types.name', 'sub_categories.name', 'store_name')
                    )
                    ->union(
                        Product::select(
                            'sale_types.name as category',
                            'sub_categories.name as sub_category',
                            'extensions.name as store_name',
                            \DB::raw('SUM(product_transactions.store_quantity) AS total_quantity')
                        )
                            ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                            ->leftJoin('product_transactions', 'product_transactions.product_id', '=', 'products.id')
                            ->leftJoin('extensions', 'extensions.id', '=', 'product_transactions.region_extension_id')
                            // ->where('products.sale_type_id', '=', 2)
                            // ->where('products.store_id', '!=', 1)
                            ->whereNotNull('product_transactions.region_extension_id')
                            ->where('product_transactions.store_quantity', '>', 0)
                            ->groupBy('sale_types.name', 'sub_categories.name', 'store_name')
                    )
                    ->get();
            } elseif ($employee->assignAndEmployee == null) {
                $productTable = Product::select(
                    'sale_types.name as category',
                    'sub_categories.name as sub_category',
                    \DB::raw('CASE WHEN products.sale_type_id != 2 AND products.store_id = 1 THEN stores.store_name END AS store_name'),
                    \DB::raw('SUM(products.main_store_qty) AS total_quantity')
                )
                    ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                    ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                    ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
                    ->leftJoin('product_transactions', 'product_transactions.product_id', '=', 'products.id')
                    ->leftJoin('regions', 'regions.id', '=', 'product_transactions.regional_id')
                    ->leftJoin('extensions', 'extensions.id', '=', 'product_transactions.region_extension_id')
                    ->groupBy('sale_types.name', 'sub_categories.name', 'stores.store_name', 'products.sale_type_id', 'products.store_id')
                    ->whereNotNull(\DB::raw('CASE WHEN products.sale_type_id != 2 AND products.store_id = 1 THEN stores.store_name END'))
                    ->union(
                        Product::select(
                            'sale_types.name as category',
                            'sub_categories.name as sub_category',
                            \DB::raw("'main store' AS store_name"),
                            \DB::raw('SUM(products.main_store_qty) AS total_quantity')
                        )
                            ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                            // ->where('products.sale_type_id', '=', 2)
                            // ->where('products.store_id', '!=', 1)
                            ->where('products.main_store_qty', '>', 0)
                            ->groupBy('sale_types.name', 'sub_categories.name', 'store_name')

                    )->get();
            } elseif ($employee->assignAndEmployee->regional_id != null) {
                $productTable = Product::select(
                    'sale_types.name as category',
                    'sub_categories.name as sub_category',
                    'regions.name as store_name',
                    \DB::raw('SUM(product_transactions.region_store_quantity) AS total_quantity')
                )
                    ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                    ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                    ->leftJoin('product_transactions', 'product_transactions.product_id', '=', 'products.id')
                    ->leftJoin('regions', 'regions.id', '=', 'product_transactions.regional_id')

                    ->whereNotNull('product_transactions.regional_id')
                    ->where('product_transactions.region_store_quantity', '>', 0)
                    ->where('product_transactions.regional_id', auth()->user()->assignAndEmployee->regional_id)
                    ->groupBy('sale_types.name', 'sub_categories.name', 'store_name')
                    ->get();
            } else {
                $productTable = Product::select(
                    'sale_types.name as category',
                    'sub_categories.name as sub_category',
                    'extensions.name as store_name',
                    \DB::raw('SUM(product_transactions.store_quantity) AS total_quantity')
                )
                    ->leftJoin('sale_types', 'sale_types.id', '=', 'products.sale_type_id')
                    ->leftJoin('sub_categories', 'sub_categories.id', '=', 'products.sub_category_id')
                    ->leftJoin('product_transactions', 'product_transactions.product_id', '=', 'products.id')
                    ->leftJoin('extensions', 'extensions.id', '=', 'product_transactions.region_extension_id')
                    ->whereNotNull('product_transactions.region_extension_id')
                    ->where('product_transactions.store_quantity', '>', 0)
                    ->where('product_transactions.region_extension_id', auth()->user()->assignAndEmployee->extension_id)
                    ->groupBy('sale_types.name', 'sub_categories.name', 'store_name')

                    ->get();
            }


            return response([
                'message' => 'success',
                'products' => $productTable,


            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function repair()
    {
        try {


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
            $repair = "";
            if ($isSuperUser) {
                $repair = \DB::table('repairs')
                    ->leftJoin('products', 'repairs.serial_no', '=', 'products.serial_no')
                    ->leftJoin('sale_types', 'repairs.sale_type_id', '=', 'sale_types.id')
                    ->leftJoin('sub_categories', 'repairs.sub_category_id', '=', 'sub_categories.id')
                    ->leftJoin('stores', 'repairs.store_id', '=', 'stores.id')
                    ->leftJoin('return_products', 'repairs.return_id', '=', 'return_products.id')
                    ->groupBy('return_products.serial_no', 'stores.store_name')
                    ->select(
                        'return_products.serial_no as product',
                        \DB::raw('GROUP_CONCAT(repairs.item_number ORDER BY repairs.item_number) as replaced_parts'),
                        \DB::raw('GROUP_CONCAT(DISTINCT sale_types.name ORDER BY sale_types.name) as sales_types'),
                        \DB::raw('GROUP_CONCAT(DISTINCT sub_categories.name ORDER BY sub_categories.name) as sub_categories'),
                        'stores.store_name',
                        \DB::raw('SUM(repairs.total_quantity) as total_quantity')
                    )
                    ->get();
            } elseif ($employee->assignAndEmployee == null) {
                $repair = \DB::table('repairs')
                    ->leftJoin('products', 'repairs.serial_no', '=', 'products.serial_no')
                    ->leftJoin('sale_types', 'repairs.sale_type_id', '=', 'sale_types.id')
                    ->leftJoin('sub_categories', 'repairs.sub_category_id', '=', 'sub_categories.id')
                    ->leftJoin('stores', 'repairs.store_id', '=', 'stores.id')
                    ->leftJoin('return_products', 'repairs.return_id', '=', 'return_products.id')
                    ->groupBy('return_products.serial_no', 'stores.store_name')
                    ->select(
                        'return_products.serial_no as product',
                        \DB::raw('GROUP_CONCAT(repairs.item_number ORDER BY repairs.item_number) as replaced_parts'),
                        \DB::raw('GROUP_CONCAT(DISTINCT sale_types.name ORDER BY sale_types.name) as sales_types'),
                        \DB::raw('GROUP_CONCAT(DISTINCT sub_categories.name ORDER BY sub_categories.name) as sub_categories'),
                        'stores.store_name',
                        \DB::raw('SUM(repairs.total_quantity) as total_quantity')
                    )
                    ->where('stores.id', '=', 1)
                    ->get();
            } elseif ($employee->assignAndEmployee->regional_id != null) {
                $repair = \DB::table('repairs')
                    ->leftJoin('products', 'repairs.serial_no', '=', 'products.serial_no')
                    ->leftJoin('sale_types', 'repairs.sale_type_id', '=', 'sale_types.id')
                    ->leftJoin('sub_categories', 'repairs.sub_category_id', '=', 'sub_categories.id')
                    ->leftJoin('stores', 'repairs.store_id', '=', 'stores.id')
                    ->leftJoin('return_products', 'repairs.return_id', '=', 'return_products.id')
                    ->groupBy('return_products.serial_no', 'stores.store_name')
                    ->select(
                        'return_products.serial_no as product',
                        \DB::raw('GROUP_CONCAT(repairs.item_number ORDER BY repairs.item_number) as replaced_parts'),
                        \DB::raw('GROUP_CONCAT(DISTINCT sale_types.name ORDER BY sale_types.name) as sales_types'),
                        \DB::raw('GROUP_CONCAT(DISTINCT sub_categories.name ORDER BY sub_categories.name) as sub_categories'),
                        'stores.store_name',
                        \DB::raw('SUM(repairs.total_quantity) as total_quantity')
                    )
                    ->where('stores.region_id', '=', auth()->user()->assignAndEmployee->regional_id)
                    ->get();
            } else {
                $repair = \DB::table('repairs')
                    ->leftJoin('products', 'repairs.serial_no', '=', 'products.serial_no')
                    ->leftJoin('sale_types', 'repairs.sale_type_id', '=', 'sale_types.id')
                    ->leftJoin('sub_categories', 'repairs.sub_category_id', '=', 'sub_categories.id')
                    ->leftJoin('stores', 'repairs.store_id', '=', 'stores.id')
                    ->leftJoin('return_products', 'repairs.return_id', '=', 'return_products.id')
                    ->groupBy('return_products.serial_no', 'stores.store_name')
                    ->select(
                        'return_products.serial_no as product',
                        \DB::raw('GROUP_CONCAT(repairs.item_number ORDER BY repairs.item_number) as replaced_parts'),
                        \DB::raw('GROUP_CONCAT(DISTINCT sale_types.name ORDER BY sale_types.name) as sales_types'),
                        \DB::raw('GROUP_CONCAT(DISTINCT sub_categories.name ORDER BY sub_categories.name) as sub_categories'),
                        'stores.store_name',
                        \DB::raw('SUM(repairs.total_quantity) as total_quantity')
                    )
                    ->where('stores.extension_id', '=', auth()->user()->assignAndEmployee->extension_id)
                    ->get();
            }

            return response([
                'message' => 'success',
                'repair' => $repair,

            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function replace()
    {
        try {
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
            $replace = "";
            if ($isSuperUser) {
                $replace = \DB::table('replaces')
                    ->leftJoin('products', 'replaces.serial_no', '=', 'products.serial_no')
                    ->leftJoin('sale_types', 'replaces.sale_type_id', '=', 'sale_types.id')
                    ->leftJoin('sub_categories', 'replaces.sub_category_id', '=', 'sub_categories.id')
                    ->leftJoin('stores', 'replaces.store_id', '=', 'stores.id')
                    ->leftJoin('return_products', 'replaces.return_id', '=', 'return_products.id')
                    ->select(
                        'replaces.serial_no as replacement',
                        'sale_types.name as sale_type',
                        'sub_categories.name as sub_category',
                        'stores.store_name',
                        'replaces.total_quantity',
                        'return_products.serial_no as replaced_item'
                    )

                    ->get();

            } elseif ($employee->assignAndEmployee == null) {
                $replace = \DB::table('replaces')
                    ->leftJoin('products', 'replaces.serial_no', '=', 'products.serial_no')
                    ->leftJoin('sale_types', 'replaces.sale_type_id', '=', 'sale_types.id')
                    ->leftJoin('sub_categories', 'replaces.sub_category_id', '=', 'sub_categories.id')
                    ->leftJoin('stores', 'replaces.store_id', '=', 'stores.id')
                    ->leftJoin('return_products', 'replaces.return_id', '=', 'return_products.id')
                    ->select(
                        'replaces.serial_no as replacement',
                        'sale_types.name as sale_type',
                        'sub_categories.name as sub_category',
                        'stores.store_name',
                        'replaces.total_quantity',
                        'return_products.serial_no as replaced_item'
                    )
                    ->where('stores.id', '=', 1)
                    ->get();

            } elseif ($employee->assignAndEmployee->regional_id != null) {
                $replace = \DB::table('replaces')
                    ->leftJoin('products', 'replaces.serial_no', '=', 'products.serial_no')
                    ->leftJoin('sale_types', 'replaces.sale_type_id', '=', 'sale_types.id')
                    ->leftJoin('sub_categories', 'replaces.sub_category_id', '=', 'sub_categories.id')
                    ->leftJoin('stores', 'replaces.store_id', '=', 'stores.id')
                    ->leftJoin('return_products', 'replaces.return_id', '=', 'return_products.id')
                    ->select(
                        'replaces.serial_no as replacement',
                        'sale_types.name as sale_type',
                        'sub_categories.name as sub_category',
                        'stores.store_name',
                        'replaces.total_quantity',
                        'return_products.serial_no as replaced_item'
                    )
                    ->where('stores.region_id', '=', auth()->user()->assignAndEmployee->regional_id)
                    ->get();
            } else {
                $replace = \DB::table('replaces')
                    ->leftJoin('products', 'replaces.serial_no', '=', 'products.serial_no')
                    ->leftJoin('sale_types', 'replaces.sale_type_id', '=', 'sale_types.id')
                    ->leftJoin('sub_categories', 'replaces.sub_category_id', '=', 'sub_categories.id')
                    ->leftJoin('stores', 'replaces.store_id', '=', 'stores.id')
                    ->leftJoin('return_products', 'replaces.return_id', '=', 'return_products.id')
                    ->select(
                        'replaces.serial_no as replacement',
                        'sale_types.name as sale_type',
                        'sub_categories.name as sub_category',
                        'stores.store_name',
                        'replaces.total_quantity',
                        'return_products.serial_no as replaced_item'
                    )
                    ->where('stores.extension_id', '=', auth()->user()->assignAndEmployee->extension_id)
                    ->get();
            }


            return response([
                'message' => 'success',
                'repair' => $replace,

            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
