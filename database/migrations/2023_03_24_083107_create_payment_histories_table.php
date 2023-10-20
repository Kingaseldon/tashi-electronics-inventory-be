<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_histories', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('sale_voucher_id')->index()->constrained('sale_vouchers');
            $table->string('receipt_no');
            $table->string('payment_mode')->comment('online, cheque, cash');
            $table->string('reference_no')->nullable()->comment('required if payment mode is online or cheque');
            $table->string('payment_status')->nullable()->comment('partial, full');
            $table->foreignId('bank_id')->index()->nullable()->constrained('banks');
            $table->decimal('cash_amount_paid', 14, 2)->default(0);
            $table->decimal('online_amount_paid', 14, 2)->default(0);
            $table->decimal('total_amount_paid', 14, 2)->default(0);
            $table->date('paid_at');
            $table->string('attachment')->nullable();
            $table->timestamps();
            $table->string('remarks')->nullable();
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
        Schema::dropIfExists('payment_histories');
    }
}
