<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('categories')->insert([
            ['sale_type_id' => 1, 'code' => 'phones', 'created_by' => 1],
            ['sale_type_id' => 2, 'code' => 'accessory', 'created_by' => 1],
            ['sale_type_id' => 3, 'code' => 'Simcard', 'created_by' => 1],
        ]);
    }
}
