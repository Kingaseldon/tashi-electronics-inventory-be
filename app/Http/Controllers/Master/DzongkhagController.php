<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Dzongkhag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DzongkhagController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:dzongkhags.view')->only('index', 'show');
        $this->middleware('permission:dzongkhags.store')->only('store');
        $this->middleware('permission:dzongkhags.update')->only('update');
        $this->middleware('permission:dzongkhags.edit-dzongkhags')->only('editDzongkhag');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $dzongkhags = Dzongkhag::with('gewogs:id,dzongkhag_id,name,code', 'gewogs.villages:gewog_id,id,name,code')->orderBy('name')->get(['id', 'name', 'code']);
            if ($dzongkhags->isEmpty()) {
                $dzongkhags = [];
            }
            return response([
                'message' => 'success',
                'dzongkhag' => $dzongkhags
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $dzongkhag = new Dzongkhag;
            $dzongkhag->name = $request->name;
            $dzongkhag->code = $request->code;
            $dzongkhag->save();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Dzongkhag has been created Successfully'
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
    public function editDzongkhag($id)
    {
        try {
            $dzongkhag = Dzongkhag::with('gewogs:id,dzongkhag_id,name,code', 'gewogs.villages:gewog_id,id,name,code')->orderBy('name')->get(['id', 'name', 'code'])->find($id);

            if (!$dzongkhag) {
                return response()->json([
                    'message' => 'The Dzongkhag you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'dzongkhag' => $dzongkhag
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
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
        $this->validate($request, [
            'name' => 'required',
        ]);
        DB::beginTransaction();
        try {
            $dzongkhag = Dzongkhag::find($id);

            if (!$dzongkhag) {
                return response()->json([
                    'message' => 'The Dzongkhag you are trying to update doesn\'t exist.'
                ], 404);
            }

            $dzongkhag->name = $request->name;
            $dzongkhag->code = $request->code;
            $dzongkhag->save();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Dzongkhag has been updated Successfully'
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
        try {

            Dzongkhag::find($id)->delete();

            return response()->json([
                'message' => 'Dzongkhag deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Dzongkhag cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}
