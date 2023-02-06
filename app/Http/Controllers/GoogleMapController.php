<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Response;
use Log;

class GoogleMapController extends Controller
{
    public $gmapKey;
    public function __construct()
    {
        $this->gmapKey = env('GOOGLE_MAP_KEY');
    }

    private function gmapSDK()
    {
        $gmaps = new \yidas\googleMaps\Client(['key' => env('GOOGLE_MAP_KEY')]);
        $gmaps->setLanguage('th-TH');
        return $gmaps;
    }

    /**
     * Calculate Distance by (latitude, longitude)
     *
     * @param  \ latitude Latitude
     * @return \ Distance
     */

    public function circle_distance($point1_lat, $point1_long, $point2_lat, $point2_long, $unit = 'km', $decimals = 2)
    {
        $degrees = rad2deg(acos((sin(deg2rad($point1_lat)) * sin(deg2rad($point2_lat))) + (cos(deg2rad($point1_lat)) * cos(deg2rad($point2_lat)) * cos(deg2rad($point1_long - $point2_long)))));

        switch ($unit) {
            case 'km':
                $distance = $degrees * 111.13384; // 1 degree = 111.13384 km, based on the average diameter of the Earth (12,735 km)
                break;
            case 'mi':
                $distance = $degrees * 69.05482; // 1 degree = 69.05482 miles, based on the average diameter of the Earth (7,913.1 miles)
                break;
            case 'nmi':
                $distance = $degrees * 59.97662; // 1 degree = 59.97662 nautic miles, based on the average diameter of the Earth (6,876.3 nautical miles)
        }
        return round($distance, $decimals);
    }

    public function encodeImagesFromUrlToBase64($ref) {
        $queryParametter = [
            'sensor' => false,
            'maxheight' => 300,
            'maxwidth' => 300,
            'photoreference' => $ref,
            'key' => $this->gmapKey,
        ];
        if (Cache::has(implode(" ", $queryParametter))) {
            $response = base64_decode(Cache::store('file')->get(implode(" ", $queryParametter)));
        } else {
            $response = Http::get('https://maps.googleapis.com/maps/api/place/photo?', $queryParametter);
            Cache::add(implode(" ", $queryParametter), base64_encode($response), Carbon::now()->addMinute(60));
        }
        return response($response)->header('Content-Type', 'image/png');
    }

    /**
     * Get nearby locations (latitude, longitude)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getNearByByLocation(Request $request)
    {
        try {
            if (!$request->latitude) {
                throw new Exception('Latitude is required');
            }
            if (!$request->longitude) {
                throw new Exception('Longitude is required');
            }
            if (empty($this->gmapKey)) {
                throw new Exception('Gmap key is required');
            }
            $queryParametter = [
                'location' => $request->latitude . ',' . $request->longitude,
                'radius' => '5000',
                'types' => 'restaurant',
                'name' => empty($request->name) ? '' : $request->name,
                'key' => $this->gmapKey,
            ];
            $listRestaurant = [];
            if (Cache::has(implode(" ", $queryParametter))) {
                $listRestaurant = Cache::store('file')->get(implode(" ", $queryParametter));
            } else {
                $response = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json?', $queryParametter);
                foreach ($response->object()->results as $value) {
                    $_tempRestaurant = [
                        'name' => (string) $value->name,
                        'icon' => (string) $value->icon,
                        'icon_background_color' => (string) $value->icon_background_color,
                        'business_status' => (string) empty($value->business_status) ? 'UNKNOW' : (string) $value->business_status,
                        'distance' => (float) $this->circle_distance($value->geometry->location->lat, $value->geometry->location->lng, $request->latitude, $request->longitude),
                        'rating' => (float) empty($value->rating) ? '0' : (float) $value->rating,
                        'location' => ['latitude' => (float) $value->geometry->location->lat, 'longitude' => (float) $value->geometry->location->lng],
                    ];
                    array_push($listRestaurant, $_tempRestaurant);
                }
                Cache::add(implode(" ", $queryParametter), $listRestaurant, Carbon::now()->addMinute(1));
            }
            return response()->json(['responseCode' => 200, 'responseMsg' => 'success', 'data' => $listRestaurant]);
        } catch (Exception $e) {
            return response()->json(['responseCode' => 400, 'responseMsg' => $e->getMessage(), 'data' => []]);
        }
    }

    public function searchByName(Request $request)
    {
        try {
            if (empty($this->gmapKey)) {
                throw new Exception('Gmap key is required');
            }
            $queryParametter = [
                'types' => 'restaurant',
                'query' => empty($request->name) ? '' : $request->name,
                'key' => $this->gmapKey,
            ];
            $listRestaurant = [];
            if (Cache::has(implode(" ", $queryParametter))) {
                $listRestaurant = Cache::store('file')->get(implode(" ", $queryParametter));
            } else {
                $response = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json?', $queryParametter);
                foreach ($response->object()->results as $value) {
                    $_tempRestaurant = [
                        'name' => (string) $value->name,
                        'icon' => (string) $value->icon,
                        'icon_background_color' => (string) $value->icon_background_color,
                        'business_status' => (string) empty($value->business_status) ? 'UNKNOW' : (string) $value->business_status,
                        'distance' => (float) $this->circle_distance($value->geometry->location->lat, $value->geometry->location->lng, $request->latitude, $request->longitude),
                        'rating' => (float) empty($value->rating) ? '0' : (float) $value->rating,
                        'img' => empty($value->photos[0]->photo_reference) ? url('/img/gray.png')  : url('gmap/placephoto') . '/' . $value->photos[0]->photo_reference,
                        'location' => ['latitude' => (float) $value->geometry->location->lat, 'longitude' => (float) $value->geometry->location->lng],
                    ];
                    array_push($listRestaurant, $_tempRestaurant);
                }
                Cache::add(implode(" ", $queryParametter), $listRestaurant, Carbon::now()->addMinute(1));
            }
            return response()->json(['responseCode' => 200, 'responseMsg' => 'success', 'data' => $listRestaurant]);
        } catch (Exception $e) {
            return response()->json(['responseCode' => 400, 'responseMsg' => $e->getMessage(), 'data' => []]);
        }
    }
}
