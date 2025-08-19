<?php

namespace Modules\Properties\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'property_ref_no' => 'required|string|unique:properties,property_ref_no',
            'permit_number' => 'required|string',
            'property_status' => 'required|in:live,inactive',
            'property_purpose' => 'required|in:Sale,Rent',
            'property_type' => 'required|string',
            'property_size' => 'required|numeric',
            'property_size_unit' => 'required|string',
            'plot_area' => 'nullable|numeric',
            'bedrooms' => 'required|integer',
            'bathrooms' => 'required|integer',
            'city' => 'required|string',
            'locality' => 'nullable|string',
            'sub_locality' => 'nullable|string',
            'tower_name' => 'nullable|string',
            'property_title' => 'required|string',
            'property_description' => 'required|string',
            'price' => 'required|numeric',
            'rent_frequency' => 'nullable|in:Yearly,Monthly',
            'furnished' => 'required|in:Yes,No,Partly',
            'off_plan' => 'required|boolean',
            'offplan_sale_type' => 'nullable|in:New,Resale',
            'offplan_dld_waiver' => 'nullable|in:0,1',
            'offplan_original_price' => 'nullable|numeric',
            'offplan_amount_paid' => 'nullable|numeric',
            'features' => 'nullable|string',
            'images' => 'nullable|string',
            'videos' => 'nullable|string',
            'portals' => 'required|string',
            'listing_agent' => 'required|string',
            'listing_agent_phone' => 'required|string',
            'listing_agent_email' => 'required|email',
            'last_updated' => 'required|date',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
