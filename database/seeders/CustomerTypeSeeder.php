<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('customer_types')->insert([
            ['name' => 'Staff','description'=>'Employee', 'created_by' => 1],
            ['name' => 'Wholesaler', 'description' => 'Dealer', 'created_by' => 1],
            ['name' => 'Retailer', 'description' => 'Walk in customer','created_by' => 1],
          
        ]);
    }
}
