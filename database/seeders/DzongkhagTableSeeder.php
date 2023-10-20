<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DzongkhagTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('dzongkhags')->insert([
		    ['name' => 'Bumthang', 'created_by' => 1],
		    ['name' => 'Chukha', 'created_by' => 1],
		    ['name' => 'Dagana', 'created_by' => 1],
		    ['name' => 'Gasa', 'created_by' => 1],
		    ['name' => 'Haa', 'created_by' => 1],
		    ['name' => 'Lhuntse', 'created_by' => 1],
		    ['name' => 'Mongar', 'created_by' => 1],
		    ['name' => 'Paro', 'created_by' => 1],
		    ['name' => 'Pemagatshel', 'created_by' => 1],
		    ['name' => 'Punakha', 'created_by' => 1],
		    ['name' => 'Samdrup Jongkhar', 'created_by' => 1],
		    ['name' => 'Samtse', 'created_by' => 1],
		    ['name' => 'Sarpang', 'created_by' => 1],
		    ['name' => 'Thimphu', 'created_by' => 1],
		    ['name' => 'Trashigang', 'created_by' => 1],
		    ['name' => 'Trashiyangtse', 'created_by' => 1],
		    ['name' => 'Trongsa', 'created_by' => 1],
		    ['name' => 'Tsirang', 'created_by' => 1],
		    ['name' => 'Wangdue Phodrang', 'created_by' => 1],
		    ['name' => 'Zhemgang', 'created_by' => 1]
		]);
    }
}
