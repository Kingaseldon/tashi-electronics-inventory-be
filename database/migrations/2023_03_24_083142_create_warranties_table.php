<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarrantiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warranties', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('sale_voucher_id')->index()->constrained('sale_vouchers');
            $table->foreignId('product_id')->index()->constrained('products');
            $table->foreignId('discount_type_id')->index()->constrained('discount_types');
            $table->foreignId('promotion_type_id')->index()->constrained('promotion_types');
            $table->integer('quantity');
            $table->string('status')->comment('in warranty or out warranty');
            $table->integer('gst')->nullable();
            $table->decimal('price', 10, 2)->nullable()->default(0);
            $table->decimal('total', 10, 2)->nullable()->default(0);
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
        Schema::dropIfExists('warranties');
    }
}
