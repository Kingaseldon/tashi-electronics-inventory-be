<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function __construct()
    {
        $this->middleware('permission:notifications.view')->only('index', 'show');
        $this->middleware('permission:notifications.update')->only('update');
        $this->middleware('permission:notifications.get-notifications')->only('getNotificationsforUser');


    }
    public function index()
    {
        try {
            $notifications = Notification::all();


            return response([
                'message' => 'success',
                'notification' => $notifications,

            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getNotificationsforUser($id)
    {
        try {
            $notifications = \DB::table('notifications as n')
                ->select('n.id', 'n.requisition_number', 'sub.name', 'n.quantity', 'e.name as extension_from', 'ex.name as extension_to', 'n.status', 'n.read', 'n.created_date')
                ->leftJoin('extensions as e', 'n.extension_from', '=', 'e.id')
                ->leftJoin('extensions as ex', 'n.extension_to', '=', 'ex.id')
                ->leftJoin('products as p', 'n.product_id', '=', 'p.id')
                ->leftJoin('sub_categories as sub', 'sub.id', '=', 'p.sub_category_id')
                ->where('n.user_id', $id)
                ->where('n.read', 0)
                ->get();
            
            return response([
                'message' => 'success',
                'notification' => $notifications,

            ], 200);
        } catch (Exception $e) {
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
        try {
            $notifications = Notification::where('id', $id);

            $notifications->update([
                'read' => true
            ]);
            return response([
                'message' => 'success',
                
            ], 200);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
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
