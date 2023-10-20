<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_no')->nullable();
            $table->string('email')->unique()->nullable()->comment("some of the dealers won't have email address");
            $table->string('username')->unique()->nullable()->comment("employee id");
            $table->string('password');
            $table->text('designation')->comment('1 = ticl_developer, 2 = TashiElectronic Admin, 3 = Executive Manager, 4 = Manager, 5 = Accountant, 6 = Customer Care Executives');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
