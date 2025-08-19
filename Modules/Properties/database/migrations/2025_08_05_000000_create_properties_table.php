<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('property_ref_no')->unique();
            $table->string('permit_number');
            $table->string('property_status');
            $table->string('property_purpose');
            $table->string('property_type');
            $table->integer('property_size');
            $table->string('property_size_unit');
            $table->integer('plot_area')->nullable();
            $table->integer('bedrooms');
            $table->integer('bathrooms');
            $table->string('city');
            $table->string('locality');
            $table->string('sub_locality')->nullable();
            $table->string('tower_name')->nullable();
            $table->string('property_title');
            $table->string('property_title_ar')->nullable();
            $table->text('property_description');
            $table->text('property_description_ar')->nullable();
            $table->decimal('price', 15, 2);
            $table->string('rent_frequency')->nullable();
            $table->string('furnished');
            $table->boolean('off_plan')->default(false);
            $table->string('offplan_sale_type')->nullable();
            $table->integer('offplan_dld_waiver')->nullable();
            $table->decimal('offplan_original_price', 15, 2)->nullable();
            $table->decimal('offplan_amount_paid', 15, 2)->nullable();
            $table->json('features');
            $table->json('images');
            $table->json('videos');
            $table->string('listing_agent');
            $table->string('listing_agent_phone');
            $table->string('listing_agent_email');
            $table->json('portals');
            $table->timestamp('last_updated');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('properties');
    }
};