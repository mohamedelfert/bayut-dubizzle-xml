<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Modules\Properties\Models\Property;
use Modules\Properties\Http\Requests\StorePropertyRequest;

class ImportCrmProperties extends Command
{
    protected $signature = 'crm:import-properties';
    protected $description = 'Import properties from CRM to PropertySync module';

    public function handle()
    {        
        try {
            $this->info('Starting CRM property import...');

            // Step 1: Get access token
            $tokenResponse = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->connectTimeout(15)
            ->retry(3, 2000)
            ->post('https://testing.8xcrm.com/oauth/token', [
                'grant_type' => 'password',
                'client_id' => env('CRM_CLIENT_ID'),
                'client_secret' => env('CRM_CLIENT_SECRET'),
                'username' => env('CRM_USERNAME'),
                'password' => env('CRM_PASSWORD'),
            ]);

            if ($tokenResponse->failed()) {
                throw new \Exception(
                    'Failed to obtain access token: HTTP ' . $tokenResponse->status() . 
                    ' - Response: ' . $tokenResponse->body()
                );
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (!$accessToken) {
                throw new \Exception('Access token not found in response.');
            }

            $this->info('Access token obtained successfully.');

            // Step 2: Call broker inventory units index
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-localization' => 'en',
            ])
            ->timeout(60)
            ->connectTimeout(30)
            ->retry(3, 5000)
            ->post('https://testing.8xcrm.com/api/v2/broker-inventory/units/index');

            if ($response->failed()) {
                throw new \Exception(
                    'CRM API request failed: HTTP ' . $response->status() . 
                    ' - Response: ' . $response->body()
                );
            }

            $crmProperties = $response->json();

            if (!is_array($crmProperties)) {
                throw new \Exception('Invalid CRM response: Expected array, got ' . gettype($crmProperties));
            }

            $properties = $this->extractPropertiesFromResponse($crmProperties);

            if (empty($properties)) {
                $this->warn('No properties found in CRM response');
                Log::warning('CRM response structure: ' . json_encode($crmProperties, JSON_PRETTY_PRINT));
                return;
            }

            $validProperties = array_filter($properties, function ($item, $index) {
                if (!is_array($item)) {
                    return false;
                }
                
                $hasValidKey = isset($item['id']) || isset($item['unit_code']) || isset($item['title']);
                
                return $hasValidKey;
            }, ARRAY_FILTER_USE_BOTH);

            $this->info('Total properties in response: ' . count($properties));
            $this->info('Valid properties found: ' . count($validProperties));
            
            Log::info('Processing ' . count($validProperties) . ' valid properties from CRM.');

            $successCount = 0;
            $errorCount = 0;

            foreach ($validProperties as $index => $crmProperty) {
                try {
                    $data = $this->mapCrmPropertyToLocalFormat($crmProperty, $index);

                    $validator = validator($data, (new StorePropertyRequest)->rules());
                    if ($validator->fails()) {
                        $errorMessage = 'Validation failed for property ' . 
                                      ($data['property_ref_no'] ?? null) . 
                                      ': ' . json_encode($validator->errors()->toArray());
                        
                        Log::warning($errorMessage);
                        $this->warn($errorMessage);
                        $errorCount++;
                        continue;
                    }

                    Property::updateOrCreate(
                        ['property_ref_no' => $data['property_ref_no']],
                        $data
                    );

                    $successCount++;
                    Log::info('Imported property: ' . $data['property_ref_no']);
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $errorMessage = 'Failed to process property at index ' . $index . ': ' . $e->getMessage();
                    Log::error($errorMessage);
                    $this->error($errorMessage);
                }
            }

            $this->info("Import completed. Success: {$successCount}, Errors: {$errorCount}");
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $errorMessage = 'Connection failed to CRM API: ' . $e->getMessage() . 
                          '. Please check network connectivity and API endpoint.';
            Log::error($errorMessage);
            $this->error($errorMessage);
            
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $errorMessage = 'HTTP request failed: ' . $e->getMessage();
            Log::error($errorMessage);
            $this->error($errorMessage);
            
        } catch (\Exception $e) {
            $errorMessage = 'Property sync error: ' . $e->getMessage() . 
                          ' at ' . $e->getFile() . ':' . $e->getLine();
            Log::error($errorMessage);
            $this->error('Failed to import properties: ' . $e->getMessage());
        }
    }

    private function extractPropertiesFromResponse(array $crmProperties, bool $debug = false): array
    {
        $possiblePaths = [
            ['data', 'data'],
            ['data'],
            ['properties'],
            ['units'],
            ['results'],
            [0],
        ];
        
        foreach ($possiblePaths as $path) {
            $current = $crmProperties;
            $pathStr = implode('.', $path);
            
            foreach ($path as $key) {
                if (isset($current[$key]) && is_array($current[$key])) {
                    $current = $current[$key];
                } else {
                    continue 2;
                }
            }
            
            if ($this->isPropertiesArray($current)) {
                if ($debug) {
                    $this->info("Found properties under path: {$pathStr}");
                }
                return $current;
            }
        }
        
        if ($this->isPropertiesArray($crmProperties)) {
            return $crmProperties;
        }
        
        return [];
    }

    private function isPropertiesArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }
        
        $firstElement = reset($array);
        if (!is_array($firstElement)) {
            return false;
        }
        
        $propertyKeys = ['id', 'unit_code', 'title', 'price', 'area', 'city_id', 'name', 'reference', 'code'];
        $foundKeys = array_intersect($propertyKeys, array_keys($firstElement));
        
        return count($foundKeys) >= 1;
    }

    private function mapCrmPropertyToLocalFormat(array $crmProperty, int $index): array
    {
        return [
            'property_ref_no' => $crmProperty['unit_code'] ?? 'UNIT-' . uniqid(),
            'permit_number' => $crmProperty['unit_code'] ?? null,
            'property_status' => $this->mapStatus($crmProperty['availability'] ?? null),
            'property_purpose' => $this->mapPurpose($crmProperty['bi_purpose_id'] ?? null, $crmProperty['bi_purpose_type_id'] ?? null),
            'property_type' => $this->mapPropertyType($crmProperty['bi_purpose_type_id'] ?? 1),
            'property_size' => $crmProperty['area'] ?? 1000,
            'property_size_unit' => 'SQFT',
            'plot_area' => $crmProperty['plot_area'] ?? null,
            'bedrooms' => $this->mapBedrooms($crmProperty['bi_bedroom_id'] ?? 1),
            'bathrooms' => $this->mapBathrooms($crmProperty['bi_bathroom_id'] ?? 1),
            'city' => $this->mapCity($crmProperty['city_id'] ?? 110),
            'locality' => $this->mapLocality($crmProperty['area_place_id'] ?? null) ?? 'Downtown Dubai',
            'sub_locality' => $this->mapSubLocality($crmProperty['sub_area_place_id'] ?? null),
            'tower_name' => isset($crmProperty['building_number']) && $crmProperty['building_number'] !== 'Access Denied' ? $crmProperty['building_number'] : null,
            'property_title' => $crmProperty['title'] ?? 'Property ' . ($crmProperty['unit_code'] ?? null),
            'property_title_ar' => null,
            'property_description' => $crmProperty['description'] ?? 'Property description for ' . ($crmProperty['unit_code'] ?? null),
            'property_description_ar' => null,
            'price' => $this->convertPrice($crmProperty['price'] ?? 100000, $crmProperty['currency_code'] ?? 'USD'),
            'rent_frequency' => $this->mapRentFrequency($crmProperty['bi_purpose_id'] ?? null, $crmProperty['number_of_installments'] ?? null),
            'furnished' => $this->mapFurnished($crmProperty['bi_furnishing_status_id'] ?? 2),
            'off_plan' => isset($crmProperty['is_delivered']) && $crmProperty['is_delivered'] == 0 ? true : false,
            'offplan_sale_type' => $this->mapOfferingType($crmProperty['bi_offering_type_id'] ?? null),
            'offplan_dld_waiver' => isset($crmProperty['bi_offering_type_id']) && $crmProperty['bi_offering_type_id'] == 1 ? (isset($crmProperty['down_payment']) && $crmProperty['down_payment'] ? 0 : 1) : null,
            'offplan_original_price' => isset($crmProperty['bi_offering_type_id']) && $crmProperty['bi_offering_type_id'] == 2 ? ($crmProperty['price'] ?? null) : null,
            'offplan_amount_paid' => $crmProperty['down_payment'] ?? null,
            'features' => json_encode($this->sanitizeArray($crmProperty['facilities_ids'] ?? [])),
            'images' => json_encode($this->sanitizeArray($crmProperty['media'] ?? (isset($crmProperty['featured_image']) && $crmProperty['featured_image'] ? [$crmProperty['featured_image']] : []))),
            'videos' => json_encode($this->sanitizeArray(isset($crmProperty['video_embed_url']) && $crmProperty['video_embed_url'] ? [$crmProperty['video_embed_url']] : [])),
            'portals' => json_encode(['Bayut', 'dubizzle']),
            'listing_agent' => isset($crmProperty['seller']) && is_array($crmProperty['seller']) ? ($crmProperty['seller']['name'] ?? null) : 'Default Agent',
            'listing_agent_phone' => isset($crmProperty['seller']) && is_array($crmProperty['seller']) ? ($crmProperty['seller']['phone'] ?? null) : '+971-50-123-4567',
            'listing_agent_email' => isset($crmProperty['seller']) && is_array($crmProperty['seller']) ? ($crmProperty['seller']['email'] ?? 'agent@realestate.com') : 'agent@realestate.com',
            'last_updated' => $this->formatDateTime($crmProperty['updated_at'] ?? now()),
        ];
    }

    private function mapPropertyType($purposeTypeId)
    {
        $typeMap = [
            1 => 'Apartment',
            2 => 'Villa',
            3 => 'Townhouse',
        ];
        return $typeMap[$purposeTypeId] ?? 'Apartment';
    }

    private function mapStatus($availability)
    {
        return $availability === 'Available' ? 'live' : 'inactive';
    }

    private function mapPurpose($purposeId, $purposeTypeId)
    {
        if ($purposeId == 1 && in_array($purposeTypeId, [1, 2, 3])) {
            return 'Sale';
        }
        return 'Rent';
    }

    private function mapBedrooms($bedroomId)
    {
        return $bedroomId ?: 1;
    }

    private function mapBathrooms($bathroomId)
    {
        return $bathroomId ?: 1;
    }

    private function mapCity($cityId)
    {
        $cityMap = [
            110 => 'Dubai',
            52758 => 'Dubai',
            52970 => 'Abu Dhabi',
        ];
        return $cityMap[$cityId] ?? 'Dubai';
    }

    private function mapLocality($areaPlaceId)
    {
        $localityMap = [
            53131 => 'Downtown Dubai',
        ];
        return $localityMap[$areaPlaceId] ?? null;
    }

    private function mapSubLocality($subAreaPlaceId)
    {
        $subLocalityMap = [
            53132 => 'Burj Khalifa Area',
        ];
        return $subLocalityMap[$subAreaPlaceId] ?? null;
    }

    private function mapFurnished($furnishingStatusId)
    {
        $furnishedMap = [
            1 => 'Yes',
            2 => 'No',
            3 => 'Partly',
        ];
        return $furnishedMap[$furnishingStatusId] ?? 'No';
    }

    private function mapRentFrequency($purposeId, $installments)
    {
        if ($purposeId != 1 && isset($installments)) {
            return $installments >= 12 ? 'Yearly' : 'Monthly';
        }
        return null;
    }

    private function mapOfferingType($offeringTypeId)
    {
        return match ($offeringTypeId) {
            1 => 'New',
            2 => 'Resale',
            default => null,
        };
    }

    private function convertPrice($price, $currency)
    {
        if ($currency === 'EGP') {
            return $price ? round($price / 10) : 100000;
        }
        return $price ?: 100000;
    }

    private function sanitizeArray($input)
    {
        if (is_array($input)) {
            return array_filter(array_map(function($value) {
                if (is_array($value)) {
                    return isset($value['id']) ? $value['id'] : 
                           (isset($value['name']) ? $value['name'] : 
                           (isset($value['value']) ? $value['value'] : json_encode($value)));
                }
                return is_string($value) ? trim($value) : (string) $value;
            }, $input), fn($value) => !empty($value));
        }
        if (is_string($input) && !empty($input)) {
            return array_filter(array_map('trim', explode(',', $input)), fn($value) => !empty($value));
        }
        return [];
    }

    private function formatDateTime($datetime)
    {
        if (empty($datetime)) {
            return now()->toDateTimeString();
        }

        try {
            if (is_string($datetime)) {
                $date = \Carbon\Carbon::parse($datetime);
                return $date->toDateTimeString();
            }
            
            return now()->toDateTimeString();
        } catch (\Exception $e) {
            Log::warning('Failed to parse datetime: ' . $datetime . ' - ' . $e->getMessage());
            return now()->toDateTimeString();
        }
    }
}