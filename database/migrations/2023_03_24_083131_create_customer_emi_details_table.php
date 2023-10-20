<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerEmiDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_emi_details', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('customer_emi_id')->index()->constrained('customer_emis');                    
            $table->string('installment_count');
            $table->string('payment_type');
            $table->string('journal_no');
            $table->date('emi_date');
            $table->decimal('amount', 10, 2)->nullable()->default(0);
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
        Schema::dropIfExists('customer_emi_details');
    }
}
