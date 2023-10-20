<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductMovementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_movements', function (Blueprint $table) {
            $table->id();
            $table->string('product_movement_no')->nullable()->comment('unique number for each product movements');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->string('requisition_number')->comment('if this product is given after requisition then requsition number is captur here');
            $table->string('regional_transfer_id')->index()->nullable()->comment('source region');
            $table->string('extension_transfer_id')->index()->nullable()->comment('source extension');
            $table->foreignId('regional_id')->nullable()->constrained('regions')->comment('destination region');
            $table->foreignId('region_extension_id')->nullable()->constrained('extensions')->comment('destination extension');
            $table->date('received_date')->nullable()->comment('date after the product is reach to the destination');
            $table->decimal('receive', 10, 2)->default(0)->comment('After the product is receive from main store tphu or any region or extension');
            $table->text('description')->nullable();
            $table->string('transfer_type')->nullable();
            $table->string('main_transfer_store')->nullable()->comment('when product is tranfer to main store');
            $table->string('status')->comment('process, verify and receive');
            $table->date('movement_date')->comment('date when product is transfering');
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
        Schema::dropIfExists('product_movements');
    }
}
