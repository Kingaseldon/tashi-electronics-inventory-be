<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaleVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sale_vouchers', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('regional_id')->nullable()->constrained('regions');
            $table->foreignId('region_extension_id')->nullable()->constrained('extensions');
            $table->foreignId('customer_id')->index()->nullable()->comment('if the sale is from agent to customer')->constrained('customers');
            $table->string('sale_type')->nullable()->comment('walk in, bulk, emi, corporate, Distributor');
            $table->string('walk_in_customer')->nullable()->comment('walk in customer name');
            $table->string('contact_no')->nullable()->comment('customer number');
            $table->foreignId('discount_type_id')->nullable()->constrained('discount_types');
            $table->string('invoice_no')->comment('unique number for each sale voucher');
            $table->date('invoice_date')->nullable();
            $table->decimal('gross_payable', 20, 2)->nullable();
            $table->string('discount_type')->nullable()->comment('none, lumpsum, percentage');
            $table->decimal('discount_rate', 10, 2)->nullable()->default(0);
            $table->decimal('net_payable', 20, 2)->nullable();            
            $table->boolean('invoice_emailed')->default(0);
            $table->text('remarks')->nullable();
            $table->string('status')->nullable()->comment('invoice is closed or discarded');
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
        Schema::dropIfExists('sale_vouchers');
    }
}
