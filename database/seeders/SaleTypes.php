<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class SaleTypes extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sale_types')->insert([
            ['name' => 'Phone', 'description' => 'Samsung mobile phones', 'created_by' => 1],
            ['name' => 'Accessory', 'description' => 'Mobile phones accessory', 'created_by' => 1],
            ['name' => 'SIM', 'description' => 'Simcard', 'created_by' => 1],

        ]);
    }
}
