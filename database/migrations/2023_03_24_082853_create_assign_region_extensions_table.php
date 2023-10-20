<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssignRegionExtensionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assign_region_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regional_id')->nullable()->constrained('regions');
            $table->foreignId('extension_id')->nullable()->constrained('extensions');
            $table->foreignId('user_id')->index()->constrained('users');
            $table->boolean('is_assign')->default(0);
            $table->string('assign_type')->nullable();     
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
        Schema::dropIfExists('assign_region_extensions');
    }
}
