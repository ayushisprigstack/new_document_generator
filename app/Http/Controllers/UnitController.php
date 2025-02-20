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




class UnitController extends Controller
{

    public function getAllUnitLeadDetails($uid)
    {
        try {
            if ($uid !== 'null') {
                // Fetch the unit based on the provided uid
                $unit = UnitDetail::with('leadUnits.allottedLead') // Eager load lead units and their allotted leads
                    ->find($uid);

                // Check if the unit exists
                if (!$unit) {
                    return null;
                }

                // Prepare the response data
                $leadDetails = $unit->leadUnits->flatMap(function ($leadUnit) {
                    // Get the interested lead IDs and split them into an array
                    $interestedLeadIds = explode(',', $leadUnit->interested_lead_id);

                    // Fetch details for each interested lead
                    $leads = Lead::whereIn('id', $interestedLeadIds)
                        ->get()
                        ->map(function ($lead) use ($leadUnit) {
                            return [
                                'id' => $lead->id,
                                'name' => $lead->name,
                                'email' => $lead->email,
                                'contact_no' => $lead->contact_no,
                                'booking_status' => $leadUnit->booking_status,
                                // Add any additional lead fields you need
                            ];
                        });

                    return $leads; // Return the detailed leads for this lead unit
                });

                return $leadDetails;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getAllUnitLeadDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getLeadNames($pid)
    {

        try {
            if ($pid != 'null') {
                $allLeads = LeadCustomer::where('property_id', $pid)->where('entity_type', 1)->get();

                return $allLeads;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getLeadNameDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }
    public function getLeadCustomerNames($pid)
    {
        try {
            if ($pid != 'null') {
                $allLeadsCustomers = LeadCustomer::where('property_id', $pid)->get();

                return $allLeadsCustomers;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getLeadNameDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function addLeadsAttachingWithUnits(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'unit_id' => 'required|integer',
                'lead_id' => 'required|integer',
                'booking_date' => 'nullable|date',
                'next_payable_amt' => 'nullable|numeric',
                'payment_due_date' => 'nullable|date',
                'token_amt' => 'nullable|numeric'
            ]);

            // Initialize variables
            $unitId = $request->input('unit_id');
            $leadId = $request->input('lead_id');
            $bookingDate = $request->input('booking_date');
            $nextPayableAmt = $request->input('next_payable_amt') == 0 ? null : $request->input('next_payable_amt');
            $paymentDueDate = $request->input('payment_due_date');
            $tokenAmt = $request->input('token_amt') == 0 ? null : $request->input('token_amt');

            // First, add the entry to `lead_units`
            $leadUnit = LeadCustomerUnit::create([
                'lead_id' => $leadId,
                'unit_id' => $unitId,
                'booking_status' => 0, // Default status, update as needed
            ]);

            $unit = UnitDetail::find($unitId);
            if (!$unit) {
                throw new \Exception("Unit not found.");
            }

            // If any of the payment fields are not null, add entry to `payment_transactions`
            if ($bookingDate || $nextPayableAmt || $paymentDueDate || $tokenAmt) {
                // Fetch property_id based on the unit

                PaymentTransaction::create([
                    'unit_id' => $unitId,
                    'property_id' => $unit->property_id, // Assuming unit has property_id
                    'booking_date' => $bookingDate,
                    'payment_due_date' => $paymentDueDate,
                    'token_amt' => $tokenAmt,
                    'amount' => $nextPayableAmt,
                    'payment_type' => 0, // You can modify this as needed
                    'transaction_notes' => 'New transaction', // Example notes
                ]);
                $unit->status_id = 3;
            } else {
                // Update unit status to 1 (no payment transaction)
                $unit->status_id = 1;
            }

            $unit->save();

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Lead and unit information saved successfully',
            ], 201);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addLeadsAttachingWithUnits';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving the data',
            ], 400);
        }
    }


    public function addInterestedLeads(Request $request)
    {
        try {
            $unit = UnitDetail::with('leadCustomerUnits')->where('id', $request->unit_id)->first();

            // Check if the unit exists
            if (!$unit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found.',
                ], 200);
            }

            // Check if the unit is associated with the provided property_id
            if ($unit->property_id != $request->property_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This unit is not associated with the specified property.',
                ], 200);
            }

            // Initialize variables
            $existingLeadIds = [];
            $existingLeadUnit = $unit->leadCustomerUnits->first();

            // If no existing lead unit, create a new one
            if (!$existingLeadUnit) {
                $newLeadUnit = new LeadCustomerUnit();
                $newLeadUnit->interested_lead_id = ''; // Will be updated later
                $newLeadUnit->unit_id = $request->unit_id;
                $newLeadUnit->booking_status = 2;
                $newLeadUnit->save();

                // Use $newLeadUnit as the current lead unit
                $leadUnitId = $newLeadUnit->id;
            } else {
                // If there is an existing lead unit, use its ID
                $leadUnitId = $existingLeadUnit->id;
                $existingLeadIds = explode(',', $existingLeadUnit->interested_lead_id);
                $existingLeadIds = array_map('intval', $existingLeadIds);
            }

            // Iterate through the leads_array from the request
            foreach ($request->leads_array as $lead) {
                $leadId = $lead['lead_id'];
                $budget = $lead['budget'];

                // Add lead ID if not already present
                if (!in_array($leadId, $existingLeadIds)) {
                    $existingLeadIds[] = $leadId;
                }

                // Check if there's an existing record in LeadUnitData
                $leadUnitData = LeadCustomerUnitData::where('leads_customers_unit_id', $leadUnitId)
                    ->where('leads_customers_id', $leadId)
                    ->first();

                if ($leadUnitData) {
                    // Update budget if different
                    if ($leadUnitData->budget != $budget) {
                        $leadUnitData->budget = $budget;
                        $leadUnitData->save();
                    }
                } else {
                    // Create new LeadUnitData entry if not exists
                    LeadCustomerUnitData::create([
                        'leads_customers_unit_id' => $leadUnitId,
                        'leads_customers_id' => $leadId,
                        'budget' => $budget,
                    ]);
                }
            }

            // Convert updated lead IDs back to a comma-separated string
            $updatedInterestedLeadIds = implode(',', $existingLeadIds);

            // Update the interested_lead_id in the lead unit
            if ($existingLeadUnit) {
                $existingLeadUnit->interested_lead_id = $updatedInterestedLeadIds;
                $existingLeadUnit->save();
            } else {
                $newLeadUnit->interested_lead_id = $updatedInterestedLeadIds;
                $newLeadUnit->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Interested leads updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addInterestedLeads';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving the data',
            ], 400);
        }
    }



    public function getUnitInterestedLeads($uid)
    {
        try {
            if ($uid != 'null') {

                // Fetch lead unit by unit ID
                $leadUnit = LeadCustomerUnit::where('unit_id', $uid)->first();


                // Check if the lead unit exists
                if (!$leadUnit) {
                    return []; // Return an empty array if no lead unit is found
                }


                // Get the interested lead IDs from the lead unit
                $interestedLeadIds = explode(',', $leadUnit->interested_lead_id); // Convert comma-separated string to array

                // Fetch details of interested leads
                $interestedLeads = LeadCustomer::whereIn('id', $interestedLeadIds)->get();


                // Fetch budget details from LeadUnitData based on lead_unit_id
                $budgets = LeadCustomerUnitData::where('leads_customers_unit_id', $leadUnit->id)
                    ->whereIn('leads_customers_id', $interestedLeadIds)
                    ->get()
                    ->keyBy('leads_customers_id'); // Index by lead_id for easy access

                // Map the budget to each lead
                $leadsWithBudgets = $interestedLeads->map(function ($lead) use ($budgets) {
                    $lead->budget = $budgets->get($lead->id)->budget ?? null; // Assign budget if available
                    return $lead;
                });

                // Return the leads with budget details
                return $leadsWithBudgets->isEmpty() ? [] : $leadsWithBudgets->toArray();
            } else {
                return []; // Return an empty array if uid is 'null'
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getUnitInterestedLeads';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    public function getUnitsBasedOnWing($wid)
    {
        try {
            // Fetch the wing details along with its associated units
            if ($wid) {
                // Extract the unit IDs and names
                $units = UnitDetail::where('wing_id', $wid)
                    ->select('id', 'name') // Select only the unit ID and name
                    ->get();
            } else {
                $units = []; // Return an empty array if the wing is not found
            }

            // Return the units directly as an array
            return response()->json($units);
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getUnitsBasedOnWing';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function sendReminderToBookedPerson($uid)
    {
        try {
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'sendReminderOfDueDate';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function updateUnitSeriesNumber(Request $request)
    {
        try {
            $propertyId = $request->input('propertyId');
            $wingId = $request->input('wingId');
            $floorDetails = $request->input('floordetails');
            $unitDetails = $request->input('unitDetails');
            $flag = $request->input('flag'); //1 scheme with floors and , 2 scheme with only units


            if ($flag == 1) {
                // Extract the starting series base and unit number from the first floor and unit
                $startingSeries = $floorDetails[0]['unit_details'][0]['name'];
                $seriesBase = preg_replace('/\d+$/', '', $startingSeries);
                $unitIndexStart = (int) filter_var($startingSeries, FILTER_SANITIZE_NUMBER_INT);

                // Determine the unit gap based on the first unit number
                $unitGap = $this->calculateUnitGap($unitIndexStart);

                // Retrieve all floors for this wing and property, to update all dynamic floors
                $allFloors = FloorDetail::where('property_id', $propertyId)
                    ->where('wing_id', $wingId)
                    ->get(); // Fetch all floors for the given property and wing

                // Validate and correct the series pattern for all floors
                foreach ($allFloors as $floorIndex => $floor) {
                    // Find corresponding floor details from the request (if any)
                    $floorFromRequest = isset($floorDetails[$floorIndex]) ? $floorDetails[$floorIndex] : null;
                    $expectedFloorStart = $unitIndexStart + ($floorIndex * $unitGap);

                    // Check if the floor series matches the expected start
                    $firstUnitOnFloor = $floorFromRequest ? $floorFromRequest['unit_details'][0]['name'] : null;
                    if ($firstUnitOnFloor) {
                        $firstUnitNum = (int) filter_var($firstUnitOnFloor, FILTER_SANITIZE_NUMBER_INT);
                        if ($firstUnitNum !== $expectedFloorStart) {
                            return response()->json([
                                'status' => 'error',
                                'message' => "Invalid starting unit on Floor " . ($floorIndex + 1) . ". Expected '{$seriesBase}{$expectedFloorStart}', got '{$firstUnitOnFloor}'.",
                            ], 200);
                        }
                    }

                    // Validate unit increment on the floor (if floor details are provided)
                    if ($floorFromRequest) {
                        foreach ($floorFromRequest['unit_details'] as $unitIndex => $unit) {
                            $currentUnitNum = (int) filter_var($unit['name'], FILTER_SANITIZE_NUMBER_INT);
                            $expectedUnitNum = $expectedFloorStart + $unitIndex;

                            if ($currentUnitNum !== $expectedUnitNum) {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => "Invalid unit number on Floor " . ($floorIndex + 1) . ". Expected '{$seriesBase}{$expectedUnitNum}', got '{$unit['name']}'.",
                                ], 200);
                            }
                        }
                    }
                }

                // Update all units for all floors in the wing, regardless of request details
                foreach ($allFloors as $floorIndex => $floor) {
                    $expectedFloorStart = $unitIndexStart + ($floorIndex * $unitGap);
                    $floorFromRequest = isset($floorDetails[$floorIndex]) ? $floorDetails[$floorIndex] : null;

                    // Update all units for this floor
                    $units = UnitDetail::where('floor_id', $floor->id)->get();
                    foreach ($units as $unitIndex => $unit) {
                        // Calculate the new unit name based on the floor index and unit index
                        $newUnitName = $seriesBase . ($expectedFloorStart + $unitIndex);
                        UnitDetail::where('id', $unit->id)
                            ->update(['name' => $newUnitName, 'updated_at' => now()]);
                    }

                    // If floor details are provided in the request, update the units in the request
                    if ($floorFromRequest) {
                        foreach ($floorFromRequest['unit_details'] as $unitIndex => $unit) {
                            $newUnitName = $seriesBase . ($expectedFloorStart + $unitIndex);
                            UnitDetail::where('id', $unit['unitId'])
                                ->update(['name' => $newUnitName, 'updated_at' => now()]);
                        }
                    }
                }
            } else if ($flag == 2) {
                // Extract the starting unit details
                $unitDetails = $request->input('unitDetails');

                // Determine the starting series base and number
                $startingSeries = $unitDetails[0]['name'];
                
                // Check if the input is valid (either all letters or all digits)
                if (ctype_alpha($startingSeries)) {
                    $isAlphabetical = true;
                    $isLowercase = ctype_lower($startingSeries); // Check if the input is lowercase
                    $seriesBase = $startingSeries;
                    $unitIndexStart = ord(strtoupper($startingSeries)); // Convert to uppercase for consistency
                } elseif (ctype_digit($startingSeries)) {
                    $isAlphabetical = false;
                    $seriesBase = '';
                    $unitIndexStart = (int)$startingSeries;
                } else {
                    // Invalid format (e.g., `A1` or `10G1`)
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid unit name format. Please use either pure letters (A, B, ...) or numbers (1, 2, ...).',
                    ], 200);
                }
                
                // Retrieve all units for this property
                $units = UnitDetail::where('property_id', $propertyId)->orderBy('id')->get();
                
                // Update unit names sequentially
                foreach ($units as $index => $unit) {
                    if ($isAlphabetical) {
                        // Increment alphabetically with wrapping
                        $newUnitIndex = ($unitIndexStart + $index - ord('A')) % 26; // Wrap after 26 letters
                        $newUnitName = chr(ord('A') + $newUnitIndex);
                        if ($isLowercase) {
                            $newUnitName = strtolower($newUnitName); // Convert back to lowercase if necessary
                        }
                    } else {
                        // Increment numerically
                        $newUnitName = $seriesBase . ($unitIndexStart + $index);
                    }
                
                    // Update the unit name in the database
                    UnitDetail::where('id', $unit->id)
                        ->update(['name' => $newUnitName, 'updated_at' => now()]);
                }
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Unit series numbers updated successfully.',
                ], 200);
                
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Unit series numbers updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            Helper::errorLog('updateUnitSeriesNumber', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }

    public function updateUnitSeriesNumbermain(Request $request)
    {
        try {
            $propertyId = $request->input('propertyId');
            $wingId = $request->input('wingId');
            $floorDetails = $request->input('floordetails');

            // Extract the starting series base and unit number from the first floor and unit
            $startingSeries = $floorDetails[0]['unit_details'][0]['name'];
            $seriesBase = preg_replace('/\d+$/', '', $startingSeries);
            $unitIndexStart = (int) filter_var($startingSeries, FILTER_SANITIZE_NUMBER_INT);

            // Determine the unit gap based on the first unit number
            $unitGap = $this->calculateUnitGap($unitIndexStart);

            // Retrieve all floors for this wing and property, to update all dynamic floors
            $allFloors = FloorDetail::where('property_id', $propertyId)
                ->where('wing_id', $wingId)
                ->get(); // Fetch all floors for the given property and wing

            // Validate and correct the series pattern for all floors
            foreach ($allFloors as $floorIndex => $floor) {
                // Find corresponding floor details from the request (if any)
                $floorFromRequest = isset($floorDetails[$floorIndex]) ? $floorDetails[$floorIndex] : null;
                $expectedFloorStart = $unitIndexStart + ($floorIndex * $unitGap);

                // Check if the floor series matches the expected start
                $firstUnitOnFloor = $floorFromRequest ? $floorFromRequest['unit_details'][0]['name'] : null;
                if ($firstUnitOnFloor) {
                    $firstUnitNum = (int) filter_var($firstUnitOnFloor, FILTER_SANITIZE_NUMBER_INT);
                    if ($firstUnitNum !== $expectedFloorStart) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "Invalid starting unit on Floor " . ($floorIndex + 1) . ". Expected '{$seriesBase}{$expectedFloorStart}', got '{$firstUnitOnFloor}'.",
                        ], 200);
                    }
                }

                // Validate unit increment on the floor (if floor details are provided)
                if ($floorFromRequest) {
                    foreach ($floorFromRequest['unit_details'] as $unitIndex => $unit) {
                        $currentUnitNum = (int) filter_var($unit['name'], FILTER_SANITIZE_NUMBER_INT);
                        $expectedUnitNum = $expectedFloorStart + $unitIndex;

                        if ($currentUnitNum !== $expectedUnitNum) {
                            return response()->json([
                                'status' => 'error',
                                'message' => "Invalid unit number on Floor " . ($floorIndex + 1) . ". Expected '{$seriesBase}{$expectedUnitNum}', got '{$unit['name']}'.",
                            ], 200);
                        }
                    }
                }
            }

            // Update all units for all floors in the wing, regardless of request details
            foreach ($allFloors as $floorIndex => $floor) {
                $expectedFloorStart = $unitIndexStart + ($floorIndex * $unitGap);
                $floorFromRequest = isset($floorDetails[$floorIndex]) ? $floorDetails[$floorIndex] : null;

                // Update all units for this floor
                $units = UnitDetail::where('floor_id', $floor->id)->get();
                foreach ($units as $unitIndex => $unit) {
                    // Calculate the new unit name based on the floor index and unit index
                    $newUnitName = $seriesBase . ($expectedFloorStart + $unitIndex);
                    UnitDetail::where('id', $unit->id)
                        ->update(['name' => $newUnitName, 'updated_at' => now()]);
                }

                // If floor details are provided in the request, update the units in the request
                if ($floorFromRequest) {
                    foreach ($floorFromRequest['unit_details'] as $unitIndex => $unit) {
                        $newUnitName = $seriesBase . ($expectedFloorStart + $unitIndex);
                        UnitDetail::where('id', $unit['unitId'])
                            ->update(['name' => $newUnitName, 'updated_at' => now()]);
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Unit series numbers updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            Helper::errorLog('updateUnitSeriesNumber', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }

    /**
     * Helper function to calculate the unit gap based on unit length.
     */
    private function calculateUnitGap($unitNumber)
    {
        $unitLength = strlen((string) $unitNumber);

        switch ($unitLength) {
            case 1:
            case 2:
                return 10;
            case 3:
                return 100;
            case 4:
                return 1000;
            default:
                throw new \Exception("Invalid unit number length.");
        }
    }


    // private function validateIncrement($unitDetails, $unitIndexStart)
    // {
    //     foreach ($unitDetails as $index => $unitDetail) {
    //         $unitName = $unitDetail['name'];
    //         $expectedUnitName = (string) ($unitIndexStart + $index); // Expected name should be the incremented number
    //         echo $expectedUnitName;
    //         // If the unit name doesn't match the expected name, return an error
    //         if ($unitName != $expectedUnitName) {
    //             return response()->json([
    //                                     'status' => 'error',
    //                                     'message' => 'Please enter proper series names with proper increment.',
    //                                 ], 200);
    //             // throw new Exception('Please enter proper series names with proper increment.');
    //         }
    //     }
    // }

    // private function validateUnitSeries($floorDetails, $seriesBase, $unitIndexStart)
    // {
    //     // Get the expected series starting number from the first floor, first unit
    //     // Determine the length of the number part of the unit name from the first unit
    //     foreach ($floorDetails as $floorDetail) {
    //         $unitDetails = $floorDetail['unit_details'];

    //         // Validate the units on the floor start with the correct unit index
    //         foreach ($unitDetails as $index => $unitDetail) {
    //             $unitName = $unitDetail['name'];
    //             $expectedUnitName = $seriesBase . ($unitIndexStart + $index);

    //             // Check if the unit name matches the expected pattern
    //             if ($unitName !== $expectedUnitName) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Unit series name is not properly formatted. Please provide proper series names.',
    //                 ], 200);
    //             }
    //         }
    //     }
    //     return;
    // }
    // /**
    //  * Extract the base series, starting index, and increment step for the unit series.
    //  */
    // private function extractSeriesIncrement($firstFloorDetails)
    // {
    //     // Get the first unit's name from the first floor's unit details
    //     $firstUnitName = $firstFloorDetails['unit_details'][0]['name'];

    //     // Extract the numeric part of the unit name
    //     preg_match('/\d+$/', $firstUnitName, $matches);
    //     $unitIndexStart = (int) $matches[0]; // Extract starting number (e.g., 1, 233, 11, 101, etc.)
    //     $seriesBase = preg_replace('/\d+$/', '', $firstUnitName); // Extract base (e.g., '1', '233', '11', '101')

    //     // Determine the increment step based on the difference between first floor units
    //     $incrementStep = $this->determineIncrementStep($firstFloorDetails);

    //     return [$seriesBase, $unitIndexStart, $incrementStep];
    // }

    // /**
    //  * Determine the increment step based on the difference between units in the first floor.
    //  */
    // private function determineIncrementStep($firstFloorDetails)
    // {
    //     // Get the units of the first floor
    //     $unitDetails = $firstFloorDetails['unit_details'];

    //     // Determine the step between the first two units of the first floor
    //     if (isset($unitDetails[1]) && isset($unitDetails[0])) {
    //         $step = $unitDetails[1]['name'] - $unitDetails[0]['name'];
    //     } else {
    //         $step = 1; // Default step if there's no valid difference
    //     }

    //     return $step;
    // }

    // /**
    //  * Validate that the unit names in the request are properly formatted and incremented.
    //  */
    // private function validateUnitSeries($floorDetails, $seriesBase, $unitIndexStart, $incrementStep)
    // {
    //     foreach ($floorDetails as $floorDetail) {
    //         $unitDetails = $floorDetail['unit_details'];
    //         foreach ($unitDetails as $index => $unitDetail) {
    //             $unitName = $unitDetail['name'];
    //             $expectedUnitName = $seriesBase . ($unitIndexStart + $index * $incrementStep);

    //             // Check if the unit name matches the expected pattern
    //             if ($unitName !== $expectedUnitName) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Unit series name is not properly formatted. Please provide proper series names.',
    //                 ], 200);
    //             }
    //         }
    //     }
    // }
}
