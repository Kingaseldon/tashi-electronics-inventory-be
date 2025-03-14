<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionAuditsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_audits', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id');
            $table->integer('sales_type_id');
            $table->integer('product_id');
            $table->string('item_number');
            $table->longText('description');
            $table->integer('stock')->default(0);
            $table->integer('received')->default(0);
            $table->integer('transfer')->default(0);
            $table->integer('sales')->default(0);
            $table->date('created_date');
            $table->string('status');
            $table->foreignId('created_by')->index()->constrained('users');
            $table->foreignId('updated_by')->index()->nullable()->constrained('users');
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
        Schema::dropIfExists('transaction_audits');
    }
}
