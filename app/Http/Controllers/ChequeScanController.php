<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Storage;
use App\Helper;
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use App\Models\PaymentTransaction;
use App\Models\UnitDetail;
use App\Models\WingDetail;
use App\Models\LeadCustomer;
use App\Models\LeadCustomerUnit;
use App\Models\LeadCustomerUnitData;
use App\Models\LeadSource;
use Exception;

class ChequeScanController extends Controller
{

    public function detectCheque(Request $request)
    {
        try {
            $request->validate(['image' => 'required|image']);
            $propertyID = $request->input('propertyID');

            $imageContent = file_get_contents($request->file('image')->getRealPath());
            $client = new DocumentProcessorServiceClient([
                'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
            ]);
            $rawDocument = (new RawDocument())
                ->setContent($imageContent)
                ->setMimeType($request->file('image')->getMimeType());

            $processRequest = (new ProcessRequest())
                ->setName("projects/cloud-vision-438307/locations/us/processors/1a6eb05828f2b041")
                ->setRawDocument($rawDocument);

            $response = $client->processDocument($processRequest);
            $document = $response->getDocument();
            $entities = $document->getEntities();


            $username = null;
            $amount = null;
            $entitiesArray = [];

            foreach ($entities as $entity) {
                $entitiesArray[] = [
                    'type' => $entity->getType(),
                    'mention_text' => $entity->getMentionText(),
                ];
            }

            // $entitiesArray = json_decode('[
            //     {
            //         "type": "scan-amount",
            //         "mention_text": "50,25,000"
            //     },
            //     {
            //         "type": "scan-name",
            //         "mention_text": "riya"
            //     },
            //      {
            //         "type": "scan-name",
            //         "mention_text": "prateek"
            //     }
            // ]', true);

            // ,
            //     {
            //         "type": "scan-name",
            //         "mention_text": "parin CHOUDHARY"
            //     },
            //      {
            //         "type": "scan-name",
            //         "mention_text": "deram CHOUDHARY"
            //     },
            //     {
            //         "type": "scan-name",
            //         "mention_text": "yira CHOUDHARY"
            //     }

           $propertyID = 1;



            // Fetch all leads with a type flag
            // Fetch all leads that are either allocated or interested for the specified property
            // Fetch all LeadUnits for the given property ID
            $leadUnits = LeadUnit::whereHas('unit', function ($query) use ($propertyID) {
                $query->where('property_id', $propertyID);
            })
                ->get(['id', 'interested_lead_id', 'allocated_lead_id', 'allocated_customer_id', 'unit_id', 'booking_status', 'created_at', 'updated_at']);

            $entities = [];


            foreach ($leadUnits as $unit) {
                if ($unit->interested_lead_id) {
                    $interestedLeadIds = explode(',', $unit->interested_lead_id);
                    $interestedLeads = Lead::whereIn('id', $interestedLeadIds)
                        ->get(['id', 'property_id', 'name', 'email', 'contact_no', 'source_id', 'budget', 'status', 'type', 'notes', 'created_at', 'updated_at'])
                        ->map(function ($lead) use ($unit) {
                            return array_merge($lead->toArray(), [
                                'lead_type' => 'interested',
                                'unit_id' => $unit->unit_id,
                                'unitMatches' => []
                            ]);
                        })
                        ->toArray();
                    $entities = array_merge($entities, $interestedLeads);
                }

                if ($unit->allocated_lead_id) {
                    $allocatedLeadIds = explode(',', $unit->allocated_lead_id);
                    $allocatedLeads = Lead::whereIn('id', $allocatedLeadIds)
                        ->get(['id', 'property_id', 'name', 'email', 'contact_no', 'source_id', 'budget', 'status', 'type', 'notes', 'created_at', 'updated_at'])
                        ->map(function ($lead) use ($unit) {
                            return array_merge($lead->toArray(), [
                                'lead_type' => 'lead',
                                'unit_id' => $unit->unit_id,
                                'unitMatches' => []  // Initialize with empty unitMatches
                            ]);
                        })
                        ->toArray();
                    $entities = array_merge($entities, $allocatedLeads);
                }

                if ($unit->allocated_customer_id) {
                    $allocatedCustomerIds = explode(',', $unit->allocated_customer_id);
                    $allocatedCustomers = Customer::whereIn('id', $allocatedCustomerIds)
                        ->get(['id', 'property_id', 'unit_id', 'name', 'email', 'contact_no', 'profile_pic', 'created_at', 'updated_at'])
                        ->map(function ($customer) use ($unit) {
                            return array_merge($customer->toArray(), [
                                'lead_type' => 'customer',
                                'unit_id' => $unit->unit_id,
                                'unitMatches' => []
                            ]);
                        })
                        ->toArray();
                    $entities = array_merge($entities, $allocatedCustomers);
                }
            }

            // Retrieve all leads for the property and add them to $entities
            $allLeadsForProperty = Lead::where('property_id', $propertyID)
                ->get(['id', 'property_id', 'name', 'email', 'contact_no', 'source_id', 'budget', 'status', 'type', 'notes', 'created_at', 'updated_at'])
                ->map(function ($lead) {
                    return array_merge($lead->toArray(), [
                        'lead_type' => 'lead',
                        'unitMatches' => []  // Initialize with empty unitMatches
                    ]);
                })
                ->toArray();


            $entities = array_merge($entities, $allLeadsForProperty);

            // Retrieve all customers for the property and add them to $entities
            $allCustomersForProperty = Customer::where('property_id', $propertyID)
                ->get(['id', 'property_id', 'unit_id', 'name', 'email', 'contact_no', 'profile_pic', 'created_at', 'updated_at'])
                ->map(function ($customer) {
                    return array_merge($customer->toArray(), [
                        'lead_type' => 'customer',
                        'unitMatches' => []  // Initialize with empty unitMatches
                    ]);
                })
                ->toArray();

            // Merge customers with entities
            $entities = array_merge($entities, $allCustomersForProperty);
            // Avoid duplicate entries by using unique IDs
            $entities = collect($entities)->unique(function ($item) {
                return $item['id'] . '|' . $item['lead_type']; // Create a unique key based on id and type
            })->values()->all();
            // $entities = collect($entities)->unique('id')->values()->all();
            // return $entities;
            $allLeads = Lead::where('property_id', $propertyID)->get();
            $validEntities = []; // Array to hold entities that match the name criteria
            $addedIds = []; // Array to track added IDs to avoid duplicates 

            // Initialize an array to keep track of unique combinations of id and lead_type
            $uniqueIdsAndTypes = [];
            $processedParts = [];
            foreach ($entitiesArray as $entityamt) {

                if ($entityamt['type'] == 'scan-amount' && preg_match('/\d/', $entityamt['mention_text'])) {
                    $amount = $entityamt['mention_text'];
                }
            }
         
            foreach ($entities as &$entity) {
                if ($entity['lead_type'] === 'interested') {
                    continue; // Skip interested types
                }

                foreach ($entitiesArray as $scanEntity) {
                    if ($scanEntity['type'] == 'scan-name') {
                        $name  = $scanEntity['mention_text'];                     
                        $nameParts = explode(' ', $name );

                        

                        foreach ($nameParts as $part) {

                            if (in_array($part, $processedParts)) {
                                continue; // Skip already processed part
                            }
                            $processedParts[] = $part;
                         
                            // echo $part;
                            // Fetch matching leads and customers for each part
                            $leadResults = Lead::where('property_id', $propertyID)
                                ->where('name', 'LIKE', '%' . $part . '%')
                                ->get();

                            $customerResults = Customer::where('property_id', $propertyID)
                                ->where('name', 'LIKE', '%' . $part . '%')
                                ->get();

                         
                            $queryResults = $leadResults->concat($customerResults);

                        

                            foreach ($queryResults as $result) {

                                // if (!in_array($result->id, array_column($validEntities, 'id'))) {
                                //     // Add the result to validEntities if it's not already present
                                //     $validEntities[] = $result;
                                // }



                                // Assign lead type
                                // $result->lead_type = isset($result->source_id) ? 'lead' : 'customer';

                                

                                // // Create a unique key combining ID and lead type
                                // $uniqueKey = $result->id . '|' . $result->lead_type;

                                // // Check if the unique key already exists before adding
                                // if (!in_array($uniqueKey, $addedIds)) {
                                //     $validEntities[] = $result; // Add to valid entities
                                //     $addedIds[] = $uniqueKey; // Mark this combination as added
                                // }

                                $result->lead_type = isset($result->source_id) ? 'lead' : 'customer';

                                // Create a unique key combining ID and lead type
                                $uniqueKey = $result->id . '|' . $result->lead_type;
                            
                                // Check if the unique key already exists before adding
                                if (!in_array($uniqueKey, $addedIds)) {
                                    // Initialize unitMatches array as an empty array
                                    $unitMatches = [];
                            
                                    // Fetch the unit based on allocated_id and allocated_type
                                    $paymentTransaction = PaymentTransaction::where('allocated_id', $result->id)
                                        ->where('allocated_type', $result->lead_type === 'lead' ? 1 : 2)
                                        ->where('property_id', $propertyID)
                                        ->first();
                            
                                    if ($paymentTransaction) {
                                        // Fetch unit details
                                        $unitDetail = UnitDetail::where('id', $paymentTransaction->unit_id)->first();
                                        if ($unitDetail) {
                                            $wing = WingDetail::find($unitDetail->wing_id);
                                            $unitMatches[] = [
                                                'unit_id' => $unitDetail->id,
                                                'unit_name' => $unitDetail->name,
                                                'wing_id' => $unitDetail->wing_id,
                                                'wing_name' => $wing ? $wing->name : null,
                                            ];
                                        }
                                    }
                            
                                    // Add the unitMatches to the result as an additional field
                                    $validEntity = $result->toArray(); // Convert result to array
                                    $validEntity['unitMatches'] = $unitMatches; // Add unitMatches to the array
                            
                                    $validEntities[] = $validEntity; // Add to valid entities
                                    $addedIds[] = $uniqueKey; // Mark this combination as added
                                }
                                
                            }
                        }
                    }
                }
            }

            // Update $results to contain only valid entities that matched names
            $results = array_values($validEntities);



            $client->close();

            return response()->json([
                'matchedLeads' => $results ?? null,
                'allLeads' => $allLeads ?? null,
                'amount' => $amount ?? null,
                'status' => 'success',

            ]);
        } catch (Exception $e) {

            return response()->json([
                'matchedLeads' => $results ?? null,
                'allLeads' => $allLeads ?? null,
                'amount' => $amount ?? null,
                'status' => 'error',
                'msg'=> $e->getMessage(),
            ]);
        }
    }
}


// if (strcasecmp($result->name, $entity['name']) == 0) {
                                //     $unitDetail = UnitDetail::where('id', $entity['unit_id'])->first();

                                //     if ($unitDetail) {
                                //         $wing = WingDetail::find($unitDetail->wing_id);

                                //         $entity['unitMatches'][] = [
                                //             'unit_id' => $unitDetail->id,
                                //             'unit_name' => $unitDetail->name,
                                //             'wing_id' => $unitDetail->wing_id,
                                //             'wing_name' => $wing ? $wing->name : null
                                //         ];

                                //         if (!isset($validEntities[$result->id])) {
                                //             $validEntities[$result->id] = $result;
                                //         }
                                //     }
                                // }