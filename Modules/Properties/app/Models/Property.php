<?php

namespace Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'property_ref_no',
        'permit_number',
        'property_status',
        'property_purpose',
        'property_type',
        'property_size',
        'property_size_unit',
        'plot_area',
        'bedrooms',
        'bathrooms',
        'city',
        'locality',
        'sub_locality',
        'tower_name',
        'property_title',
        'property_title_ar',
        'property_description',
        'property_description_ar',
        'price',
        'rent_frequency',
        'furnished',
        'off_plan',
        'offplan_sale_type',
        'offplan_dld_waiver',
        'offplan_original_price',
        'offplan_amount_paid',
        'features',
        'images',
        'videos',
        'listing_agent',
        'listing_agent_phone',
        'listing_agent_email',
        'portals',
        'last_updated',
    ];

    protected $casts = [
        'features' => 'array',
        'images' => 'array',
        'videos' => 'array',
        'portals' => 'array',
        'off_plan' => 'boolean',
    ];
}