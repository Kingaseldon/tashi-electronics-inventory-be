<?php

namespace App\Http\Controllers\DealerManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CustomerType;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:customers.view')->only('index', 'show');
        $this->middleware('permission:customers.store')->only('store');
        $this->middleware('permission:customers.update')->only('update');
        $this->middleware('permission:customers.edit-customers')->only('editCustomer');
        $this->middleware('permission:customers.get-customers')->only('getCustomers');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $customers = Customer::with('customerType')->orderBy('id')->get();
            $customerTypes = CustomerType::orderBy('id')->get();
            if ($customers->isEmpty()) {
                $customers = [];
            }
            return response([
                'message' => 'success',
                'customer' => $customers,
                'customerType' => $customerTypes,
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
        $this->validate($request, [
            'customer_name' => 'required',
        ]);
        // dd($request->all());
        DB::beginTransaction();

        try {
            $customer = new Customer;
            $customer->customer_name = $request->customer_name;
            $customer->contact_no = $request->contact_no;
            $customer->address = $request->address;
            $customer->license_no = $request->license_no;
            $customer->tpn_number = $request->tpn_number;
            $customer->gst_number = $request->gst_number;
            $customer->customer_type_id = $request->customer_type;
            $customer->description = $request->description;
            $customer->save();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Customer has been created Successfully'
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
    public function editCustomer($id)
    {
        try {
            $customer = Customer::with('customerType')->find($id);
            $customerTypes = CustomerType::orderBy('id')->get();

            if (!$customer) {
                return response()->json([
                    'message' => 'The Customer you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'customer' => $customer,
                'customerType' => $customerTypes,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    //get customer based on customer type id
    public function getCustomers($id)
    {
        try {

            $customer = Customer::orderBy('id')->where('customer_type_id', $id)->get();

            if (!$customer) {
                return response()->json([
                    'message' => 'The CustomerType you are trying to update doesn\'t exist.'
                ], 404);
            }
            return response([
                'message' => 'success',
                'customer' => $customer,

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
            'customer_name' => 'required',
        ]);
        DB::beginTransaction();
        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'message' => 'The Customer you are trying to update doesn\'t exist.'
                ], 404);
            }

            $customer->customer_name = $request->customer_name;
            $customer->contact_no = $request->contact_no;
            $customer->address = $request->address;
            $customer->license_no = $request->license_no;
            $customer->tpn_number = $request->tpn_number;
            $customer->gst_number = $request->gst_number;
            $customer->customer_type_id = $request->customer_type;
            $customer->description = $request->description;
            $customer->save();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        DB::commit();
        return response()->json([
            'message' => 'Customer has been updated Successfully'
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

            Customer::find($id)->delete();

            return response()->json([
                'message' => 'Customer deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Customer cannot be delete. Already used by other records.'
            ], 202);
        }
    }
}
