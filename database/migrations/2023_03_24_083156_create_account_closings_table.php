<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountClosingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_closings', function (Blueprint $table) {
            $table->id();
            $table->string('status')->comment('open by customer care and closed by account');
            $table->foreignId('regional_id')->nullable()->constrained('regions');
            $table->foreignId('region_extension_id')->nullable()->constrained('extensions');      
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
        Schema::dropIfExists('account_closings');
    }
}
