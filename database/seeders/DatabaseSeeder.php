<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UserRoleTableSeeder::class);
        $this->call(DzongkhagTableSeeder::class);
        $this->call(GewogTableSeeder::class);
        $this->call(VillageTableSeeder::class);
        $this->call(CustomerTypeSeeder::class);
        $this->call(SaleTypes::class);
        $this->call(CategorySeeder::class);
        $this->call(StoreSeeder::class);
    }
}
