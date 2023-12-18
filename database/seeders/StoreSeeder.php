<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('stores')->insert([
            ['store_name' => 'main store', 'region_id' => null, 'extension_id' => null, 'created_by' => 1],           
        ]);
    }
}
