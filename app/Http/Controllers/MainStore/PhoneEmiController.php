<?php

namespace App\Http\Controllers\MainStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CustomerEmi;
use App\Models\Product;

class PhoneEmiController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:phone-emis.view')->only('index', 'show');
        $this->middleware('permission:phone-emis.store')->only('store');
        $this->middleware('permission:phone-emis.update')->only('update');       
        $this->middleware('permission:phone-emis.emi-payments')->only('emiPayments');       
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $customerEmi = CustomerEmi::with('emiDetail')->orderBy('id')->get();         
            $products = Product::where('quantity', '!=', 0)->with('unit', 'brand', 'store', 'category', 'subCategory', 'saleType')->orderBy('id')->get();          
            
            if($products->isEmpty()){
                $products = [];
            }   
            return response([
                'message' => 'success',
                'product' => $products,
                'customerEmi' => $customerEmi,
            ], 200);
        }catch(Exception $e){
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
