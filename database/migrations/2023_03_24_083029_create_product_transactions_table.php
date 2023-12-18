<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_movement_id')->nullable()->constrained('product_movements');
            $table->string('requisition_number')->comment('if this product is given after requisition then requsition number is captur here');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->foreignId('regional_id')->nullable()->constrained('regions');
            $table->foreignId('region_extension_id')->nullable()->constrained('extensions');      
            $table->date('received_date');
            $table->decimal('receive', 10, 2)->default(0)->comment('After the product is receive from main store tphu, regional and site');
            $table->decimal('give_back', 10, 2)->default(0)->comment('Give back to other region, main store');
            $table->decimal('store_quantity')->nullable();
            $table->decimal('region_store_quantity',10,2)->nullable();
            $table->decimal('region_transfer_quantity',10,2)->nullable();
            $table->decimal('extension_transfer_quantity',10,2)->nullable();
            $table->decimal('sold_quantity')->default(0);
            $table->text('description')->nullable();
            $table->string('status')->comment('process, receive');
            $table->string('sale_status')->comment('stock, transfer, sold');;
            $table->date('movement_date');
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
        Schema::dropIfExists('product_transactions');
    }
}
