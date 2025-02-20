<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Mail\ManageLeads;
use App\Models\CustomField;
use App\Models\CustomFieldsStructure;
use App\Models\CustomFieldsTypeValue;
use App\Models\LeadCustomer;
use App\Models\LeadCustomerUnit;
use App\Models\LeadCustomerUnitData;
use App\Models\LeadsCustomersTag;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\Tag;
use App\Models\UserProperty;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade as PDF;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use App\Models\UserCapability;
use App\Models\Feature;
use App\Models\ModulePlanFeature;
use App\Models\Plan;









class LeadController extends Controller
{

    public function getLeads($pid, $flag, $skey, $sort, $sortbykey, $statusid, $customfieldid,$customfieldvalue, $tagid, $offset, $limit)
    {
        // flag  1->allleads,2->members(customers),3->non members(interested leads)

        try {
            if ($pid != 'null') {
                // Base query
                $allLeads = LeadCustomer::with(['userproperty', 'leadSource', 'leadCustomerUnits.unit.wingDetail', 'tags', 'leadStatus', 'customFields'])
                    ->where('property_id', $pid);

     

                // Combine filters for tags, status, and custom fields
                $allLeads->when($flag != 'null', function ($query) use ($flag) {
                    if ($flag == 2) {
                        // Customers (entity_type = 2)
                        $query->where('entity_type', 2);
                    } elseif ($flag == 3) {
                        // Non-members (entity_type = 1)
                        $query->where('entity_type', 1);
                    }
                })->when($statusid != 'null', function ($query) use ($statusid) {
                    $query->where('status_id', $statusid);
                })->when($tagid != 'null', function ($query) use ($tagid) {
                    $query->whereHas('tags', function ($subQuery) use ($tagid) {
                        $subQuery->where('tag_id', $tagid);
                    });
                })->when($customfieldid != 'null' && $customfieldvalue != 'null', function ($query) use ($customfieldid, $customfieldvalue) {
                    $query->whereHas('customFields', function ($subQuery) use ($customfieldid, $customfieldvalue) {
                        $customFieldType = CustomField::find($customfieldid)->custom_fields_type_values_id;
                
                        if (in_array($customFieldType, [1, 2, 3, 4])) {
                            // String or numeric matches for Small Text, Long Text, Number, or Date
                            $subQuery->where('custom_field_id', $customfieldid)
                                ->where(function ($subQ) use ($customfieldvalue) {
                                    $subQ->where('text_value', 'like', "%{$customfieldvalue}%")
                                        ->orWhere('small_text_value', 'like', "%{$customfieldvalue}%")
                                        ->orWhere('int_value', 'like', "%{$customfieldvalue}%")
                                        ->orWhere('date_value', 'like', "%{$customfieldvalue}%")
                                        ->orWhere('date_time_value', 'like', "%{$customfieldvalue}%");
                                });
                        } elseif (in_array($customFieldType, [5, 6])) {
                            // Single or Multi Selection, expecting comma-separated IDs
                            $values = explode(',', $customfieldvalue);
                            $subQuery->where('custom_field_id', $customfieldid)
                                ->whereIn('custom_fields_structure_id', $values);
                        }
                    });
                });

                // Apply search key filter (without custom fields)
                $allLeads->when($skey != 'null' , function ($query) use ($skey) {
                    $query->where(function ($subQuery) use ($skey) {
                        $subQuery->where('name', 'like', "%{$skey}%")
                            ->orWhere('email', 'like', "%{$skey}%")
                            ->orWhere('contact_no', 'like', "%{$skey}%")
                            ->orWhereHas('leadSource', function ($sourceQuery) use ($skey) {
                                $sourceQuery->where('name', 'like', "%{$skey}%");
                            });
                    });
                });

                // Apply sorting
                $allLeads->when($sortbykey != 'null', function ($query) use ($sortbykey, $sort) {
                    if (in_array($sortbykey, ['name', 'email', 'contact_no'])) {
                        $query->orderBy($sortbykey, $sort);
                    } elseif ($sortbykey == 'source') {
                        $query->leftJoin('lead_sources', 'leads_customers.source_id', '=', 'lead_sources.id')
                            ->select('leads_customers.*', 'lead_sources.name as source_name')
                            ->orderBy('lead_sources.name', $sort);
                    }
                }, function ($query) {
                    $query->orderBy('id', 'desc'); // Default sorting
                });

            

                // Paginate results
                $totalCount = $allLeads->count();
                $offset = max(1, min($offset, ceil($totalCount / $limit))); // Ensure offset is valid

                $allLeads = $allLeads->paginate($limit, ['*'], 'page', $offset);
                // $allLeads = $allLeads->paginate($limit, ['*'], 'page', $offset);

                // Modify the response to include unit name and wing name at the top level
                foreach ($allLeads as $lead) {
                    $lead->unit_name = null; // Default value
                    $lead->wing_name = null; // Default value

                    if ($lead->leadCustomerUnits->isNotEmpty()) {
                        foreach ($lead->leadCustomerUnits as $leadUnit) {
                            if ($leadUnit->allocated_lead_id) {
                                $unit = $leadUnit->unit; // Get the related unit details
                                if ($unit) {
                                    $lead->unit_name = $unit->name; // Assign unit name to lead
                                    $lead->wing_name = $unit->wingDetail->name ?? null; // Assign wing name to lead if exists
                                }
                            }
                        }
                    }
                }

                return $allLeads;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getLeadDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getUserProperties($uid)
    {
        try {
            if ($uid != 'null') {
                $allUserProperties = UserProperty::where('user_id', $uid)->get();
                return $allUserProperties;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getUserproperty';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getLeadSources(Request $request)
    {

        try {
            $allSources = LeadSource::all();
            return $allSources;
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getSources';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }
    public function getLeadStatus(Request $request)
    {

        try {
            $allStatus = LeadStatus::all();
            return $allStatus;
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getLeadStatus';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function fetchLeadDetail(Request $request, $pid, $lid)
    {
        try {
            if ($pid != 'null' && $lid != 'null') {
                $fetchLeadDetail = LeadCustomer::with('userproperty', 'leadSource', 'tags')->where('property_id', $pid)->where('id', $lid)->first();

                if ($fetchLeadDetail) {
                    // Transform tags to include only names
                    $tagsArray = $fetchLeadDetail->tags->pluck('name')->toArray();
                    $fetchLeadDetail = $fetchLeadDetail->toArray(); // Convert to array
                    $fetchLeadDetail['tags'] = $tagsArray;
                }
                return $fetchLeadDetail;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'fetchParticularLead';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function addOrEditLeads(Request $request)
    {
        try {

            // Validate inputs
            $validatedData = $request->validate([
                'propertyinterest' => 'required|integer',  // Assuming propertyinterest is an integer (property_id)
                'name' => 'required|string|max:255',       // Name is required and must be a string
                'contactno' => 'required|integer',   // Contact number is required, can be a string
                'agent_name' => 'nullable|string|max:255',
                'agent_contact' =>  'nullable|string|max:15',
                'source' => 'required|integer',            // Source ID is required (1-reference, 2-social media, etc.)
                'status' => 'required|integer',
                'budget' => 'nullable|numeric',            // Budget is optional and must be a number if provided
                'leadid' => 'required|numeric',
                'notes' => 'nullable|string',
                // 'flag' => 'required|in:1,2',                // Flag to determine lead type
                'unitId' => 'nullable|integer',           // Unit ID will be provided for flag 2
            ]);

            // Retrieve validated data from the request
            $propertyid = $validatedData['propertyinterest'];
            $name = $validatedData['name'];
            $contactno = $validatedData['contactno'];
            $agentname = $validatedData['agent_name'];
            $agentcontact = $validatedData['agent_contact'];
            $sourceid = $validatedData['source'];
            $budget = $request->input('budget'); // Budget remains nullable
            $leadid = $request->input('leadid');
            // $flag = $validatedData['flag'];  // New flag parameter
            $unit_id = $request->input('unitId'); // Optional unit ID
            $email = $request->input('email');
            $notes = $request->input('notes');
            $status = $request->input('status');
            $flag = $request->input('flag'); //1 means form leads add sales module, 2 means leads add from lead module
            $userId = $request->input('userId');
            $actionName = $request->input('userCapabilities');

            if ($flag == 2) {
                $address = $request->input('address');
                $city = $request->input('city');
                $state = $request->input('state');
                $pincode = $request->input('pincode');
                $reminder_date = $request->input('reminder_date');
                $tags = $request->input('tags');
                $customFieldData = $request->input('CustomFieldData', []);
            }



            if ($unit_id == null) {
                //for add manual lead and edit lead
                // Flag 1: Normal lead add

                if ($leadid == 0) { //if new lead


                    //add lead condidtions plan limit check
                    $moduleId = Helper::getModuleIdFromAction($actionName);

                    $feature = Feature::where('action_name', $actionName)
                        ->where('module_id', $moduleId)
                        ->first();

                    $limitCheck = $this->checkFeatureLimits($userId, $moduleId, $feature, null);
                    if ($limitCheck) {
                        return $limitCheck; // Return upgrade response if limit is exceeded
                    }



                    // Check if the same contact number and property combination already exists
                    $existingLead = LeadCustomer::where('contact_no', $contactno)
                        ->where('property_id', $propertyid)
                        ->first();

                    if (!$existingLead) {
                        // Create a new lead record for manual or web form entry
                        $leadData = [
                            'property_id' => $propertyid,
                            'name' => $name,
                            'contact_no' => $contactno,
                            'agent_name' => $agentname,
                            'agent_contact' => $agentcontact,
                            'email' => $email,
                            'source_id' => $sourceid,
                            'status_id' => $status, // 0-new
                            'type' => 0, // manual
                            'notes' => $notes,
                            'entity_type' => 1,
                            'user_id' => $userId
                        ];

                        // Add additional fields if flag is 2
                        if ($flag == 2) {
                            $leadData = array_merge($leadData, [
                                'address' => $address,
                                'city' => $city,
                                'state' => $state,
                                'pincode' => $pincode,
                                'reminder_date' => $reminder_date,
                            ]);
                        }

                        // Create the lead
                        $lead = LeadCustomer::create($leadData);


                        if ($flag == 2) {
                            // Add or update tags associated with this lead
                            if (isset($tags) && is_array($tags)) {
                                foreach ($tags as $tagName) {
                                    // Call the addTagToLead function to handle tag insertion and association
                                    $this->addTagToLead($lead->id, $lead->property_id, $tagName);
                                }
                            }

                            //common function for saving customfields based on leads
                            if (isset($customFieldData) && is_array($customFieldData)) {
                                CustomFieldController::saveCustomFieldData($propertyid, $lead->id, $customFieldData);
                            }
                        }
                        // Return success response
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Lead added successfully.',
                            'data' => $lead
                        ], 200);
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => $existingLead->name . ' is already added with this contact no.',
                            'data' => null
                        ], 200);
                    }
                } else {
                    //edit case of lead
                    // Update an existing lead record
                    $lead = LeadCustomer::find($leadid);

                    if (!$lead) {
                        // Return error if lead not found
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Lead/customer not found.',
                            'data' => null
                        ], 200);
                    }

                    // Check if another lead with the same contact number and updated property_id exists
                    $duplicateLead = LeadCustomer::where('contact_no', $contactno)
                        ->where('property_id', $propertyid)
                        ->where('id', '!=', $leadid)  // Exclude the current lead
                        ->first();


                    if ($duplicateLead) {
                        return response()->json([
                            'status' => 'error',
                            'message' => $duplicateLead->name . ' is already added with this contact no.',
                            'data' => null
                        ], 200);
                    }

                    // Update the existing lead record
                    $lead->update([
                        'property_id' => $propertyid,
                        'name' => $name,
                        'contact_no' => $contactno,
                        'agent_name' => $agentname,
                        'agent_contact' => $agentcontact,
                        'email' => $email,
                        'source_id' => $sourceid,
                        'status_id' => $status, // You can change this to another value if needed
                        'type' => 0, // 0 - manual, modify if necessary
                        'notes' => $notes,
                        'user_id' => $userId,
                    ]);

                    if ($lead->entity_type != 2) {
                        $lead->update([
                            'entity_type' => 1,
                        ]);
                    }

                    if ($flag == 2) {
                        $lead->update([
                            'address' => $address,
                            'city' => $city,
                            'state' => $state,
                            'pincode' => $pincode,
                            'reminder_date' => $reminder_date
                        ]);
                    }

                    if ($flag == 2) {
                        // Add or update tags associated with this lead
                        if (isset($tags) && is_array($tags)) {
                            // Get the current tags associated with the lead
                            $currentTags = $lead->tags->pluck('id')->toArray();

                            // Iterate over the incoming tags
                            foreach ($tags as $tagName) {
                                $this->addTagToLead($lead->id, $lead->property_id, $tagName);
                            }

                            // Find tags that need to be removed (tags present in current but not in the new list)
                            $tagsToRemove = array_diff($currentTags, array_map(function ($tag) use ($lead) {
                                return Tag::firstOrCreate(
                                    ['name' => $tag, 'property_id' => $lead->property_id],
                                    ['created_at' => now(), 'updated_at' => now()]
                                )->id;
                            }, $tags));

                            // Remove those tags
                            LeadsCustomersTag::where('leads_customers_id', $lead->id)
                                ->whereIn('tag_id', $tagsToRemove)
                                ->delete();
                        }

                        //common function for saving customfields based on leads
                        if (isset($customFieldData) && is_array($customFieldData)) {
                            CustomFieldController::saveCustomFieldData($propertyid, $lead->id, $customFieldData);
                        }
                    }
                    // Return success response for updating the lead
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Lead updated successfully.',
                        'data' => $lead
                    ], 200);
                }
            } elseif ($unit_id != null) {
                //for add lead based on unit association means interested leads
                // Flag 2: Add new lead with attached unit

                if ($leadid == 0) { //if new lead



                    //add lead condidtions plan limit check
                    $moduleId = Helper::getModuleIdFromAction($actionName);

                    $feature = Feature::where('action_name', $actionName)
                        ->where('module_id', $moduleId)
                        ->first();

                    $limitCheck = $this->checkFeatureLimits($userId, $moduleId, $feature, null,null);
                    if ($limitCheck) {
                        return $limitCheck; // Return upgrade response if limit is exceeded
                    }


                    // Check if the same contact number and property combination already exists
                    $existingLead = LeadCustomer::where('contact_no', $contactno)
                        ->where('property_id', $propertyid)
                        ->first();


                    if (!$existingLead) {
                        // Create a new lead record


                        $leadData = [
                            'property_id' => $propertyid,
                            'name' => $name,
                            'contact_no' => $contactno,
                            'agent_name' => $agentname,
                            'agent_contact' => $agentcontact,
                            'email' => $email,
                            'source_id' => $sourceid,
                            'status_id' => $status, // 0-new
                            'type' => 0, // manual
                            'notes' => $notes,
                            'entity_type' => 1,
                            'user_id' => $userId,
                        ];

                        // Add additional fields if flag is 2
                        if ($flag == 2) {
                            $leadData = array_merge($leadData, [
                                'address' => $address,
                                'city' => $city,
                                'state' => $state,
                                'pincode' => $pincode,
                                'reminder_date' => $reminder_date,
                            ]);
                        }

                        // Create the lead
                        $lead = LeadCustomer::create($leadData);


                        if ($flag == 2) {
                            // Add or update tags associated with this lead
                            if (isset($tags) && is_array($tags)) {
                                foreach ($tags as $tagName) {
                                    // Call the addTagToLead function to handle tag insertion and association
                                    $this->addTagToLead($lead->id, $lead->property_id, $tagName);
                                }
                            }

                            //common function for saving customfields based on leads
                            CustomFieldController::saveCustomFieldData($propertyid, $lead->id, $customFieldData);
                        }
                        // Now handle the LeadUnit entry
                        $existingUnit = LeadCustomerUnit::where('unit_id', $unit_id)->first();

                        if ($existingUnit) {
                            // Append the new lead ID to the interested_lead_id (comma-separated)
                            // Convert the comma-separated string of IDs to an array
                            $interestedLeadIds = explode(',', $existingUnit->interested_lead_id);

                            // Check if the current lead ID is already in the array
                            if (!in_array($lead->id, $interestedLeadIds)) {
                                // Append the new lead ID only if it's not already in the array
                                $interestedLeadIds[] = $lead->id;
                                $existingUnit->interested_lead_id = implode(',', $interestedLeadIds);

                                // Update the lead_unit entry
                                $existingUnit->save();
                            }
                        } else {
                            // Create a new lead_unit entry if no existing entry for the unit
                            $existingUnit = LeadCustomerUnit::create([
                                'interested_lead_id' => $lead->id,
                                'leads_customers_id' => null,
                                'unit_id' => $unit_id,
                                'booking_status' => 2,
                            ]);
                        }



                        // Now handle the LeadUnitData entry
                        $leadUnitData = LeadCustomerUnitData::where('leads_customers_unit_id', $existingUnit->id)
                            ->where('leads_customers_id', $lead->id)
                            ->first();


                        if ($leadUnitData) {
                            // Update the budget if LeadUnitData exists
                            $leadUnitData->update([
                                'budget' => $budget,
                            ]);
                        } else {
                            // Create a new LeadUnitData entry if it doesn't exist
                            $leadcustomerunitdata = new LeadCustomerUnitData();
                            $leadcustomerunitdata->leads_customers_unit_id = $existingUnit->id;
                            $leadcustomerunitdata->leads_customers_id = $lead->id;
                            $leadcustomerunitdata->budget = $budget;
                            $leadcustomerunitdata->save();
                        }

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Lead added with unit successfully.',
                            'data' => $lead
                        ], 200);
                    } else {
                        // If the lead exists, don't create a new lead, but pass it to the LeadUnit table

                        return response()->json([
                            'status' => 'error',
                            'message' => $existingLead->name . ' is already added with this contact no.',
                            'data' => null
                        ], 200);
                    }




                    // Return success response
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Lead added with unit successfully.',
                        'data' => $lead
                    ], 200);
                } else {

                    //add lead condidtions plan limit check
                    $moduleId = Helper::getModuleIdFromAction($actionName);

                    $feature = Feature::where('action_name', $actionName)
                        ->where('module_id', $moduleId)
                        ->first();

                    $limitCheck = $this->checkFeatureLimits($userId, $moduleId, $feature, null,null);
                    if ($limitCheck) {
                        return $limitCheck; // Return upgrade response if limit is exceeded
                    }


                    $existingLead = LeadCustomer::where('contact_no', $contactno)
                        ->where('property_id', $propertyid)
                        ->first();

                    if (!$existingLead) {
                        // Create a new lead record
                        $leadData = [
                            'property_id' => $propertyid,
                            'name' => $name,
                            'contact_no' => $contactno,
                            'agent_name' => $agentname,
                            'agent_contact' => $agentcontact,
                            'email' => $email,
                            'source_id' => $sourceid,
                            'status_id' => $status, // 0-new
                            'type' => 0, // manual
                            'notes' => $notes,
                            'entity_type' => 1,
                            'user_id' => $userId,
                        ];

                        // Add additional fields if flag is 2
                        if ($flag == 2) {
                            $leadData = array_merge($leadData, [
                                'address' => $address,
                                'city' => $city,
                                'state' => $state,
                                'pincode' => $pincode,
                                'reminder_date' => $reminder_date,
                            ]);
                        }

                        // Create the lead
                        $lead = LeadCustomer::create($leadData);


                        if ($flag == 2) {
                            // Add or update tags associated with this lead
                            if (isset($tags) && is_array($tags)) {
                                foreach ($tags as $tagName) {
                                    // Call the addTagToLead function to handle tag insertion and association
                                    $this->addTagToLead($lead->id, $lead->property_id, $tagName);
                                }
                            }

                            //common function for saving customfields based on leads
                            CustomFieldController::saveCustomFieldData($propertyid, $lead->id, $customFieldData);
                        }
                        // Now handle the LeadUnit entry
                        $existingUnit = LeadCustomerUnit::where('unit_id', $unit_id)->first();

                        if ($existingUnit) {
                            // Append the new lead ID to the interested_lead_id (comma-separated)
                            // Convert the comma-separated string of IDs to an array
                            $interestedLeadIds = explode(',', $existingUnit->interested_lead_id);

                            // Check if the current lead ID is already in the array
                            if (!in_array($lead->id, $interestedLeadIds)) {
                                // Append the new lead ID only if it's not already in the array
                                $interestedLeadIds[] = $lead->id;
                                $existingUnit->interested_lead_id = implode(',', $interestedLeadIds);

                                // Update the lead_unit entry
                                $existingUnit->save();
                            }
                        } else {
                            // Create a new lead_unit entry if no existing entry for the unit
                            $existingUnit = LeadCustomerUnit::create([
                                'interested_lead_id' => $lead->id,
                                'leads_customers_id' => null,
                                'unit_id' => $unit_id,
                                'booking_status' => 2,
                            ]);
                        }


                        // Now handle the LeadUnitData entry
                        $leadUnitData = LeadCustomerUnitData::where('leads_customers_unit_id', $existingUnit->id)
                            ->where('leads_customers_id', $lead->id)
                            ->first();

                        if ($leadUnitData) {
                            // Update the budget if LeadUnitData exists
                            $leadUnitData->update([
                                'budget' => $budget,
                            ]);
                        } else {
                            // Create a new LeadUnitData entry if it doesn't exist
                            $leadcustomerunitdata = new LeadCustomerUnitData();
                            $leadcustomerunitdata->leads_customers_unit_id = $existingUnit->id;
                            $leadcustomerunitdata->leads_customers_id = $lead->id;
                            $leadcustomerunitdata->budget = $budget;
                            $leadcustomerunitdata->save();
                        }

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Lead added with unit successfully',
                            'data' => null
                        ], 200);
                    } else {
                        // If the lead exists, don't create a new lead, but pass it to the LeadUnit table
                        // $lead = $existingLead;
                        return response()->json([
                            'status' => 'error',
                            'message' => $existingLead->name . ' is already added with this contact no.',
                            'data' => null
                        ], 200);
                    }



                    // Return success response
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Lead added with unit successfully.',
                        'data' => $lead
                    ], 200);
                }
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addEditLeadDetails';
            $errorMessage = $e->getMessage() . $e->getLine();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }


    private function addTagToLead($leadId, $propertyId, $tagName)
    {
        // Check if the tag already exists for the given property
        $tag = Tag::firstOrCreate(
            ['name' => $tagName, 'property_id' => $propertyId],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // Check if the tag is already associated with the lead, if not, add it
        $existingAssociation = LeadsCustomersTag::where('leads_customers_id', $leadId)
            ->where('tag_id', $tag->id)
            ->first();

        if (!$existingAssociation) {
            // Add the new association in the bridge table
            LeadsCustomersTag::create([
                'leads_customers_id' => $leadId,
                'tag_id' => $tag->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    public function addLeadsCsv(Request $request)
    {
        try {
            // Check if a single CSV file is uploaded
            $file = $request->file('file');
            $propertyId = $request->input('propertyid');
            $userId = $request->input('userId');

            // Fetch property user email based on property id
            $property = UserProperty::find($propertyId);
            $propertyUserEmail = $property->user->email; // Assuming `user` is the relationship to the user


            // Validate file extension (CSV or XLSX)
            $extension = $file->getClientOriginalExtension();
            if (!in_array($extension, ['csv', 'xlsx'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid file format. Only CSV and XLSX files are allowed.',
                ], 200);
            }

            // Read file based on extension
            if ($extension == 'csv') {
   
                // Open CSV file and process it
                $csvFile = fopen($file, 'r');
                $header = fgetcsv($csvFile);
                $expectedHeaders = ['name', 'email(optional)', 'contact', 'source', 'notes(optional)', 'status'];
                $escapedHeader = [];

                foreach ($header as $value) {
                    // Normalize headers by removing spaces and setting to lowercase
                    $normalizedHeader = strtolower(str_replace([' ', '(optional)'], '', $value));
                    $escapedHeader[] = $normalizedHeader;
                }

                $normalizedExpectedHeaders = ['name', 'email', 'contact', 'source', 'notes', 'status'];

                // Validate CSV headers
                if (array_diff($normalizedExpectedHeaders, $escapedHeader)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid CSV headers.',
                    ], 200);
                }

                // Read the rest of the rows
                $fileContent = [];
               
                while (($row = fgetcsv($csvFile)) !== false) {
                    $fileContent[] = array_combine($escapedHeader, $row);
                    // $csvRowCount++; // Increment count

                      
                }

                
                
            } else {


                // //add lead condidtions plan limit check
                // $actionName = $request->input('userCapabilities');
                // $moduleId = Helper::getModuleIdFromAction($actionName);

                // $feature = Feature::where('action_name', $actionName)
                //     ->where('module_id', $moduleId)
                //     ->first();

                // $limitCheck = $this->checkFeatureLimits($userId, $moduleId, $feature, null);;
                // if ($limitCheck) {
                //     return $limitCheck; // Return upgrade response if limit is exceeded
                // }


                // Read XLSX file using PhpSpreadsheet
                $reader = IOFactory::createReaderForFile($file);
                $spreadsheet = $reader->load($file);
                $sheet = $spreadsheet->getActiveSheet();
                $header = $sheet->rangeToArray('A1:E1')[0]; // Assuming headers are in the first row

                $expectedHeaders = ['name', 'email(optional)', 'contact', 'source', 'notes(optional)', 'status'];
                $escapedHeader = [];

                foreach ($header as $value) {
                    $normalizedHeader = strtolower(str_replace([' ', '(optional)'], '', $value));
                    $escapedHeader[] = $normalizedHeader;
                }

                $normalizedExpectedHeaders = ['name', 'email', 'contact', 'source', 'notes', 'status'];

                // Validate XLSX headers
                if (array_diff($normalizedExpectedHeaders, $escapedHeader)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid XLSX headers.',
                    ], 200);
                }

                // Get all rows (excluding the header)
                $fileContent = $sheet->toArray(null, true, true, true);
            }

            // Initialize arrays for processed leads and issues
            $leadsAdded = [];
            $leadsIssues = [];

            // Process rows
            $csvRowCount = 0;
            foreach ($fileContent as $row) {
                if ($extension == 'csv') {
                     // Initialize row counter

                    $data = array_combine($escapedHeader, $row);


                    $datalead=[
                        'csvRowCount' =>$csvRowCount,
                    ];
                     // Add lead conditions plan limit check
                    $actionName = $request->input('userCapabilities');
                    $moduleId = Helper::getModuleIdFromAction($actionName);
    
                    $feature = Feature::where('action_name', $actionName)
                        ->where('module_id', $moduleId)
                        ->first();
    
                    // Check the feature limits against the CSV row count
                    $limitCheck = $this->checkFeatureLimits($userId, $moduleId, $feature, $datalead ,null);
    

                    if ($limitCheck) {
                        return $limitCheck; // Return upgrade response if limit is exceeded
                    } 

                } else {
                    // For XLSX, adjust to map the data properly
                    $data = array_combine($escapedHeader, $row);
                }

                if (empty($data['name']) && empty($data['contact']) && empty($data['source'] && empty($data['status']))) {
                    continue;
                }

                // Validate required fields (name, contact, source,status)
                if (empty($data['name']) || empty($data['contact']) || empty($data['source'] ||  empty($data['status']))) {
                    Helper::errorLog('addLeadDetailsfailed', 'Missing required field(s)', 'high');
                    $leadsIssues[] = [
                        'name' => $data['name'] ?? 'N/A',
                        'email' => $data['email'] ?? 'N/A',
                        'notes' => $data['notes'] ?? 'N/A',
                        'contact' => $data['contact'] ?? 'N/A',
                        'source' => $data['source'] ?? 'N/A',
                        'status' => $data['status'] ?? 'N/A',
                        'reason' => 'Missing required field(s)',
                    ];
                    continue;
                }

                // Validate phone number (10 digits)
                if (!preg_match('/^\d{10}$/', $data['contact'])) {
                    $leadsIssues[] = [
                        'name' => $data['name'],
                        'contact' => $data['contact'],
                        'reason' => 'Invalid phone number (must be 10 digits)',
                    ];
                    continue;
                }

                // Validate email format
                if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    Helper::errorLog('addLeadDetailsfailed', 'Invalid email format', 'high');
                    $leadsIssues[] = [
                        'name' => $data['name'],
                        'email' => $data['email'] ?? 'N/A',
                        'reason' => 'Invalid email format',
                    ];
                    continue;
                }

                try {

                    // Process the lead (same logic as before)
                    $source = LeadSource::whereRaw('LOWER(name) = ?', [strtolower($data['source'])])->first();
                    if (!$source) {
                        $source = LeadSource::find(6);
                    }


                    $status = LeadStatus::whereRaw('LOWER(name) = ?', [strtolower($data['status'])])->first();
                    if (!$status) {
                        $status = LeadStatus::find(1);
                    }

                    $existingLead = LeadCustomer::where('contact_no', $data['contact'])
                        ->where('property_id', $propertyId)
                        ->first();

                    if ($existingLead) {
                        $leadsIssues[] = [
                            'name' => $data['name'],
                            'reason' => 'Duplicate entry based on contact number',
                        ];
                        continue;
                    }

                    // Create lead record
                    $lead = LeadCustomer::create([
                        'property_id' => $propertyId,
                        'user_id' => $userId,
                        'name' => $data['name'],
                        'email' => $data['email'] ?? null,
                        'contact_no' => $data['contact'],
                        'notes' => $data['notes'] ?? null,
                        'source_id' => $source->id,
                        'status_id' => $status->id, // New lead
                        'type' => 1, // From CSV/XLSX
                    ]);


                    $leadsAdded[] = $lead;
                    $csvRowCount ++;
                } catch (Exception $e) {
                    $leadsIssues[] = [
                        'name' => $data['name'],
                        'reason' => "Error: " . $e->getMessage(),
                    ];
                    Helper::errorLog('addLeadDetailsfailed', $e->getMessage(), 'high');
                }
            }

            // Close file for CSV
            // if ($extension == 'csv') {
            //     fclose($fileContent);
            // } // Close the file after processing

            // if (count($leadsIssues) > 0) {
            //     // Create a temporary CSV file for skipped/failed leads
            //     $csvFilePath = storage_path('app/leads_issues_' . time() . '.csv');
            //     $csvHandle = fopen($csvFilePath, 'w');
            //     fputcsv($csvHandle, ['Name', 'Email(optional)', 'Contact', 'Source','Reason']);

            //     foreach ($leadsIssues as $leadIssue) {
            //         fputcsv($csvHandle, [
            //             $leadIssue['name'],
            //             $leadIssue['email'],
            //             $leadIssue['contact'],
            //             $leadIssue['source'],
            //             $leadIssue['reason']
            //         ]);
            //     }

            //     fclose($csvHandle);

            //     // Send the email with the CSV attachment
            //     Mail::to($propertyUserEmail)->send(new ManageLeads($property, $leadsIssues, $csvFilePath));

            //     // Delete the temporary file after sending the email
            //     if (file_exists($csvFilePath)) {
            //         unlink($csvFilePath); // This removes the temporary CSV file
            //     }
            // }

            return response()->json([
                'status' => 'success',
                'message' => 'Leads added successfully from CSV file.',
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addLeadDetails';
            $errorMessage = $e->getLine() . $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }


    //rest api
    public function generateLead(Request $request)
    {
        try {
            // Validate client_id and client_secret
            $client_id = $request->query('client_id');
            $client_secret_key = $request->query('client_secret_key');

            // Check if client_id and client_secret are provided
            if (!$client_id || !$client_secret_key) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing or Invalid Data.'
                ], 200);
            }

            // Find the user with the given client_id and client_secret
            $user = User::where('client_id', $client_id)
                ->where('client_secret_key', $client_secret_key)
                ->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid Authentication.'
                ], 200);
            }

            // Validate JSON input for lead creation
            $validatedData = $request->validate([
                'leads' => 'required|array', // Expect an array of leads
                'leads.*.name' => 'required|string|max:255',
                'leads.*.email' => 'nullable|email|max:255',
                'leads.*.contact' => 'required|string|max:15',
                'leads.*.notes' => 'nullable|string', // Ensure budget is required and numeric
                'leads.*.source' => 'required|string|max:255', // Example: "call"
                'leads.*.property' => 'required|string|max:255', // Property could be validated more specifically if needed
                'leads.*.status' => 'required|string|max:255',
            ]);

            $createdLeads = [];
            $existingLeads = [];

            foreach ($validatedData['leads'] as $leadData) {
                // Find property ID by property name
                $property = UserProperty::whereRaw('LOWER(name) = ?', [strtolower($leadData['property'])])->first();

                if (!$property) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Property not found for ' . $leadData['property'],
                    ], 200);
                }

                //add lead condidtions plan limit check
                $userId = $property->user_id;
                $actionName = "webform_api_integrations";

                $moduleId = Helper::getModuleIdFromAction($actionName);

                $feature = Feature::where('action_name', $actionName)
                    ->where('module_id', $moduleId)
                    ->first();

                $limitCheck = $this->checkFeatureLimits($userId, $moduleId, $feature, null,2);
                if ($limitCheck) {
                    return $limitCheck; // Return upgrade response if limit is exceeded
                }

                // Check if the lead with the same email and property already exists
                $existingLead = LeadCustomer::where('contact_no', $leadData['contact'])
                    ->where('property_id', $property->id)
                    ->first();

                if ($existingLead) {
                    $existingLeads[] = $leadData['contact']; // Collect existing leads
                    continue; // Skip to the next lead
                }

                // Find source ID
                $source = LeadSource::whereRaw('LOWER(name) = ?', [strtolower($leadData['source'])])->first();
                $status = LeadStatus::whereRaw('LOWER(name) = ?', [strtolower($leadData['status'])])->first();

                if (!$source) {
                    $source = LeadSource::find(6);
                }

                if (!$status) {
                    $status = LeadStatus::find(1);
                }





                // Create the new lead
                $newLead = LeadCustomer::create([
                    'property_id' => $property->id,
                    'name' => $leadData['name'],
                    'user_id' => $property->user_id,
                    'email' => $leadData['email'],
                    'contact_no' => $leadData['contact'],
                    'source_id' => $source->id,
                    'status_id' =>  $status->id,  // Default to new lead
                    'type' => 2, // 0 for manual, 1 CSV, 2 REST API,
                    'notes' => $leadData['notes'],
                ]);

                $createdLeads[] = $newLead; // Collect newly created leads
            }

            // Prepare the response
            return response()->json([
                'status' => 'success',
                'message' => 'Leads created successfully.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors and return a proper response
            return response()->json([
                'status' => 'error',
                'message' => 'Missing or Invalid Data.',
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'restapidetails';
            $errorMessage = $e->getMessage() . $e->getLine();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }




    //web form api
    public function webFormLead(Request $request)
    {
        try {

            $userId = $request->input('userId');
            $actionName = $request->input('userCapabilities');

            $moduleId = Helper::getModuleIdFromAction($actionName);

            $feature = Feature::where('action_name', $actionName)
                ->where('module_id', $moduleId)
                ->first();

            $limitCheck = $this->checkFeatureLimits($userId, $moduleId, $feature, null ,1);
            if ($limitCheck) {
                return $limitCheck; // Return upgrade response if limit is exceeded
            }

            // Step 1: Validate Google reCAPTCHA
            $recaptchaResponse = $request->input('grecaptcha');
            $secretKey = env('recaptcha_secret'); // Make sure to store your secret key in env

            // Make an API request to verify the reCAPTCHA response
            $recaptcha = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $recaptchaResponse,
            ]);

            $recaptchaResult = json_decode($recaptcha->body());

            // If reCAPTCHA validation fails
            if (!$recaptchaResult->success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'reCAPTCHA validation failed.',
                ], 200);
            }

            // Step 2: Referrer validation
            $referer = $request->header('referer'); // Get referer from headers

            // $referer="http://superbuildup.s3-website.ap-south-1.amazonaws.com/";
            //env('APP_FRONTEND_URL')= http://superbuildup.s3-website.ap-south-1.amazonaws.com/
            // $allowedDomains = [env('APP_FRONTEND_URL'), '127.0.0.1', 'localhost'];


            $frontendUrl = env('APP_FRONTEND_URL');

            // Normalize the domain by extracting the host part
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $frontendHost = parse_url($frontendUrl, PHP_URL_HOST);

            $allowedDomains = [$frontendHost, '127.0.0.1', 'localhost'];

            if (!$referer || !in_array($refererHost, $allowedDomains)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized domain.',
                ], 200);
            }

            if (!$referer || !in_array(parse_url($referer, PHP_URL_HOST), $allowedDomains)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized domain.',
                ], 200);
            }

            // Step 3: Validate form inputs
            $validatedData = $request->validate([
                'propertyinterest' => 'required|integer',  // Assuming propertyinterest is an integer (property_id)
                'name' => 'required|string|max:255',       // Name is required and must be a string
                'email' => 'nullable|email|max:255',       // Email is required and must be valid
                'contactno' => 'required|string|max:15',   // Contact number is required, can be a string
                'source' => 'required|integer',            // Source ID is required (1-reference, 2-social media, etc.)      // Budget is optional and must be a number if provided
                'notes' => 'nullable|string',
                'status' => 'required|integer',
            ]);

            // Retrieve validated data from the request
            $propertyid = $validatedData['propertyinterest'];
            $name = $validatedData['name'];
            $email = $validatedData['email'];
            $contactno = $validatedData['contactno'];
            $sourceid = $validatedData['source'];
            $notes = $request->input('notes'); // notes remains nullable
            $agentname = $request->input('agent_name');
            $agentcontact = $request->input('agent_contact');
            $status = $validatedData['status'];
            $userId = $request->input('userId');

            // Check if the same email and property combination already exists
            $existingLead = LeadCustomer::where('contact_no', $contactno)
                ->where('property_id', $propertyid)
                ->first();


            if (!$existingLead) {
                // Create a new lead record for manual or web form entry //0 or 2
                $lead = LeadCustomer::create([
                    'property_id' => $propertyid,
                    'user_id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'contact_no' => $contactno,
                    'agent_name' => $agentname,
                    'agent_contact' => $agentcontact,
                    'source_id' => $sourceid,
                    'notes' => $notes,
                    'status_id' => $status, //0-new, 1-negotiation, 2-in contact, 3-highly interested, 4-closed
                    'type' => 3 //web form
                ]);

                // Return success response
                return response()->json([
                    'status' => 'success',
                    'message' => 'Lead added successfully.',
                    'data' => $lead
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $existingLead->name . ' is already added with this contact no.',
                    'data' => null
                ], 200);
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'webformdetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }

    public function fetchLeadInterestedBookedDetail($pid, $lid)
    {
        try {
            if ($pid != 'null') {

                // Fetch Lead Customer details
                // Get the lead customer details based on property ID and lead ID
                $leadcustomerdetails = LeadCustomer::with(['leadCustomerUnits.unit', 'leadCustomerUnits.paymentTransaction', 'leadCustomerUnits.leadCustomer', 'leadCustomerUnits.leadCustomerUnitData'])
                    ->where('property_id', $pid)
                    ->where('id', $lid)
                    ->first();

                if ($leadcustomerdetails) {
                    // Initialize arrays for interested and booked units
                    $interestedLeads = [];
                    $bookedDetails = [];

                    // Fetch interested leads where this lead is marked as interested_lead_id
                    $interestedUnits = LeadCustomerUnit::where(function ($query) use ($lid) {
                        $query->where('interested_lead_id', $lid)
                            ->orWhereRaw('FIND_IN_SET(?, interested_lead_id)', [$lid]);
                    })->with(['unit.wingDetail', 'leadCustomerUnitData'])->get();
                    // return $interestedUnits;

                    foreach ($interestedUnits as $unit) {
                        $leadWiseBudget = $unit->leadCustomerUnitData
                            ->where('leads_customers_id', $lid) // Filter by the current lead ID
                            ->pluck('budget')
                            ->first();
                        $interestedLeads[] = [
                            'wing_name' => $unit->unit->wingDetail->name ?? null,
                            'unit_name' => $unit->unit->name ?? null,
                            'lead_name' => $unit->leadCustomer->name ?? null,
                            'budget' => $leadWiseBudget ?? null,
                        ];
                    }

                    // Loop through the lead customer's units
                    foreach ($leadcustomerdetails->leadCustomerUnits as $unit) {
                        // Booked units (booking details)
                        $paymentTransactions = PaymentTransaction::where('unit_id', $unit->unit_id)
                            ->where('leads_customers_id', $unit->leadCustomer->id)
                            ->orderBy('id', 'asc') // Ensure the first transaction is first in the results
                            ->get();

                        // Calculate the total paid amount
                        $totalPaidAmount = 0;

                        foreach ($paymentTransactions as $index => $transaction) {
                            if ($transaction->payment_status == 2) { // Only include payments where status is 2
                                if ($index == 0) {
                                    // Add the token amount from the first transaction
                                    $totalPaidAmount += $transaction->token_amt;
                                } else {
                                    // Add the next payable amount from subsequent transactions
                                    $totalPaidAmount += $transaction->next_payable_amt;
                                }
                            }
                        }
                        $bookedUnits = LeadCustomerUnit::where(function ($query) use ($lid) {
                            $query->where('leads_customers_id', $lid)
                                ->orWhereRaw('FIND_IN_SET(?, leads_customers_id)', [$lid]);
                        })->with(['unit.wingDetail', 'leadCustomerUnitData'])->get();
                        if ($unit->paymentTransaction || $bookedUnits) {
                            $bookedDetails[] = [
                                'wing_name' => $unit->unit->wingDetail->name,
                                'unit_name' => $unit->unit->name,
                                'customer_name' => $unit->leadCustomer->name,
                                'unit_price' => $unit->unit->price ?? 0,
                                'total_paid_amount' => $totalPaidAmount ?? 0,
                                'booking_date' => $unit->paymentTransaction->booking_date ?? null,
                            ];
                        }
                    }

                    // Return the response with the lead customer details, interested units, and booked units inside one object
                    return response()->json([
                        'leadcustomerdetails' => [
                            'id' => $leadcustomerdetails->id,
                            'property_id' => $leadcustomerdetails->property_id,
                            'name' => $leadcustomerdetails->name,
                            'agent_name' => $leadcustomerdetails->agent_name ?? null,
                            'agent_contact' => $leadcustomerdetails->agent_contact ?? null,
                            'email' => $leadcustomerdetails->email ?? null,
                            'contact_no' => $leadcustomerdetails->contact_no,
                            'source_id' => $leadcustomerdetails->source_id,
                            'source_name' => $leadcustomerdetails->leadSource->name ?? null, // Add lead source name
                            'status' => $leadcustomerdetails->status,
                            'status_name' => $leadcustomerdetails->leadStatus->name ?? null,
                            'type' => $leadcustomerdetails->type,
                            'entity_type' => $leadcustomerdetails->entity_type,
                            'notes' => $leadcustomerdetails->notes ??  null,
                            'interested_units' => $interestedLeads,
                            'booked_units' => $bookedDetails,
                        ],
                    ], 200);
                } else {
                    return response()->json([
                        'leadcustomerdetails' => null,
                    ], 200);
                }
            } else {
                return response()->json([
                    'leadcustomerdetails' => null,
                ], 200);
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'fetchLeadInterestedBookedDetail';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }
    public function getFieldTypes()
    {
        try {
            $allfieldtypes = CustomFieldsTypeValue::all();
            return $allfieldtypes;
        } catch (Exception $e) {
            $errorFrom = 'getFieldTypes';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function fetchTags($pid)
    {
        try {
            if ($pid != 'null') {
                $allTags = Tag::where('property_id', $pid)->get();
                return $allTags;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'fetchTags';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public static function checkFeatureLimits($userId, $moduleId, $feature,$data=null, $flag = null)
    {

        // Get the user's plan limit for the feature
        $feature = Feature::where('id', $feature->id)
            ->first();
        $actionName = $feature->action_name;


        $userCapability = UserCapability::where('user_id', $userId)
            ->where('module_id', $moduleId)
            ->where('feature_id', $feature->id)
            ->first();

            $featurename=ModulePlanFeature::where('module_id',$moduleId)
            ->where('plan_id',$userCapability->plan_id)
            ->where('feature_id',$feature->id)
            ->first();

        if (!$userCapability) {
            return response()->json([
                'status' => 'error',
                'message' => 'Feature access not found for the user.',
            ], 200);
        }


        $plandetail = Plan::find($userCapability->plan_id);
        $limit = $userCapability->limit ?? 0; // The limit based on the user's plan


        // Count how many leads the user has already entered
        $leadCount = LeadCustomer::where('user_id', $userId)->count();

      
        if (!empty($data) && isset($data['csvRowCount'])) {
            $leadCount = $data['csvRowCount'] + $leadCount;
        }else{
            $leadCount= $leadCount+1;
        }
// echo $leadCount;
        if ($actionName == 'manual_entry_csv_import') {
            if ($leadCount > $limit && ($plandetail->id == 1 || $plandetail->id == 2)) {
                // If the limit is exceeded, return the upgrade plan message
                return response()->json([
                    'status' => 'upgradeplan',
                    'moduleid' => $moduleId,
                    'activeplanname' => $plandetail->name ?? 'Unknown',
                    'buttontext'=> $limit." ".$featurename->name,
                ], 200);
            }
        } else if ($actionName == 'webform_api_integrations') {

            if ($flag == 1) {
                //for web from api
                $leadCount = LeadCustomer::where('user_id', $userId)->where('type', 3)->count();
            } else if ($flag == 2) {
                //for rest api
                $leadCount = LeadCustomer::where('user_id', $userId)->where('type', 2)->count();
            }

            if ($leadCount >= $limit && ($plandetail->id == 2 || $plandetail->id == 3 || $plandetail->id == 4)) {
                // If the limit is exceeded, return the upgrade plan message
                return response()->json([
                    'status' => 'upgradeplan',
                    'moduleid' => $moduleId,
                    'activeplanname' => $plandetail->name ?? 'Unknown',
                    'buttontext'=> $limit." ".$featurename->name, 
                ], 200);
            }
        }


        // If the limit is not exceeded, proceed with the request
        return null; // Proceed
    }

    public function fetchSingleMultiCustomFieldValue($pid, $cid)
    {
        try {
            if ($pid != 'null' && $cid != 'null') {

                //flag 1 means single select values
                $fieldValue = CustomField::where('property_id', $pid)
                    ->where('id', $cid)
                    ->first(); // Fetch first record based on custom_field_id and property_id

                if ($fieldValue) {
                    $fieldvalues = CustomFieldsStructure::where('custom_field_id', $cid)
                        ->get();

                    $response = $fieldvalues->toArray(); // Convert Eloquent collection to array

                }

                return response()->json($response);
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'fetchSingleMultiCustomFieldValue';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function exportLeads($pid,$flag=null)
    {
        try {
            if ($pid != 'null') {
                $allleads = LeadCustomer::with('leadStatus', 'leadSource', 'city', 'state')->where('property_id', $pid)->get();
                return $allleads;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $errorFrom = 'exportLeads';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }
}
