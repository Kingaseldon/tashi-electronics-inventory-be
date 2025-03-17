<?php

namespace App\Http\Controllers\SystemSetting;

use App\Http\Controllers\Controller;
use \OwenIt\Auditing\Models\Audit;
use Illuminate\Http\Request;

class AuditsController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:activity-logs.index')->only('index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $audits = Audit::with('user')->orderBy('created_at', 'desc')->get();

            if($audits->isEmpty()){
                $audits = [];
            }
                return response([
                    'message' => 'success',
                    'audit' =>$audits
                ],200);

        }catch(\Exception $e){
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
