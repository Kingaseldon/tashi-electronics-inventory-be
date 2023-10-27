<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscountTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discount_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->comment('family is capture here');
            $table->foreignId('sub_category_id')->nullable()->constrained('sub_categories')->comment('item serial  is capture here');   
            $table->foreignId('region_id')->nullable()->constrained('regions');   
            $table->foreignId('extension_id')->nullable()->constrained('extensions');   
            $table->string('discount_name');
            $table->string('discount_type');
            $table->string('discount_value'); 
            $table->string('applicable_to'); 
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();     
            $table->text('description')->nullable();
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
        Schema::dropIfExists('discount_types');
    }
}
