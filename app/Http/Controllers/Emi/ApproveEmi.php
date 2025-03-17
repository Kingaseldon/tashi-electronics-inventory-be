<?php

namespace App\Http\Controllers\Emi;

use App\Http\Controllers\Controller;
use App\Models\CustomerEmi;
use App\Models\Product;
use App\Services\SerialNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApproveEmi extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('permission:approve-emi.view')->only('index', 'show');
        $this->middleware('permission:approve-emi.store')->only('store');
        $this->middleware('permission:approve-emi.update')->only('update');
        $this->middleware('permission:approve-emi.edit-emi')->only('getEmi');
    }
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
            $emi = CustomerEmi::with('user')->get();
            return response([
                'message' => 'success',
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
    public function update(Request $request, $id, SerialNumberGenerator $serial)
    {
        $this->validate($request, []);

        DB::beginTransaction();
        try {
            $emi = CustomerEmi::find($id);
            //unique number generator

            $emi->emi_no = 'EMI-' . $emi->id;
            $emi->status = $request->status;
            $emi->save();


            if (!$emi) {
                return response()->json([
                    'message' => 'The EMI request you are trying to approve doesn\'t exist.'
                ], 404);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'EMI Verified'
        ], 200);
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
