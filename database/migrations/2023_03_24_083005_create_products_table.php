<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('item_number')->comment('item number for mobile, phone number for sim and part number for accessories');
            $table->foreignId('category_id')->nullable()->constrained('categories')->comment('family is capture here');
            $table->foreignId('sub_category_id')->nullable()->constrained('sub_categories')->comment('item serial  is capture here');             
            $table->string('serial_no')->nullable()->comment('for the mobile product imei');
            $table->string('batch_no')->comment('batch number when upload product');
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->foreignId('brand_id')->nullable()->constrained('brands');
            $table->foreignId('color_id')->nullable()->constrained('colors');
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->foreignId('sale_type_id')->constrained('sale_types')->comment('phone, spare parts and sim, its product type');
            $table->decimal('total_quantity', 10, 2)->default(0)->comment('total quatity received for records');
            $table->decimal('quantity', 10, 2)->default(0)->comment('total product recieved that will be use for distribution and for repair');
            $table->decimal('distributed_quantity', 10, 2)->default(0)->comment('product distribute to the employee');
            $table->decimal('region_transfer', 10, 2)->default(0)->comment('item that is transfer from region, it will be added to quatity fro transfer again to other regions');
            $table->string('region_name')->nullable()->comment('the product that is transfer from region to main store');
            $table->decimal('price', 10, 2)->default(0)->nullable()->comment('price for one product');            
            $table->date('created_date')->nullable();
            $table->longText('description')->nullable()->comment('product name');
            $table->string('sub_inventory')->nullable()->comment('sub inventory for mobile phones');
            $table->string('locator')->nullable()->comment('locator for mobile phone');
            $table->string('iccid')->nullable()->comment('iccid for sim card');
            $table->string('status')->comment('new, verified, received, rejected here there is possibity of getting not all product');
            $table->string('sale_status')->comment('stock, transfer, sold');
            $table->integer('parent_id')->index()->nullable()->comment('for the damage product ,not necessary required');           
            $table->decimal('damage_quantity', 10, 2)->default(0)->comment('how many product product has been dismantlenot necessary required');
            $table->decimal('main_store_sold_quantity', 10, 2)->default(0)->comment('products sold from main store');
            $table->decimal('upload_item', 10, 2)->default(0)->comment('upload old items in main store or regional storenot necessary required');
            $table->string('region_upload')->nullable()->comment('when old items are uploadnot necessary required');
            $table->string('photo')->nullable()->comment('product photo to refernot necessary required');
            $table->longText('content')->nullable()->comment('description of phone specific like in tashicell websitenot necessary required');
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
        Schema::dropIfExists('products');
    }
}
