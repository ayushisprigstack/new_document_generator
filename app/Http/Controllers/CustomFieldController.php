<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\CustomField;
use App\Models\CustomFieldsStructure;
use App\Models\CustomFieldsTypeValue;
use App\Models\CustomFieldTypeValue;
use App\Models\CustomFieldsValue;
use App\Models\LeadCustomer;
use App\Models\LeadsCustomersTag;
use App\Models\Property;

use App\Models\UserProperty;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;






class CustomFieldController extends Controller
{


    // public function addCustomFields(Request $request)
    // {
    //     try {

    //         // Validate incoming request data
    //         $validatedData = $request->validate([
    //             'propertyId' => 'required',  // Ensure property exists
    //             'fieldname' => 'required|string|max:255',
    //             'fieldtype' => 'required|integer',
    //             'fieldrequired' => 'required|in:1,2',  // 1 = required, 2 = not required
    //             'singleselection' => 'array',
    //             'multiselection' => 'array',
    //             'fieldId'=>'nuallable',
    //         ]);

    //         // Extract input data
    //         $propertyId = $validatedData['propertyId'];
    //         $fieldName = $validatedData['fieldname'];
    //         $fieldType = $validatedData['fieldtype'];
    //         $isRequired = $validatedData['fieldrequired'];
    //         $singleSelection = $validatedData['singleselection'];
    //         $multiSelection = $validatedData['multiselection'];

    //         // Check if the custom field already exists for the given property
    //         $existingField = CustomField::where('property_id', $propertyId)
    //             ->where('name', $fieldName)
    //             ->first();

    //         if ($existingField) {
    //             // If the field already exists, return a response with an error message
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Custom field with the same name already exists for this property.',
    //             ], 200);
    //         }

    //         // Save the custom field in the custom_fields table
    //         $customField = CustomField::create([
    //             'property_id' => $propertyId,
    //             'name' => $fieldName,
    //             'custom_fields_type_values_id' => $fieldType,
    //             'is_required' => $isRequired,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);

    //         // Save the single and multi-selection values in the custom_fields_structures table
    //         if (!empty($singleSelection)) {
    //             foreach ($singleSelection as $value) {
    //                 CustomFieldsStructure::create([
    //                     'custom_field_id' => $customField->id,
    //                     'value_type' => 'single',  // single selection type
    //                     'value' => $value,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         }

    //         if (!empty($multiSelection)) {
    //             foreach ($multiSelection as $value) {
    //                 CustomFieldsStructure::create([
    //                     'custom_field_id' => $customField->id,
    //                     'value_type' => 'multi',  // multi selection type
    //                     'value' => $value,
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }
    //         }

    //         // Return success response
    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Custom field added successfully.',
    //         ], 200);
    //     } catch (\Exception $e) {
    //         Helper::errorLog('addCustomFields', $e->getLine() . $e->getMessage(), 'high');
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Something went wrong.',
    //         ], 400);
    //     }
    // }

    public function addCustomFields(Request $request)
    {
        try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'propertyId' => 'required',  // Ensure property exists
                'fieldname' => 'required|string|max:255',
                'fieldtype' => 'required|integer',
                'singleselection' => 'array',
                'multiselection' => 'array',
                'fieldId' => 'nullable|integer', // fieldid can be nullable
            ]);

            // Extract input data
            $propertyId = $validatedData['propertyId'];
            $fieldName = $validatedData['fieldname'];
            $fieldType = $validatedData['fieldtype'];
            $singleSelection = $validatedData['singleselection'];
            $multiSelection = $validatedData['multiselection'];
            $fieldId = $validatedData['fieldId'];

            // Case: If fieldid is provided and not 0, it's an edit request
            if ($fieldId != null && $fieldId != 0) {
                // Find the custom field by id
                $customField = CustomField::find($fieldId);

                // Check if the field exists and belongs to the provided property
                if (!$customField || $customField->property_id != $propertyId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Custom field not found for this property.',
                    ], 200);
                }

                // Check if the field name is being changed, ensure no other field with the same name exists
                if ($customField->name != $fieldName && CustomField::where('property_id', $propertyId)->where('id', '!=', $fieldId)->where('name', $fieldName)->exists()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Custom field with the same name already exists for this property.',
                    ], 200);
                }

                // Update the custom field
                $customField->update([
                    'name' => $fieldName,
                    'custom_fields_type_values_id' => $fieldType,
                    'updated_at' => now(),
                ]);

                // Clear previous structure and re-save new structure
                CustomFieldsStructure::where('custom_field_id', $customField->id)->delete();

                // Save new single and multi-selection values
                $this->saveCustomFieldStructure($customField, $singleSelection, 'single');
                $this->saveCustomFieldStructure($customField, $multiSelection, 'multi');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Custom field updated successfully.',
                ], 200);
            }

            // Case: Create new custom field (fieldid is 0 or not provided)
            // Check if the custom field already exists for the given property
            $existingField = CustomField::where('property_id', $propertyId)
                ->where('name', $fieldName)
                ->first();

            if ($existingField) {
                // If the field already exists, return a response with an error message
                return response()->json([
                    'status' => 'error',
                    'message' => 'Custom field with the same name already exists for this property.',
                ], 200);
            }

            // Create a new custom field
            $customField = CustomField::create([
                'property_id' => $propertyId,
                'name' => $fieldName,
                'custom_fields_type_values_id' => $fieldType,
            ]);

            // Save the single and multi-selection values in the custom_fields_structures table
            $this->saveCustomFieldStructure($customField, $singleSelection, 'single');
            $this->saveCustomFieldStructure($customField, $multiSelection, 'multi');

            return response()->json([
                'status' => 'success',
                'message' => 'Custom field added successfully.',
            ], 200);
        } catch (\Exception $e) {
            Helper::errorLog('addCustomFields', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }

    // Common method to save custom field structure
    protected function saveCustomFieldStructure($customField, $values, $valueType)
    {
        if (!empty($values)) {
            foreach ($values as $value) {
                // Check if the value already exists
                if (CustomFieldsStructure::where('custom_field_id', $customField->id)
                    ->where('value', $value)
                    ->exists()
                ) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Duplicate value detected in ' . $valueType . ' selection.',
                    ], 200);
                }

                // If not, create the structure entry
                CustomFieldsStructure::create([
                    'custom_field_id' => $customField->id,
                    'value' => $value,
                ]);
            }
        }
    }


    public function getCustomFields($pid)
    {
        try {
            if ($pid != 'null') {
                $customFields = CustomField::where('property_id', $pid)
                    ->with('customFieldStructures', 'typeValue')
                    ->orderBy('id', 'desc')   // Eager load custom field structures (if needed)
                    ->get();


                // Transform custom fields to include value_type and value_type_name from typeValue
                $customFields = $customFields->map(function ($customField) {
                    // If typeValue exists, transform it
                    if ($customField->typeValue) {
                        $customField->value_type = $customField->typeValue->id; // assuming id is the value_type
                        $customField->value_type_name = $customField->typeValue->type; // assuming type is the value_type_name
                    } else {
                        // If no typeValue, set default values or handle the absence
                        $customField->value_type = null;
                        $customField->value_type_name = null;
                    }

                    // Optionally, you can remove the typeValue object from the response
                    unset($customField->typeValue);

                    return $customField;
                });

                // Check if custom fields are found
                if ($customFields->isEmpty()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No custom fields found for this property.',
                    ], 200);
                }

                // Return success response with the fetched custom fields
                return $customFields;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            Helper::errorLog('getCustomFields', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }

    public function getCustomFieldWithLeadValues($pid, $lid)
    {
        try {

            // Fetch custom fields for the given property ID with related structures and type values
            $customFields = CustomField::where('property_id', $pid)
                ->with(['typeValue', 'customFieldValues' => function ($query) use ($lid) {
                    $query->where('leads_customers_id', $lid);
                }, 'customFieldStructures'])
                ->get();

            // Check if custom fields are found
            if ($customFields->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No custom fields found for this property.',
                ], 200);
            }

            // Transform the custom fields with the respective values
            $customFields = $customFields->map(function ($customField) {
                // Default values to be returned
                $customFieldData = [
                    'id' => $customField->id,
                    'property_id' => $customField->property_id ?? null,
                    'name' => $customField->name,
                    'value_type' => $customField->typeValue ? $customField->typeValue->id : null,
                    'value_type_name' => $customField->typeValue ? $customField->typeValue->type : null,
                    // 'custom_field_structures' => [],
                    'created_at' => $customField->created_at,
                    'value' => null,
                    'CustomFieldValueid' => null
                ];

                // If custom field has values for the lead, map the values
                if ($customField->customFieldValues->isNotEmpty()) {
                    $leadValue = $customField->customFieldValues->first(); // Assuming there is only one value per field for the lead
                    $customFieldData['CustomFieldValueid'] = $leadValue->id;

                    // Depending on the field type, set the appropriate value
                    switch ($customField->typeValue->id) {
                        case 1: // For "Small Text", use text_value
                            $customFieldData['value'] = $leadValue->small_text_value;
                            break;
                            
                        case 2: // For "long Text", use text_value
                                $customFieldData['value'] = $leadValue->text_value;
                                break;
                        case 3: // For "int Text", use int_value
                                $customFieldData['value'] = $leadValue->int_value;
                                break;
                        case 4: // For "date Text", use date
                                $customFieldData['value'] = $leadValue->date_value;
                                break;
                        case 5: // For "Single Selection" 
                            $customFieldData['value'] = $leadValue->custom_fields_structure_id; 
                            break;
                        case 6: // For "Multi Selection"
                            $customFieldData['value'] = $customField->customFieldValues
                            ->pluck('custom_fields_structure_id') // Pluck all the selected structure IDs
                            ->toArray();
                            // $customFieldData['value'] = $leadValue->customFieldStructure; // Multi Selection
                            break;
                            // Add more cases for other types if needed
                        default:
                            $customFieldData['value'] = $leadValue->int_value ?? null; // default fallback
                    }
                }

                // Add custom field structures for multi-selection or other types
                $customFieldData['custom_field_structures'] = $customField->customFieldStructures->map(function ($structure) {
                    return [
                        'id' => $structure->id,
                        'value' => $structure->value,
                        'created_at' => $structure->created_at,
                        'updated_at' => $structure->updated_at
                    ];
                });

                return $customFieldData;
            });

            // Return the custom fields with their respective lead values
            return response()->json($customFields, 200);
        } catch (\Exception $e) {
            Helper::errorLog('getCustomFieldWithLeadValues', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }


    

    public function fetchCustomField($cfid)
    {
        try {
            if ($cfid != 'null') {
                $customFieldDetail = CustomField::with('customFieldStructures', 'typeValue') // Eager load custom field structures (if needed)
                    ->where('id', $cfid)
                    ->first();


                if ($customFieldDetail) {
                    // Transform tags to include only names
                    $customFieldStructure = $customFieldDetail->customFieldStructures->pluck('value')->toArray();
                    $customFieldDetail = $customFieldDetail->toArray(); // Convert to array
                    $customFieldDetail['custom_field_structures'] = $customFieldStructure;
                }
                // Return success response with the fetched custom fields
                return $customFieldDetail;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            Helper::errorLog('fetchCustomField', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }

    public function removeCustomField(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'propertyId' => 'required',  // Ensure propertyId is valid
                'fieldId' => 'required',    // Ensure fieldId is valid
            ]);

            $propertyId = $validatedData['propertyId'];
            $fieldId = $validatedData['fieldId'];

            // Fetch the custom field by fieldId and propertyId
            $customField = CustomField::where('id', $fieldId)
                ->where('property_id', $propertyId)
                ->first();

            if (!$customField) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Custom field not found for this property.',
                ], 404);
            }

            // Delete related records in `custom_fields_structures`
            CustomFieldsStructure::where('custom_field_id', $customField->id)->delete();

            // Delete related records in `custom_fields_values`
            CustomFieldsValue::where('custom_field_id', $customField->id)
                ->where('property_id', $propertyId)
                ->delete();

            // Delete the custom field itself
            $customField->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Custom field and all related data removed successfully.',
            ], 200);
        } catch (\Exception $e) {
            Helper::errorLog('removeCustomField', $e->getLine() . $e->getMessage(), 'high');
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }

    //common function for saving customfields based on leads
    public static function saveCustomFieldData($propertyId, $leadId, $customFieldData)
    {
        foreach ($customFieldData as $customField) {
            $customFieldId = $customField['custom_field_id'];
            $valueType = $customField['value_type'];
            $value = $customField['value'];
            $customFieldStructureId = $customField['custom_field_structure_id'] ?? null;

            // Fetch the custom field type using Eloquent
            $fieldType = CustomFieldsTypeValue::find($valueType)->type ?? null;

            // Remove existing values for the current custom field
            CustomFieldsValue::where('property_id', $propertyId)
                ->where('leads_customers_id', $leadId)
                ->where('custom_field_id', $customFieldId)
                ->delete();

            if ($fieldType == 'Single Selection' || $fieldType == 'Multi Selection') {
                if ($customFieldStructureId) {
                    $structureIds = explode(',', $customFieldStructureId);
                    $values = explode(',', $value);

                    foreach ($structureIds as $index => $structureId) {
                        $customstructurefieldname=CustomFieldsStructure::where('id',$structureId)->first();
                        CustomFieldsValue::create([
                            'property_id' => $propertyId,
                            'leads_customers_id' => $leadId,
                            'custom_fields_type_values_id' => $valueType,
                            'custom_field_id' => $customFieldId,
                            'custom_fields_structure_id' => $structureId,
                            'text_value' => $customstructurefieldname->value ?? null,
                        ]);
                    }
                }
            } elseif ($fieldType == 'Long Text') {
                CustomFieldsValue::create([
                    'property_id' => $propertyId,
                    'leads_customers_id' => $leadId,
                    'custom_fields_type_values_id' => $valueType,
                    'custom_field_id' => $customFieldId,
                    'text_value' => $value,
                ]);
            } elseif ($fieldType == 'Date') {
                CustomFieldsValue::create([
                    'property_id' => $propertyId,
                    'leads_customers_id' => $leadId,
                    'custom_fields_type_values_id' => $valueType,
                    'custom_field_id' => $customFieldId,
                    'date_value' => $value,
                ]);
            } elseif ($fieldType == 'Small Text(max character 100)') {
                CustomFieldsValue::create([
                    'property_id' => $propertyId,
                    'leads_customers_id' => $leadId,
                    'custom_fields_type_values_id' => $valueType,
                    'custom_field_id' => $customFieldId,
                    'small_text_value' => $value,
                ]);
            } elseif ($fieldType == 'Number') {
                CustomFieldsValue::create([
                    'property_id' => $propertyId,
                    'leads_customers_id' => $leadId,
                    'custom_fields_type_values_id' => $valueType,
                    'custom_field_id' => $customFieldId,
                    'int_value' => $value,
                ]);
            } else {
                CustomFieldsValue::create([
                    'property_id' => $propertyId,
                    'leads_customers_id' => $leadId,
                    'custom_fields_type_values_id' => $valueType,
                    'custom_field_id' => $customFieldId,
                    'text_value' => $value,
                ]);
            }
        }
    }
}
