<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaleVoucherDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sale_voucher_details', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('sale_voucher_id')->index()->constrained('sale_vouchers');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->foreignId('product_transaction_id')->nullable()->constrained('product_transactions');
            $table->foreignId('discount_type_id')->index()->nullable()->constrained('discount_types');
            $table->integer('quantity');
            $table->integer('gst')->nullable();
            $table->decimal('price', 10, 2)->nullable()->default(0);
            $table->decimal('total', 10, 2)->nullable()->default(0);
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
        Schema::dropIfExists('sale_voucher_details');
    }
}
