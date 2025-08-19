<?php

namespace Modules\Properties\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Properties\Models\Property;

class PropertyTableSeeder extends Seeder
{
    public function run()
    {
        Property::create([
            'property_ref_no' => 'PROP-2024-001',
            'permit_number' => '123456',
            'property_status' => 'live',
            'property_purpose' => 'Rent',
            'property_type' => 'Apartment',
            'property_size' => 1200,
            'property_size_unit' => 'SQFT',
            'plot_area' => 1500,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'city' => 'Dubai',
            'locality' => 'Dubai Marina',
            'sub_locality' => 'Marina Walk',
            'tower_name' => 'Marina Tower',
            'property_title' => 'Stunning 2BR Apartment with Marina View',
            'property_title_ar' => 'شقة رائعة من غرفتي نوم مع إطلالة على المارينا',
            'property_description' => 'Beautiful 2-bedroom apartment with stunning marina views. Fully furnished with modern amenities. Walking distance to restaurants and shopping. Available immediately for long-term rental.',
            'property_description_ar' => 'شقة جميلة من غرفتي نوم مع إطلالات خلابة على المارينا. مفروشة بالكامل مع وسائل الراحة الحديثة. على مسافة قريبة من المطاعم والتسوق.',
            'price' => 85000,
            'rent_frequency' => 'Yearly',
            'furnished' => 'Yes',
            'off_plan' => false,
            'features' => json_encode(['Swimming Pool', 'Gym', 'Parking', 'Balcony', 'Built in Wardrobes', 'Central AC']),
            'images' => json_encode([
                'https://example.com/images/property1/living_room.jpg',
                'https://example.com/images/property1/bedroom1.jpg',
                'https://example.com/images/property1/bedroom2.jpg',
                'https://example.com/images/property1/kitchen.jpg',
                'https://example.com/images/property1/balcony_view.jpg',
            ]),
            'videos' => json_encode(['https://youtube.com/watch?v=example1']),
            'listing_agent' => 'Sarah Ahmed',
            'listing_agent_phone' => '+971-50-123-4567',
            'listing_agent_email' => 'sarah.ahmed@realestate.com',
            'portals' => json_encode(['Bayut', 'dubizzle']),
            'last_updated' => now(),
        ]);
    }
}