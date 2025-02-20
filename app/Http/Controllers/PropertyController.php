<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FloorDetail;
use App\Models\LetterHead;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\UnitDetail;
use App\Models\UserProperty;
use App\Models\WingDetail;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\Status;
use App\Models\Amenity;
use App\Models\Country;
use App\Models\Feature;
use App\Models\LeadCustomer;
use App\Models\LeadCustomerUnit;
use App\Models\LeadCustomerUnitData;
use App\Models\ModulePlanFeature;
use App\Models\PaymentTransaction;
use App\Models\State;
use App\Models\UserCapability;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;






class PropertyController extends Controller
{
    public function getPropertyTypes($typeFlag)
    {
        $get = Property::with('subProperties')->where('id', $typeFlag)->where('parent_id', 0)->first(); //typeflag : 1=>residential ,2=>commercial
        return $get;
    }

    public function addPropertyDetails(Request $request): mixed
    {
        try {
            $name = $request->input('name');
            $reraRegisteredNumber = $request->input('reraRegisteredNumber');
            $propertyTypeFlag = $request->input('propertyTypeFlag');
            $propertySubTypeFlag = $request->input('propertySubTypeFlag');
            $address = $request->input('address');
            $propertyImg = $request->input('property_img'); //base64
            $description = $request->input('description');
            $userId = $request->input('userId');
            $pincode = $request->input('pincode');
            $stateId = $request->input('state');
            $cityId = $request->input('city');
            $area = $request->input('area');

            $userId = $request->input('userId');
            $actionName = $request->input('userCapabilities');

            $moduleId = Helper::getModuleIdFromAction($actionName);

            $feature = Feature::where('action_name', $actionName)
                ->where('module_id', $moduleId)
                ->first();


            $limitCheck = $this->checkFeatureLimits($userId, $moduleId, $feature, null, 1);
            if ($limitCheck) {
                return $limitCheck; // Return upgrade response if limit is exceeded
            }


            if ($reraRegisteredNumber) {
                // $checkRegisterNumber = UserProperty::where('user_id', $userId)
                //     ->where('rera_registered_no', $reraRegisteredNumber)
                //     ->first();

                $checkRegisterNumber = UserProperty::where('rera_registered_no', $reraRegisteredNumber)
                    ->first();


                if ($checkRegisterNumber) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Property with this registered number already exists.',
                        'propertyId' => null,
                        'propertyName' => null
                    ], 400);
                }
            }


            $userProperty = new UserProperty();
            $userProperty->user_id = $userId;
            $userProperty->property_id = $propertySubTypeFlag;
            $userProperty->name = $name;
            $userProperty->description = $description;
            $userProperty->rera_registered_no = $reraRegisteredNumber;
            $userProperty->address = $address;
            $userProperty->pincode = $pincode;
            $userProperty->state_id = $stateId;
            $userProperty->city_id = $cityId;
            $userProperty->area = $area;
            // $userProperty->property_img = $propertyImg;
            $userProperty->property_step_status = 1;
            $userProperty->save();

            // Handle image saving
            if ($propertyImg) {
                // Define the base properties folder path
                $baseFolderPath = public_path("properties");

                // Check if the base properties folder exists, if not, create it
                if (!file_exists($baseFolderPath)) {
                    mkdir($baseFolderPath, 0777, true);
                }

                // Define user-specific folder path
                $folderPath = public_path("properties/$userId/{$userProperty->id}");

                // Ensure user-specific directory exists
                if (!file_exists($folderPath)) {
                    mkdir($folderPath, 0777, true);
                }

                // Decode base64 image
                $image_parts = explode(";base64,", $propertyImg);
                $image_type_aux = explode("image/", $image_parts[0]);
                $image_type = $image_type_aux[1];
                $image_base64 = base64_decode($image_parts[1]);

                // Create unique file name
                $fileName = uniqid() . '.' . $image_type;

                // Save the image in the defined folder
                $filePath = $folderPath . '/' . $fileName;
                file_put_contents($filePath, $image_base64);

                // Save file path relative to the public folder
                $userProperty->property_img = "properties/$userId/{$userProperty->id}/$fileName";
                $userProperty->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => null,
                'propertyId' => $userProperty->id,
                'propertyName' => $userProperty->name
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addPropertyDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }
    public function getPropertyStatues($statusFlag)
    {
        $get = Status::with('subStatuses')->where('id', $statusFlag)->where('parent_id', 0)->first(); //statusFlag : 1=>Construction Status ,2=>Electrical Status, 3=>Pipline Status
        return $get;
    }


    public function getPropertyAmenities()
    {
        $get = Amenity::get();
        return $get;
    }


    public function getPropertyWingsBasicDetails($pid)
    {

        try {
            // Fetch wings associated with the property
            $fetchWings = WingDetail::with(['unitDetails', 'floorDetails']) // Eager load unit details
                ->where('property_id', $pid)
                ->get();

            // Prepare the response structure
            $response = [
                'building_wings_count' => 0,
                'total_units' => 0,
                'wings' => [],
            ];

            if ($fetchWings->isNotEmpty()) {
                // Update the response structure with actual data
                $response['building_wings_count'] = $fetchWings->count();
                $response['total_units'] = $fetchWings->sum(function ($wing) {
                    return $wing->unitDetails->count(); // Count of units for each wing
                });
                $response['wings'] = $fetchWings->map(function ($wing) {
                    return [
                        'wing_id' => $wing->id,
                        'wing_name' => $wing->name,
                        'total_floors' => $wing->total_floors,
                        'total_units' => $wing->unitDetails->count(), // Count units in this wing
                    ];
                });
            }

            return response()->json($response);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'getPropertyWingsBasicDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            // Return a consistent response structure with an error message
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching wings details',
            ], 400); // Return 500 status code for internal server error
        }
    }


    // public function addWingDetails(Request $request)
    // {
    //     try {
    //         $wingName = $request->input('wingName');
    //         $numberOfFloors = $request->input('numberOfFloors');
    //         $propertyId = $request->input('propertyId');
    //         $wingId = $request->input('wingId');
    //         $sameUnitFlag = $request->input('sameUnitFlag');
    //         $numberOfUnits = $request->input('numberOfUnits');
    //         $floorUnitCounts = $request->input('floorUnitCounts');

    //         if ($sameUnitFlag == 1) {
    //             $checkWing = WingDetail::where('user_property_id', $propertyId)->where('name', $wingName)->first();
    //             if (isset($checkWing)) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Same wing name exist.',
    //                     'wingId' => null,
    //                     'floorUnitDetails' => null
    //                 ], 400);
    //             }
    //             $wingDetail = new WingDetail();
    //             $wingDetail->user_property_id = $propertyId;
    //             $wingDetail->name = $wingName;
    //             $wingDetail->total_floors = $numberOfFloors;
    //             $wingDetail->save();
    //             $floorUnitDetails = [];

    //             for ($i = 1; $i <= $numberOfFloors; $i++) {
    //                 $floorDetail = new FloorDetail();
    //                 $floorDetail->user_property_id = $propertyId;
    //                 $floorDetail->wing_id = $wingDetail->id;
    //                 $floorDetail->total_units = $numberOfUnits;
    //                 $floorDetail->save();

    //                 $unitDetails = [];
    //                 for ($j = 1; $j <= $numberOfUnits; $j++) {
    //                     $unitDetail = new UnitDetail();
    //                     $unitDetail->user_property_id = $propertyId;
    //                     $unitDetail->wing_id = $wingDetail->id;
    //                     $unitDetail->floor_id = $floorDetail->id;
    //                     $unitDetail->save();

    //                     $unitDetails[] = ['unitId' => $unitDetail->id];
    //                 }

    //                 $floorUnitDetails[] = ['floorId' => $floorDetail->id, 'unitDetails' => $unitDetails];
    //             }
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => null,
    //                 'wingId' => $wingDetail->id,
    //                 'floorUnitDetails' => $floorUnitDetails,
    //                 'floorUnitCounts' => null
    //             ], 200);
    //         } elseif ($sameUnitFlag == 2) {
    //             $checkWing = WingDetail::where('user_property_id', $propertyId)->where('name', $wingName)->first();
    //             if (isset($checkWing)) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Same wing name exist.',
    //                     'wingId' => null,
    //                     'floorUnitDetails' => null
    //                 ], 400);
    //             }
    //             $wingDetail = new WingDetail();
    //             $wingDetail->user_property_id = $propertyId;
    //             $wingDetail->name = $wingName;
    //             $wingDetail->total_floors = $numberOfFloors;
    //             $wingDetail->save();
    //             $floorUnitCounts = [];

    //             for ($i = 1; $i <= $numberOfFloors; $i++) {
    //                 $floorDetail = new FloorDetail();
    //                 $floorDetail->user_property_id = $propertyId;
    //                 $floorDetail->wing_id = $wingDetail->id;
    //                 $floorDetail->save();
    //                 $floorUnitCounts[] = ['floorId' => $floorDetail->id, 'unit' => null];
    //             }
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => null,
    //                 'wingId' => $wingDetail->id,
    //                 'floorUnitDetails' => null,
    //                 'floorUnitCounts' => $floorUnitCounts
    //             ], 200);
    //         } else {
    //             $floorUnitDetails = [];
    //             foreach ($floorUnitCounts as $floorUnitCount) {
    //                 $floorDetail = FloorDetail::where('id', $floorUnitCount['floorId'])->update(['total_units' => $floorUnitCount['unit']]);
    //                 $unitDetails = [];
    //                 for ($j = 1; $j <= $floorUnitCount['unit']; $j++) {
    //                     $unitDetail = new UnitDetail();
    //                     $unitDetail->user_property_id = $propertyId;
    //                     $unitDetail->wing_id = $wingId;
    //                     $unitDetail->floor_id = $floorUnitCount['floorId'];
    //                     $unitDetail->save();
    //                     $unitDetails[] = ['unitId' => $unitDetail->id];
    //                 }
    //                 $floorUnitDetails[] = ['floorId' => $floorUnitCount['floorId'], 'unitDetails' => $unitDetails];
    //             }

    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => null,
    //                 'wingId' => $wingId,
    //                 'floorUnitDetails' => $floorUnitDetails,
    //                 'floorUnitCounts' => null
    //             ], 200);
    //         }
    //     } catch (\Exception $e) {
    //         $errorFrom = 'addWingDetails';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::errorLog($errorFrom, $errorMessage, $priority);
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'something went wrong',
    //         ], 400);
    //     }
    // }
    public function addUnitDetails(Request $request)
    {

        try {
            $unitStartNumber = $request->input('unitStartNumber');
            $floorDetailsArray = $request->input('floorUnitDetails');


            foreach ($floorDetailsArray as $index => $floorDetail) {
                $currentStartNumber = (string) $unitStartNumber;
                $unitLength = strlen($currentStartNumber);
                if ($unitLength == 3) {
                    $currentFloorStartNumber = $index * 100 + $unitStartNumber;
                    foreach ($floorDetail['unitDetails'] as $UnitDetail) {
                        UnitDetail::where('id', $UnitDetail['unitId'])->update(['name' => $currentFloorStartNumber, 'unit_size' => $UnitDetail['unitSize']]);
                        $currentFloorStartNumber++;
                    }
                } elseif ($unitLength == 4) {
                    $currentFloorStartNumber = $index * 1000 + $unitStartNumber;
                    foreach ($floorDetail['unitDetails'] as $UnitDetail) {
                        UnitDetail::where('id', $UnitDetail['unitId'])->update(['name' => $currentFloorStartNumber, 'unit_size' => $UnitDetail['unitSize']]);
                        $currentFloorStartNumber++;
                    }
                } else {
                    $currentFloorStartNumber = $unitStartNumber;
                    foreach ($floorDetail['unitDetails'] as $UnitDetail) {
                        UnitDetail::where('id', $UnitDetail['unitId'])->update(['name' => $currentFloorStartNumber, 'unit_size' => $UnitDetail['unitSize']]);
                        $currentFloorStartNumber++;
                    }
                    $unitStartNumber = $currentFloorStartNumber;
                }
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Unit details added successfully.',
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addUnitDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }

    public function getWingDetails($propertyId)
    {
        $WingDetails = WingDetail::with('floorDetails.unitDetails')->where('user_property_id', $propertyId)->get();
        $WingDetails = WingDetail::with('floorDetails.unitDetails')->where('user_property_id', $propertyId)->get();
        return $WingDetails;
    }

    public function getPropertyDetails($pid)
    {
        try {
            if ($pid != 'null') {

                $fetchWings = WingDetail::with(['unitDetails', 'floorDetails']) // Eager load unit details
                    ->where('property_id', $pid)
                    ->get();



                $propertyDetails = UserProperty::where('id', $pid)->first();
                // return $propertyDetails;

                // Fetch the property details along with wings
                $propertyDetails = UserProperty::with([
                    'wingDetails',
                    'property',
                    'unitDetails' => function ($query) {
                        $query->with([
                            'leadCustomerUnits',
                            'paymentTransactions' // Ensure payment transactions are loaded
                        ]);
                    }
                ])->where('id', $pid)->first();

                if ($propertyDetails) {
                    // Check if the property has any wings
                    $wingsflag = $propertyDetails->wingDetails->isNotEmpty() ? 1 : 0;

                    // Add wingsflag to the property details
                    $propertyDetails->wingsflag = $wingsflag;
                    $propertyDetails->property_name = $propertyDetails->property->name ?? null;


                    if ($fetchWings->isNotEmpty()) {
                        // Update the response structure with actual data
                        $propertyDetails->building_wings_count = $fetchWings->count();
                        $propertyDetails->total_units = $fetchWings->sum(function ($wing) {
                            return $wing->unitDetails->count(); // Count of units for each wing
                        });
                    } else {

                        if ($propertyDetails->unitDetails) {
                            $propertyDetails->building_wings_count = 0;
                            // $propertyDetails->total_units=0;
                            $propertyDetails->total_units = $propertyDetails->unitDetails->count();
                        } else {
                            $propertyDetails->building_wings_count = 0;
                            $propertyDetails->total_units = 0;
                        }
                    }



                    // Process unit details to include total_paid_amount
                    $propertyDetails->unitDetails = $propertyDetails->unitDetails->map(function ($unit) {
                        $unitLeads = $unit->leadCustomerUnits;

                        // Calculate total interested leads count
                        $unit->interested_lead_count = $unitLeads->filter(function ($leadCustomerUnits) {
                            return !empty($leadCustomerUnits->interested_lead_id); // Exclude empty or null `interested_lead_id`
                        })->sum(function ($leadCustomerUnits) {
                            return count(explode(',', $leadCustomerUnits->interested_lead_id)); // Count valid IDs
                        });

                        $unit->booking_status = $unitLeads->pluck('booking_status')->first();
                        $totalPaidAmount = 0;

                        // Check payment transactions for this unit
                        $paymentTransactions = $unit->paymentTransactions;

                        if ($paymentTransactions->isNotEmpty()) {
                            // Filter transactions with payment_status = 2
                            $filteredTransactions = $paymentTransactions->where('payment_status', 2);

                            // Get the first transaction
                            $firstTransaction = $filteredTransactions->first();

                            // Add token_amt from the first transaction if it exists
                            if ($firstTransaction && $firstTransaction->token_amt) {
                                $totalPaidAmount += $firstTransaction->token_amt;
                            }

                            // Add next_payable_amt from all transactions
                            foreach ($filteredTransactions as $index => $transaction) {
                                if ($index == 0 && $firstTransaction && $firstTransaction->next_payable_amt) {
                                    $totalPaidAmount += $firstTransaction->next_payable_amt;
                                } elseif ($index > 0 && $transaction->next_payable_amt) {
                                    $totalPaidAmount += $transaction->next_payable_amt;
                                }
                            }
                        }

                        // Add total_paid_amount to the unit details
                        $unit->total_paid_amount = $totalPaidAmount;



                        $allocatedEntities = [];
                        foreach ($unitLeads as $leadCustomerUnits) {
                            // Retrieve and format leads
                            if ($leadCustomerUnits->leads_customers_id) {
                                $leadIds = explode(',', $leadCustomerUnits->leads_customers_id);
                                $allocatedLeads = LeadCustomer::whereIn('id', $leadIds)->get(['id', 'name']);
                                foreach ($allocatedLeads as $lead) {
                                    $allocatedEntities[] = [
                                        'allocated_lead_id' => $lead->id,
                                        'allocated_name' => $lead->name
                                    ];
                                }
                            }
                        }

                        // Assign the aggregated allocated entities array to each unit
                        $unit->allocated_entities = $allocatedEntities;

                        // return $unit;
                    });


                    $letterHead=LetterHead::where('property_id',$pid)->first();
                    $generatedDoc = $letterHead ? $letterHead->file_path : null;
                    $generatedDocId = $letterHead ? $letterHead->id : null;

                    $propertyDetails->generatedDoc = $generatedDoc; // Add to response  
                    $propertyDetails->letter_head_id = $generatedDocId;

                    $propertyDetails['wing_details'] = $fetchWings->map(function ($wing) {
                        return [
                            'id' => $wing->id,
                            'name' => $wing->name,
                            'property_id' => $wing->property_id,
                            'total_floors' => $wing->total_floors,
                            'total_units_in_wing' => $wing->unitDetails->count(), // Count units in this wing
                        ];
                    });


                    unset($propertyDetails->wingDetails);
                    return $propertyDetails;
                } else {
                    return null;
                }
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'getPropertyDetail';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getUserPropertyDetails($uid)
    {

        try {
            if ($uid != 'null') {
                $userProperties = UserProperty::where('user_id', $uid)->get();

                // Get IDs of Commercial and Residential properties (and their subtypes)
                $commercialPropertyIds = Property::where('parent_id', 1)->orWhere('id', 1)->pluck('id')->toArray(); // '1' for Commercial and its subtypes
                $residentialPropertyIds = Property::where('parent_id', 2)->orWhere('id', 2)->pluck('id')->toArray(); // '2' for Residential and its subtypes

                // Separate properties into Commercial and Residential
                $commercialProperties = $userProperties->whereIn('property_id', $commercialPropertyIds)->values(); // Reset keys
                $residentialProperties = $userProperties->whereIn('property_id', $residentialPropertyIds)->values(); // Reset keys
                return response()->json([
                    'commercial_properties' => $commercialProperties,
                    'residential_properties' => $residentialProperties,
                ], 200);
            } else {
                return response()->json([
                    'commercial_properties' => null,
                    'residential_properties' => null,
                ], 200);
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'getUserPropertyDetail';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getStateDetails()
    {
        $getAllState = State::get();
        return $getAllState;
    }

    public function getStateWithCities($id)
    {
        $getStateWithCities = State::with('cities')->where('id', $id)->first();
        return $getStateWithCities;
    }


    public function getAreaWithCities($uid, $cid)
    {
        $getAreaWithStates = UserProperty::where('user_id', $uid)->where('city_id', $cid)
            ->distinct('area')
            ->pluck('area');
        return $getAreaWithStates;
    }

    public function getAllProperties($uid, $stateid, $cityid, $area)
    {
        try {

            if ($uid != 'null') {
                // Base queries for all Commercial and Residential properties
                $commercialQuery = UserProperty::where('user_id', $uid)
                    ->whereIn('property_id', Property::where('parent_id', 1)->pluck('id'));

                $residentialQuery = UserProperty::where('user_id', $uid)
                    ->whereIn('property_id', Property::where('parent_id', 2)->pluck('id'));

                // Apply filters if provided
                if ($stateid != 'null') {
                    $commercialQuery->where('state_id', $stateid);
                    $residentialQuery->where('state_id', $stateid);
                }

                if ($cityid != 'null') {
                    $commercialQuery->where('city_id', $cityid);
                    $residentialQuery->where('city_id', $cityid);
                }

                if ($area != 'null') {
                    $commercialQuery->where('area', 'like', '%' . $area . '%');
                    $residentialQuery->where('area', 'like', '%' . $area . '%');
                }

                // Execute the queries and get the results
                $commercialProperties = $commercialQuery->get();
                $residentialProperties = $residentialQuery->get();

                // Return combined result arrays
                return response()->json([
                    'commercialProperties' => $commercialProperties, // Contains both filtered/unfiltered
                    'residentialProperties' => $residentialProperties // Contains both filtered/unfiltered
                ], 200);
            } else {
                return response()->json([
                    'commercialProperties' => null, // Contains both filtered/unfiltered
                    'residentialProperties' => null // Contains both filtered/unfiltered
                ], 200);
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'filterProperties';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Error filtering properties',
            ], 400);
        }
    }


    public static function checkFeatureLimits($userId, $moduleId, $feature, $data = null, $flag = null)
    {
        // Get the user's plan limit for the feature
        $feature = Feature::where('id', $feature->id)
            ->first();
        $actionName = $feature->action_name;

        $userCapability = UserCapability::where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->where('feature_id', $feature->id)
            ->first();

        $featurename = ModulePlanFeature::where('module_id', $moduleId)
            ->where('plan_id', $userCapability->plan_id)
            ->where('feature_id', $feature->id)
            ->first();

        if (!$userCapability) {
            return response()->json([
                'status' => 'error',
                'message' => 'Feature access not found for the user.',
            ], 200);
        }


        $plandetail = Plan::find($userCapability->plan_id);
        $limit = $userCapability->limit ?? 0; // The limit based on the user's plan


        // $unitCount = UnitDetail::where('user_id', $userId)->count();
        $schemeCount = UserProperty::where('user_id', $userId)->count();
        // echo $schemeCount. $unitCount. $plandetail->id . "hkjh";
        if ($flag == 1) { //for property and  scheme
            // Check unit creation limit for the plan

            // Enforce limits for Basic and Standard plans
            // echo $schemeCount. $updatingunitcount. $plandetail->id;
            if (($plandetail->id == 1 && $schemeCount >= $limit) ||  // Basic: 1 scheme, 100 units
                ($plandetail->id == 2 && $schemeCount >= $limit)
            ) { // Standard: 5 schemes, 500 units
                return response()->json([
                    'status' => 'upgradeplan',
                    'moduleid' => $moduleId,
                    'activeplanname' => $plandetail->name ?? 'Unknown',
                    'buttontext' => $limit . " " . $featurename->name,
                ], 200);
            }
        } else if ($flag == 2) { //for units 
            $updatingunitcount = $data['updatingunitcount'];
            $floor_id = $data['floor_id'];
            $wing_id = $data['wing_id'];




            if (($plandetail->id == 1 && $updatingunitcount > 100) ||  // Basic: 1 scheme, 100 units
                ($plandetail->id == 2 && $updatingunitcount > 500)
            ) { // Standard: 5 schemes, 500 units


                //delete floor if no unit exists due to plan feature
                $unitsExist = UnitDetail::where('floor_id', $floor_id)->exists();
                // If no units exist for the given floor, delete the FloorDetail
                if (!$unitsExist) {
                    $floorsToDelete = FloorDetail::where('wing_id', $wing_id)
                        ->where('id', $floor_id)
                        ->get();

                    // Count the number of floors being deleted
                    $deletedFloorsCount = $floorsToDelete->count();

                    FloorDetail::where('id', $floor_id)
                        ->where('wing_id', $wing_id)
                        ->delete();


                    // if ($deletedFloorsCount > 0) {
                    //     WingDetail::where('id', $wing_id)
                    //         ->decrement('total_floors', $deletedFloorsCount);

                    // }


                    // if ($deletedFloorsCount > 0) {
                    //     // Get the current total_floors value
                    //     $wing = WingDetail::where('id', $wing_id)->first(['total_floors']);

                    //     if ($wing) {
                    //         $newTotalFloors = $wing->total_floors - $deletedFloorsCount;

                    //         // Ensure the value doesn't go below 0
                    //         if ($newTotalFloors < 0) {
                    //             $newTotalFloors = 0;
                    //         }

                    //         // Update the total_floors only if it remains 0 or positive
                    //         WingDetail::where('id', $wing_id)->update(['total_floors' => $newTotalFloors]);
                    //     }
                    // }
                }
                $floorcount = FloorDetail::where('wing_id', $wing_id)->count();
                WingDetail::where('id', $wing_id)->update(['total_floors' => $floorcount]);


                //adding this for buttontext message
                if ($plandetail->id == 1) {
                    $limitvalue = 100;
                } else if ($plandetail->id == 2) {
                    $limitvalue = 500;
                }



                if ($updatingunitcount > $limitvalue) {
                    return response()->json([
                        'status' => 'upgradeplan',
                        'moduleid' => $moduleId,
                        'activeplanname' => $plandetail->name ?? 'Unknown',
                        'buttontext' => $limitvalue . " " . 'units',
                    ], 200);
                }
            }
        }


        // If the limit is not exceeded, proceed with the request
        return null; // Proceed
    }

    public function exportSales($pid)
    {
        try {
            if ($pid != 'null') {
                // Fetch Property Details
                $property = UserProperty::find($pid);

                if (!$property) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Property not found',
                    ], 404);
                }

                $propertyData = [
                    'property_name' => $property->name, // Corrected property name
                    'total_floors' => 0, // Calculated dynamically
                    'wings' => [] // Contains all wings
                ];

                // Fetch Wing Details
                $wings = WingDetail::where('property_id', $pid)->get();

                foreach ($wings as $wing) {
                    $wingData = [
                        'wingname' => $wing->name,
                        'total_floors' => 0, // Calculated dynamically
                        'floors' => [] // Contains all floors for this wing
                    ];

                    $floorCounter = 1;
                    // Fetch Floor Details
                    $floors = FloorDetail::where('property_id', $pid)
                        ->where('wing_id', $wing->id)
                        ->get();

                    foreach ($floors as $floor) {
                        $floorData = [
                            'floor_name' => $floorCounter, // Use actual floor name if available
                            'total_units' => 0, // Calculated dynamically
                            'total_booked' => 0, // Calculated dynamically
                            'interested_units' => 0 // Calculated dynamically
                        ];

                        // Fetch Unit Details
                        $units = UnitDetail::where('property_id', $pid)
                            ->where('wing_id', $wing->id)
                            ->where('floor_id', $floor->id)
                            ->get();

                        foreach ($units as $unit) {
                            // Count Interested Units
                            $interestedUnitsCount = LeadCustomerUnit::where('unit_id', $unit->id)
                                ->whereNotNull('interested_lead_id')
                                ->count();

                            // Count Booked Units
                            $bookedUnitsCount = LeadCustomerUnit::where('unit_id', $unit->id)
                                ->whereNotNull('leads_customers_id')
                                ->count();

                            $floorData['total_units']++;
                            $floorData['total_booked'] += $bookedUnitsCount;
                            $floorData['interested_units'] += $interestedUnitsCount;
                        }

                        $wingData['total_floors']++;
                        $wingData['floors'][] = $floorData;

                        $floorCounter++; // Increment floor counter for this wing
                    }

                    $propertyData['total_floors'] += $wingData['total_floors'];
                    $propertyData['wings'][] = $wingData; // Append wing data to property
                }

                return response()->json($propertyData);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid property ID',
                ], 400);
            }
        } catch (\Exception $e) {
            $errorFrom = 'exportSales';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    public function getSalesBasicDetails($uid, $pid)
    {
        try {
            $units = UnitDetail::where('property_id', $pid)
                ->where('user_id', $uid)
                ->leftJoin('leads_customers_unit', 'unit_details.id', '=', 'leads_customers_unit.unit_id')
                ->select(
                    'unit_details.id as unit_id',
                    'unit_details.wing_id',
                    'leads_customers_unit.interested_lead_id',
                    'leads_customers_unit.leads_customers_id',
                    'leads_customers_unit.booking_status'
                )
                ->get();


            // Payment Pending
            // Fetch payment pending separately based on leads_customers_unit
            $paymentPendingCount = LeadCustomerUnit::whereIn('unit_id', $units->pluck('unit_id'))
                ->where(function ($query) {
                    $query->where('booking_status', 4)
                        ->Where('booking_status', '!=', 3);
                })
                ->count();

            $bookedUnit = LeadCustomerUnit::whereIn('unit_id', $units->pluck('unit_id'))
                ->where(function ($query) {
                    $query->where('booking_status', 3);
                })
                ->count();

            $interestedLeads = LeadCustomerUnit::whereIn('unit_id', $units->pluck('unit_id'))
                ->where(function ($query) {
                    $query->where('booking_status', 2);
                })
                ->count();

            // Initialize counters for each scheme
            $data = [
                'available_units' => 0,
                'interested_leads' => $interestedLeads,
                'booked_units' => $bookedUnit,
                'payment_pending' => $paymentPendingCount,
            ];

            // Calculate counts
            foreach ($units as $unit) {
                // Available Units
                if (is_null($unit->leads_customers_id) && is_null($unit->interested_lead_id)) {
                    $data['available_units']++;
                }
            }
            return response()->json($data);
        } catch (\Exception $e) {
            $errorFrom = 'getSalesBasicDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    public function getRecentInterestedLeads($uid, $pid)
    {
        try {
            $unitIds = UnitDetail::where('property_id', $pid)
                ->where('user_id', $uid)
                ->pluck('id'); // Retrieve the unit IDs only


    //             $leadCustomerUnitIds = LeadCustomerUnit::whereIn('unit_id', $unitIds)->pluck('id');

    //             $latestEntries = LeadCustomerUnitData::whereIn('leads_customers_unit_id', $leadCustomerUnitIds)
    //                 ->with([
    //                     'leadCustomerUnit.unit' => function ($query) {
    //                         $query->select('id', 'name', 'wing_id');
    //                     },
    //                     'leadCustomerUnit.unit.wingDetail' => function ($query) {
    //                         $query->select('id', 'name');
    //                     },
    //                     'leadCustomer' => function ($query) {
    //                         $query->select('id', 'name', 'contact_no', 'source_id');
    //                     },
    //                     'leadCustomer.leadSource' => function ($query) {
    //                         $query->select('id', 'name');
    //                     }
    //                 ])
    //                 ->orderBy('id', 'desc')
    //                 ->take(5)
    //                 ->get();
    // return  $latestEntries;
            // Get the latest 5 entries with the required details
            $latestEntries = LeadCustomerUnitData::whereHas('leadCustomerUnit', function ($query) use ($unitIds) {
                $query->whereIn('unit_id', $unitIds);
            })
                ->with([
                    'leadCustomerUnit.unit' => function ($query) {
                        $query->select('id', 'name', 'wing_id');
                    },
                    'leadCustomerUnit.unit.wingDetail' => function ($query) {
                        $query->select('id', 'name');
                    },
                    'leadCustomer' => function ($query) {
                        $query->select('id', 'name', 'contact_no', 'source_id');
                    },
                    'leadCustomer.leadSource' => function ($query) {
                        $query->select('id', 'name');
                    }
                ])
                ->orderBy('id', 'desc') // Sort by latest
                ->take(5) // Limit to 5 entries
                ->get();

            // Format the response
            $response = $latestEntries->map(function ($entry) {
                return [
                    'lead_customer_name' => $entry->leadCustomer->name ?? null,
                    'contact_number' => $entry->leadCustomer->contact_no ?? null,
                    'source_name' => $entry->leadCustomer->leadSource->name ?? null,
                    'wing_name' => $entry->leadCustomerUnit->unit->wingDetail->name ?? null,
                    'unit_name' => $entry->leadCustomerUnit->unit->name ?? null,
                ];
            });

            return response()->json($response);
            // return response()->json([
            //     'status' => 'success',
            //     'data' => $latestEntries
            // ]);
        } catch (\Exception $e) {
            $errorFrom = 'getRecentInterestedLeads';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getRecentCustomers($uid, $pid)
    {
        try {
            $recentCustomers = LeadCustomer::where('property_id', $pid)
                ->where('user_id', $uid)
                ->where('entity_type', 2) // Filter by entity type 2 (customers)
                ->orderBy('updated_at', 'desc') // Sort by the updated_at field
                ->take(5) // Limit to the latest 5 customers
                ->get(['id', 'name', 'email', 'contact_no', 'source_id', 'updated_at']); // Select required fields

            // Include the source name in the response using eager loading
            // Map through customers to fetch required details
            $response = $recentCustomers->map(function ($customer) {
                // Find the related lead_customer_unit entry
                $leadCustomerUnit = LeadCustomerUnit::where('leads_customers_id', $customer->id)->first();

                if (!$leadCustomerUnit) {
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'contact_no' => $customer->contact_no,
                        'wing_name' => null,
                        'unit_name' => null,
                        'source_name' => $customer->source->name ?? null,
                        'total_amount' => 0,
                        'amount_received' => 0,
                        'created_at' => $customer->created_at,
                        'updated_at' => $customer->updated_at,
                    ];
                }

                // Find the related unit details
                $unitDetails = UnitDetail::find($leadCustomerUnit->unit_id);

                // Get payment transactions
                $paymentTransactions = PaymentTransaction::where('leads_customers_id', $customer->id)
                    ->where('unit_id', $leadCustomerUnit->unit_id)
                    ->where('payment_status',2)
                    ->get();

                $totalAmount = $unitDetails->price ?? 0; // Unit's total price
                $tokenAmount = $paymentTransactions->first()->token_amt ?? 0; // First token amount
                $nextPayableSum = $paymentTransactions->sum('next_payable_amt'); // Sum of all next payments

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'contact_no' => $customer->contact_no,
                    'wing_name' => $unitDetails->wingDetail->name ?? null, // Wing name
                    'unit_name' => $unitDetails->name ?? null, // Unit name
                    'source_name' => $customer->leadSource->name ?? null, // Source name
                    'total_amount' => $totalAmount,
                    'amount_received' => $tokenAmount + $nextPayableSum,
                    'created_at' => $customer->created_at,
                    'updated_at' => $customer->updated_at,
                ];
            });

            return response()->json($response);
        } catch (\Exception $e) {
            $errorFrom = 'getRecentCustomers';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getPaymentTypeSummary($userId, $propertyId)
    {
        try {
            // Fetch payment type data and aggregate counts
            $paymentSummary = PaymentTransaction::where('property_id', $propertyId)
                ->select('payment_type', DB::raw('COUNT(*) as count'))
                ->groupBy('payment_type')
                ->get();

            // Map payment types from the static table
            $paymentTypes = [
                1 => 'Cheque',
                2 => 'RTGS/NEFT',
                3 => 'Online Transfer',
                4 => 'Cash',
                5 => 'Other',
            ];

            // Prepare labels and series data
            $labels = [];
            $series = [];

            foreach ($paymentTypes as $id => $name) {
                $labels[] = $name;

                // Find count for this payment type, default to 0 if not found
                $count = $paymentSummary->where('payment_type', $id)->first()->count ?? 0;
                $series[] = $count;
            }

            // Build JSON response
            return response()->json([
                'labels' => $labels,
                'series' => $series,
            ]);
        } catch (\Exception $e) {
            $errorFrom = 'getPaymentTypeSummary';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching payment type summary',
            ], 400);
        }
    }
    public function getSalesAnalyticsReport($uid, $pid, $flag)
    {
        try {
            $currentYear = now()->year;

            if ($flag == 1) { // Monthly report for current year
                $categories = [];
                $series = [];

                // Generate month names for categories
                for ($month = 1; $month <= 12; $month++) {
                    $categories[] = \Carbon\Carbon::create($currentYear, $month, 1)->format('M Y');
                }

                // Fetch monthly revenue
                $monthlyRevenue = PaymentTransaction::selectRaw('
                    MONTH(booking_date) as month,
                    SUM(CASE WHEN id = first_transaction_id THEN COALESCE(token_amt, 0) ELSE 0 END) 
                    + SUM(COALESCE(next_payable_amt, 0)) AS total_revenue
                ')
                    ->where('property_id', $pid)
                    ->where('payment_status',2)
                    ->whereYear('booking_date', $currentYear)
                    ->withCasts(['id' => 'int'])  // Ensures ID is correctly casted
                    ->groupBy(DB::raw('MONTH(booking_date)'))
                    ->joinSub(
                        PaymentTransaction::selectRaw('unit_id, MIN(id) as first_transaction_id')
                            ->where('property_id', $pid)
                            ->whereYear('booking_date', $currentYear)
                            ->groupBy('unit_id'),
                        'first_transactions',
                        'payment_transactions.unit_id',
                        '=',
                        'first_transactions.unit_id'
                    )
                    ->pluck('total_revenue', 'month');

                // Populate series data
                foreach (range(1, 12) as $month) {
                    $series[] = $monthlyRevenue[$month] ?? 0;
                }

                return response()->json([
                    'categories' => $categories,
                    'series' => $series,
                ]);
            } elseif ($flag == 2) { // Yearly report for the next 10-15 years
                $categories = [];
                $series = [];

                // Generate categories for next 15 years
                $years = range($currentYear, $currentYear + 14);
                foreach ($years as $year) {
                    $categories[] = (string) $year; // Convert year to string
                }

                // Fetch yearly revenue
                $yearlyRevenue = PaymentTransaction::selectRaw('
                        YEAR(booking_date) as year,
                        SUM(CASE WHEN id = first_transaction_id THEN COALESCE(token_amt, 0) ELSE 0 END) 
                        + SUM(COALESCE(next_payable_amt, 0)) AS total_revenue
                    ')
                    ->where('property_id', $pid)
                    ->where('payment_status',2)
                    ->whereYear('booking_date', $currentYear) // Optional if you want to filter by the current year
                    ->withCasts(['id' => 'int'])  // Ensures ID is correctly casted
                    ->groupBy(DB::raw('YEAR(booking_date)'))
                    ->joinSub(
                        PaymentTransaction::selectRaw('unit_id, MIN(id) as first_transaction_id')
                            ->where('property_id', $pid)
                            ->whereYear('booking_date', $currentYear) // Optional if you want to filter by the current year
                            ->groupBy('unit_id'),
                        'first_transactions',
                        'payment_transactions.unit_id',
                        '=',
                        'first_transactions.unit_id'
                    )
                    ->pluck('total_revenue', 'year');

                // Populate series data
                foreach ($years as $year) {
                    $series[] = $yearlyRevenue[$year] ?? 0;
                }

                return response()->json([
                    'categories' => $categories,
                    'series' => $series,
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid flag provided',
                ], 400);
            }
        } catch (\Exception $e) {
            $errorFrom = 'getSalesAnalyticsReport';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching sales analytics report',
            ], 400);
        }
    }
}
