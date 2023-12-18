<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductRequisitionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_number')->comment('requistion number when requested . i can be multiple since they request multiple');
            $table->string('product_item_number')->comment('product item number is capture here, cannot capture product sice product will have same item number with different serial number');
            $table->integer('requisition_to');//1 for main store and 2 for region store, 3 for exten to extension transfer
            $table->integer('requested_extension')->nullable();
            $table->foreignId('sale_type_id')->constrained('sale_types')->comment('phone, spare parts and sim, its product type');
            $table->foreignId('regional_id')->nullable()->constrained('regions');
            $table->foreignId('region_extension_id')->nullable()->constrained('extensions');
            $table->date('request_date');
            $table->date('transfer_date')->nullable();
            $table->string('status')->comment('requested or supplied');
            $table->string('description')->comment('product description');
            $table->decimal('request_quantity', 10, 2)->default(0)->comment('request quantity');
            $table->decimal('transfer_quantity', 10, 2)->default(0)->comment('transfer quantity is update when it is transfer from main store');
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
        Schema::dropIfExists('product_requisitions');
    }
}
