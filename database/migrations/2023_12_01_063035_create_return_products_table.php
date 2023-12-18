<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReturnProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('return_products', function (Blueprint $table) {
            $table->id();
            $table->string('item_number')->comment('item number for mobile, phone number for sim and part number for accessories');
            $table->foreignId('category_id')->nullable()->constrained('categories')->comment('family is capture here');
            $table->foreignId('sale_type_id')->constrained('sale_types')->comment('phone, spare parts and sim, its product type');
            $table->foreignId('sub_category_id')->nullable()->constrained('sub_categories')->comment('item serial  is capture here');
            $table->string('serial_no')->nullable()->comment('for the mobile product imei');
            $table->string('invoice_number')->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->foreignId('color_id')->nullable()->constrained('colors');
            $table->decimal('total_quantity', 10, 2)->default(0)->comment('total quatity received for records');
            $table->decimal('price', 10, 2)->nullable()->comment('price for one product');
            $table->date('created_date')->nullable();
            $table->longText('description')->nullable()->comment('product name');
            $table->string('sub_inventory')->nullable()->comment('sub inventory for mobile phones');
            $table->string('locator')->nullable()->comment('locator for mobile phone');
            $table->string('iccid')->nullable()->comment('iccid for sim card');
            $table->string('status')->comment('replaced,refubrished');
            $table->string('sale_status')->nullable();
            $table->foreignId('created_by')->index()->constrained('users');
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
        Schema::dropIfExists('return_products');
    }
}
