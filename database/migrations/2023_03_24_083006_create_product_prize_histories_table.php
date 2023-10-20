<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductPrizeHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_prize_histories', function (Blueprint $table) {
            $table->id();
            $table->decimal('price', 10, 2)->default(0)->nullable()->comment('price for one product'); 
            $table->string('product_item_number')->comment('product item number is capture here, cannot capture product since product will have same item number with different serial number esp phone and sim');
            $table->date('change_date');
            $table->timestamps();
            $table->foreignId('created_by')->index()->constrained('users');
            $table->foreignId('updated_by')->index()->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_prize_histories');
    }
}
