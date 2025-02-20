<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FloorDetail;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\UnitDetail;
use App\Models\UserProperty;
use App\Models\WingDetail;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\LeadCustomer;
use App\Models\LeadCustomerUnit;
use App\Models\LeadCustomerUnitData;
use App\Models\PaymentTransaction;
use App\Models\State;
use Exception;
use Illuminate\Support\Facades\Log;




class VillaBunglowController extends Controller
{



    public function addVillaBunglowDetails(Request $request)
    {

        try {
            // Get the incoming request data
            $propertyId = $request->input('propertyId');
            $totalUnits = $request->input('totalUnits');
            $unitSize = $request->input('unitSize', null); // Default to null if not provided

            // Retrieve the last unit for this property, ordered by ID
            $lastUnit = UnitDetail::where('property_id', $propertyId)
                ->orderBy('id', 'desc')
                ->first();

            $units = [];
            if ($lastUnit) {
                // Extract the last unit's name and determine the starting point
                $lastUnitName = $lastUnit->name;

                if (ctype_digit($lastUnitName)) {
                    // If the last unit is numeric, continue incrementing numbers
                    $startingUnitName = (int)$lastUnitName + 1;
                } elseif (ctype_alpha($lastUnitName)) {
                    // If the last unit is alphabetical, continue incrementing letters
                    $isLowercase = ctype_lower($lastUnitName);
                    $startingUnitName = chr(ord(strtoupper($lastUnitName)) + 1);
                    if ($startingUnitName > 'Z') {
                        $startingUnitName = 'A'; // Wrap around to 'A' after 'Z'
                    }
                    if ($isLowercase) {
                        $startingUnitName = strtolower($startingUnitName); // Preserve lowercase
                    }
                } else {
                    // Invalid format, return an error
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid last unit name format. The series must be numeric or alphabetical.',
                    ], 200);
                }
            } else {
                // If no units exist, start series with 101
                $startingUnitName = 101;
            }

            // Create new units starting from the determined point
            for ($i = 0; $i < $totalUnits; $i++) {
                if (ctype_digit((string)$startingUnitName)) {
                    // Increment numerically
                    $unitName = (string)($startingUnitName + $i);
                } else {
                    // Increment alphabetically with wrapping
                    $currentIndex = ord(strtoupper($startingUnitName)) + $i;
                    if ($currentIndex > ord('Z')) {
                        $currentIndex = ord('A') + ($currentIndex - ord('Z') - 1);
                    }
                    $unitName = chr($currentIndex);
                    if (ctype_lower($startingUnitName)) {
                        $unitName = strtolower($unitName); // Preserve lowercase
                    }
                }

                $units[] = [
                    'property_id' => $propertyId,
                    'name' => $unitName,
                    'square_feet' => ($unitSize !== null && $unitSize !== '') ? $unitSize : null, // Assign unitSize if available
                    'wing_id' => null, // Wing ID is null as per the requirements
                    'floor_id' => null, // Floor ID is null as per the requirements
                    'status_id' => 1, // Default status ID
                    'price' => null, // Default price
                ];
            }

            // Insert units data into the database
            UnitDetail::insert($units);

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Data added successfully',
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addVillaBunglowDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }
}
