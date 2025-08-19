<?php

namespace Modules\Properties\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Properties\Models\Property;
use DOMDocument;

class PropertiesController extends Controller
{
    public function generateXml()
    {
        try {
            $properties = Property::all();

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;

            $root = $dom->createElement('Properties');
            $dom->appendChild($root);

            foreach ($properties as $property) {
                $portals = is_array($property->portals) ? $property->portals : (is_string($property->portals) ? json_decode($property->portals, true) ?? [] : []);
                if (!in_array('Bayut', $portals) && !in_array('dubizzle', $portals)) {
                    continue;
                }

                $propertyNode = $dom->createElement('Property');
                $root->appendChild($propertyNode);

                // add child element with CDATA
                $addCdataElement = function ($parent, $name, $value) use ($dom) {
                    $element = $dom->createElement($name);
                    $cdata = $dom->createCDATASection($value ?? '');
                    $element->appendChild($cdata);
                    $parent->appendChild($element);
                };

                // Basic Property Details
                $addCdataElement($propertyNode, 'Property_Ref_No', $property->property_ref_no);
                $addCdataElement($propertyNode, 'Permit_Number', $property->permit_number);
                $addCdataElement($propertyNode, 'Property_Status', $property->property_status);
                $addCdataElement($propertyNode, 'Property_purpose', $property->property_purpose);
                $addCdataElement($propertyNode, 'Property_Type', $property->property_type);
                $addCdataElement($propertyNode, 'Property_Size', $property->property_size);
                $addCdataElement($propertyNode, 'Property_Size_Unit', $property->property_size_unit);
                $addCdataElement($propertyNode, 'plotArea', $property->plot_area ?? '');
                $addCdataElement($propertyNode, 'Bedrooms', $property->property_type === 'Studio' ? '0' : $property->bedrooms);
                $addCdataElement($propertyNode, 'Bathrooms', $property->bathrooms);

                // Location Details
                $addCdataElement($propertyNode, 'City', $property->city);
                $addCdataElement($propertyNode, 'Locality', $property->locality);
                $addCdataElement($propertyNode, 'Sub_Locality', $property->sub_locality ?? '');
                $addCdataElement($propertyNode, 'Tower_Name', $property->tower_name ?? '');
                $addCdataElement($propertyNode, 'Locationtext', $property->city . ' - ' . $property->locality);

                // Property Information
                $addCdataElement($propertyNode, 'Property_Title', $property->property_title);
                $addCdataElement($propertyNode, 'Property_Title_AR', $property->property_title_ar ?? $property->property_title);
                $addCdataElement($propertyNode, 'Property_Description', $property->property_description);
                $addCdataElement($propertyNode, 'Property_Description_AR', $property->property_description_ar ?? $property->property_description);

                // Pricing
                $addCdataElement($propertyNode, 'Price', $property->price);
                if ($property->property_purpose === 'Rent') {
                    $addCdataElement($propertyNode, 'Rent_Frequency', $property->rent_frequency ?? 'Yearly');
                }
                $addCdataElement($propertyNode, 'Furnished', $property->furnished);

                // Off-plan Details
                $addCdataElement($propertyNode, 'Off_plan', $property->off_plan ? 'Yes' : 'No');
                if ($property->off_plan) {
                    $addCdataElement($propertyNode, 'offplanDetails_saleType', $property->offplan_sale_type ?? 'New');
                    if ($property->offplan_sale_type === 'New') {
                        $addCdataElement($propertyNode, 'offplanDetails_dldWaiver', $property->offplan_dld_waiver ?? 0);
                    } elseif ($property->offplan_sale_type === 'Resale') {
                        $addCdataElement($propertyNode, 'offplanDetails_originalPrice', $property->offplan_original_price ?? 0);
                        $addCdataElement($propertyNode, 'offplanDetails_amountPaid', $property->offplan_amount_paid ?? 0);
                    }
                }

                // Features
                $featuresNode = $dom->createElement('Features');
                $propertyNode->appendChild($featuresNode);
                $features = is_array($property->features) ? $property->features : (is_string($property->features) ? json_decode($property->features, true) ?? [] : []);
                foreach ($features as $feature) {
                    $addCdataElement($featuresNode, 'Feature', trim($feature));
                }

                // Images
                $imagesNode = $dom->createElement('Images');
                $propertyNode->appendChild($imagesNode);
                $images = is_array($property->images) ? $property->images : (is_string($property->images) ? json_decode($property->images, true) ?? [] : []);
                foreach ($images as $image) {
                    $addCdataElement($imagesNode, 'Image', trim($image));
                }

                // Videos
                $videosNode = $dom->createElement('Videos');
                $propertyNode->appendChild($videosNode);
                $videos = is_array($property->videos) ? $property->videos : (is_string($property->videos) ? json_decode($property->videos, true) ?? [] : []);
                foreach ($videos as $video) {
                    $addCdataElement($videosNode, 'Video', trim($video));
                }

                // Portals
                $portalsNode = $dom->createElement('Portals');
                $propertyNode->appendChild($portalsNode);
                foreach ($portals as $portal) {
                    $addCdataElement($portalsNode, 'Portal', trim($portal));
                }

                // Last Updated
                $addCdataElement($propertyNode, 'Last_Updated', $property->last_updated);
            }

            return response($dom->saveXML(), 200, ['Content-Type' => 'application/xml']);

        } catch (\Exception $e) {
            return response('Error generating XML: ' . $e->getMessage(), 500);
        }
    }
}