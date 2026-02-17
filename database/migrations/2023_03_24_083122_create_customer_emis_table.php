<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerEmisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_emis', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('user_id')->index()->constrained('users');
            $table->string('emi_no')->nullable()->comment('unique number for each emi');
            $table->foreignId('sale_voucher_id')->index()->nullable()->constrained('sale_vouchers');
            $table->foreignId('product_id')->nullable()->index()->constrained('products');
            $table->integer('quantity')->default(1);
            $table->date('request_date');
            $table->integer('emi_duration');
            $table->decimal('monthly_emi', 10, 2)->nullable()->default(0);
            $table->decimal('total', 10, 2)->nullable()->default(0);
            $table->decimal('gst', 10, 2)->nullable()->default(0);
            $table->date('deduction_from')->nullable();
            $table->string('status')->nullable();
            $table->string('description')->nullable();
            $table->string('item_number')->nullable();
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
        Schema::dropIfExists('customer_emis');
    }
}
