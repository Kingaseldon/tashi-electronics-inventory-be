<?php

namespace App\Http\Controllers\ReportController;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class OnHandItemsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {

        $this->middleware('permission:onhanditems.view')->only('index');


    }
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $rolesId = $user->roles->first()->id;
            if ($rolesId == 1 || $rolesId == 2 || $rolesId == 3 || $rolesId == 4 || $rolesId == 5 || $rolesId == 9 || $rolesId == 10) {
                $onhand = DB::table('products')->leftJoin('stores', function ($join) {
                    $join->on('products.store_id', '=', 'stores.id');
                })
                    ->where(function ($query) use ($request) {
                        if ('ALL' !== $request->category_id) {
                            $query->where('products.category_id', '=', $request->category_id);
                        }
                    })->where(function ($query) use ($request) {
                        if ('ALL' !== $request->regional_id) {
                            $query->where('stores.region_id', '=', $request->regional_id);
                        }
                    })
                    ->where(function ($query) use ($request) {
                        if ('ALL' !== $request->region_extension_id) {
                            $query->where('stores.extension_id', '=', $request->region_extension_id);
                        }
                    })->whereBetween(DB::raw('DATE_FORMAT(products.created_date, "%Y-%m-%d")'), [$request->from_date, $request->to_date])
                    ->where('products.main_store_qty', '>', 0)->select([
                            'products.id',
                            'products.item_number',
                            'products.serial_no',
                            DB::raw('COALESCE(products.sub_inventory, "--") AS sub_inventory'),
                            DB::raw('COALESCE(products.locator, "--") AS locator'),
                            DB::raw('COALESCE(products.iccid, "--") AS iccid'),
                            'products.main_store_qty AS store_qty',
                            'products.description',
                        ])->union(
                        DB::table('product_transactions')->leftJoin('products', function ($join) {
                            $join->on('product_transactions.product_id', '=', 'products.id');
                        })
                            ->where(function ($query) use ($request) {
                                if ('ALL' !== $request->category_id) {
                                    $query->where('products.category_id', '=', $request->category_id);
                                }
                            })->where(function ($query) use ($request) {
                                if ('ALL' !== $request->regional_id) {
                                    $query->where('product_transactions.regional_id', '=', $request->regional_id);
                                }
                            })
                            ->where(function ($query) use ($request) {
                                if ('ALL' !== $request->region_extension_id) {
                                    $query->where('product_transactions.region_extension_id', '=', $request->region_extension_id);
                                }
                            })->whereBetween(DB::raw('DATE_FORMAT(products.created_date, "%Y-%m-%d")'), [$request->from_date, $request->to_date])
                            ->where(function ($query) {
                                $query->where('product_transactions.region_store_quantity', '>', 0);
                                $query->orWhere('product_transactions.store_quantity', '>', 0);
                            })
                            ->select([
                                'product_transactions.id',
                                'products.item_number',
                                'products.serial_no',
                                DB::raw('COALESCE(products.sub_inventory, "--") AS sub_inventory'),
                                DB::raw('COALESCE(products.locator, "--") AS locator'),
                                DB::raw('COALESCE(products.iccid, "--") AS iccid'),
                                DB::raw('CASE WHEN product_transactions.regional_id IS NOT NULL THEN product_transactions.region_store_quantity ELSE product_transactions.store_quantity END AS store_qty'),
                                'products.description',
                            ])
                            ->where(DB::raw('CASE WHEN product_transactions.regional_id IS NOT NULL THEN product_transactions.region_store_quantity ELSE product_transactions.store_quantity END'), '>', 0)
                    )
                    ->get();
            } else {
                if ($rolesId == 8 || $rolesId == 11) {
                    $onhand =
                        DB::table('product_transactions')->leftJoin('products', function ($join) {
                            $join->on('product_transactions.product_id', '=', 'products.id');
                        })
                            ->whereBetween(DB::raw('DATE_FORMAT(products.created_date, "%Y-%m-%d")'), [$request->from_date, $request->to_date])
                            ->where(function ($query) {
                                $query->where('product_transactions.region_store_quantity', '>', 0);
                                $query->orWhere('product_transactions.store_quantity', '>', 0);
                            })
                            ->select([
                                'product_transactions.id',
                                'products.item_number',
                                'products.serial_no',
                                'product_transactions.region_store_quantity',
                                'product_transactions.regional_id',
                                DB::raw('COALESCE(products.sub_inventory, "--") AS sub_inventory'),
                                DB::raw('COALESCE(products.locator, "--") AS locator'),
                                DB::raw('COALESCE(products.iccid, "--") AS iccid'),
                                DB::raw('CASE WHEN product_transactions.regional_id IS NOT NULL THEN product_transactions.region_store_quantity ELSE product_transactions.store_quantity END AS store_qty'),
                                'products.description',
                            ])
                            ->where('product_transactions.regional_id', auth()->user()->assignAndEmployee->regional_id)

                            ->where(DB::raw('CASE WHEN product_transactions.regional_id IS NOT NULL THEN product_transactions.region_store_quantity ELSE product_transactions.store_quantity END'), '>', 0)

                            ->get();
                } else {
                    $onhand =
                        DB::table('product_transactions')->leftJoin('products', function ($join) {
                            $join->on('product_transactions.product_id', '=', 'products.id');
                        })
                            ->whereBetween(DB::raw('DATE_FORMAT(products.created_date, "%Y-%m-%d")'), [$request->from_date, $request->to_date])
                            ->where(function ($query) {
                                $query->where('product_transactions.region_store_quantity', '>', 0);
                                $query->orWhere('product_transactions.store_quantity', '>', 0);
                            })
                            ->select([
                                'product_transactions.id',
                                'products.item_number',
                                'products.serial_no',
                                'product_transactions.store_quantity',
                                'product_transactions.region_extension_id',
                                DB::raw('COALESCE(products.sub_inventory, "--") AS sub_inventory'),
                                DB::raw('COALESCE(products.locator, "--") AS locator'),
                                DB::raw('COALESCE(products.iccid, "--") AS iccid'),
                                'products.description',
                            ])
                            ->where('product_transactions.region_extension_id', auth()->user()->assignAndEmployee->extension_id)

                            ->where(DB::raw('CASE WHEN product_transactions.regional_id IS NOT NULL THEN product_transactions.region_store_quantity ELSE product_transactions.store_quantity END'), '>', 0)

                            ->get();
                }
            }




            return response([
                'message' => 'success',
                'onhand' => $onhand,

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
