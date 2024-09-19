<?php

namespace App\Http\Controllers\ReportController;

use App\Http\Controllers\Controller;
use App\Models\ProductMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class TransferController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {

        $this->middleware('permission:transfer-reports.view')->only('index');


    }
    public function index(Request $request)
    {
        try {
      
           $transfer = DB::table('product_movements as m')
                ->select(
                    'm.*',  
                    'u.name as created_by',
                    'u1.name as updated_by',                
                    'e.name as extension',
                    'r.name as region',
                    'p.serial_no',
                    'p.description'
                )       
                ->leftJoin('users as u', 'm.created_by', '=', 'u.id')
                ->leftJoin('users as u1', 'm.updated_by', '=', 'u1.id')
                ->leftJoin('regions as r', 'm.regional_id', '=', 'r.id')
                ->leftJoin('extensions as e', 'm.region_extension_id', '=', 'e.id')
                ->leftJoin('products as p', 'm.product_id', '=', 'p.id')
                ->where(function ($query) use ($request) { // Use $request in the closure 
                    $query->when('ALL' === $request->category_id, function ($subquery) {
                        $subquery->whereRaw('1 = 1');
                    }, function ($subquery) use ($request) {
                        $subquery->where('p.category_id', '=', $request->category_id);
                    });
                })
                ->where(function ($query) use ($request) { // Use $request in the closure 
                    $query->when('ALL' === $request->regional_id, function ($subquery) {
                        $subquery->whereRaw('1 = 1');
                    }, function ($subquery) use ($request) {
                        $subquery->where('r.id', '=', $request->regional_id);
                    });
                })
                ->where(function ($query) use ($request) { // Use $request in the closure 
                    $query->when('ALL' === $request->region_extension_id, function ($subquery) {
                        $subquery->whereRaw('1 = 1');
                    }, function ($subquery) use ($request) {
                        $subquery->where('e.id', '=', $request->region_extension_id);
                    });
                })
                ->whereBetween(DB::raw('DATE_FORMAT(m.movement_date, "%Y-%m-%d")'), [$request->from_date, $request->to_date])

                ->get();
            
      
            return response([
                'message' => 'success',
                'transfer' =>$transfer,

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
