<?php

namespace App\Http\Controllers\ReportController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SalesAndStockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
    
        $this->middleware('permission:reports.salesandstocks')->only('stock');
      

    }
    public function index(Request $request)
    {
        try {
           
            $stock = DB::table('products')
                ->select('item_number', 'serial_no', 'description', 'total_quantity', 'price')
                ->where(function ($query) use ($request) {
                    if ('ALL' !== $request->category_id) {
                        $query->where('products.category_id', '=', $request->category_id);
                    }
                })
              ->whereBetween(DB::raw('DATE_FORMAT(products.created_date, "%Y-%m-%d")'), [$request->from_date, $request->to_date])
                ->get();


            // if ($stock->isEmpty()) {
            //     $stock = [];
            // }
            return response([
                'message' => 'success',
                'stock' => $stock,

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
