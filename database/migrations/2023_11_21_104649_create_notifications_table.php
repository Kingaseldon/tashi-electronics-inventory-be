<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users');
            $table->foreignId('extension_from')->index()->constrained('extensions');
            $table->foreignId('extension_to')->index()->constrained('extensions');
            $table->foreignId('product_id')->index()->constrained('products');
            $table->string('requisition_number');  
            $table->decimal('quantity');  
            $table->date('created_date');
            $table->foreignId('created_by')->index()->constrained('users');
            $table->string('status');
            $table->boolean('read');
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
        Schema::dropIfExists('notifications');
    }
}
