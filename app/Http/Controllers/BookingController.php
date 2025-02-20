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
use App\Models\Status;
use App\Models\Amenity;
use App\Models\Country;
use App\Models\LeadCustomer;
use App\Models\LeadCustomerUnit;
use App\Models\LeadCustomerUnitData;
use App\Models\PaymentTransaction;
use App\Models\PaymentType;
use App\Models\State;
use Exception;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{

    public function getBookedUnitDetail($uid, $bid, $type)
    {

        //uid ->unit detail id,bid-> customer/lead id, type-> lead/customer
        try {
            // Check if uid and type are not null
            if ($uid === 'null' || $type === 'null') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid parameters provided.',
                ], 200);
            }

            // Initialize the response data
            $responseData = [];


            $leadUnit = LeadCustomerUnit::where('unit_id', $uid)->first();
            if (!$leadUnit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found for the provided unit ID.',
                ], 404);
            }

            // Determine the contact details from Lead or Customer based on allocation fields
            if (!is_null($leadUnit->leads_customers_id)) {
                $contact = LeadCustomer::find($leadUnit->leads_customers_id);
            }

            // Populate contact details if available
            if (isset($contact)) {
                $responseData['contact_name'] = $contact->name;
                $responseData['contact_email'] = $contact->email ?? null;
                $responseData['contact_number'] = $contact->contact_no;
                $responseData['notes'] = $contact->notes ?? null;
            }


            // Retrieve the LeadUnit with the necessary relationships
            $leadUnit = LeadCustomerUnit::with(['paymentTransaction' => function ($query) {
                $query->orderBy('id', 'asc'); // Order by transaction ID in ascending order
            }])
                ->where('unit_id', $uid)
                ->first();

            if (!$leadUnit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit not found for the provided unit ID.',
                ], 404);
            }

            // Retrieve the payment transactions
            $paymentTransactions = $leadUnit->paymentTransaction()->get();

            // Initialize variables for total paid amount and response data for contacts
            $totalPaidAmount = 0;
            $contactId = null;
            $responseData['payment_schedule'] = [];
            $isFirstTransaction = true;

            // Loop through payment transactions to get contact details based on allocated_id
            foreach ($paymentTransactions as $index => $transaction) {
                // if ($transaction->allocated_type == 1) { // If it's a lead
                //     $contact = Lead::find($transaction->allocated_id);
                // } elseif ($transaction->allocated_type == 2) { // If it's a customer
                //     $contact = Customer::find($transaction->allocated_id);
                // }

                // if ($contact) {
                //     // Populate contact details only once
                //     if (empty($responseData['contact_name'])) {
                //         $responseData['contact_name'] = $contact->name;
                //         $responseData['contact_email'] = $contact->email;
                //         $responseData['contact_number'] = $contact->contact_no;
                //     }
                // }

                // Sum up the total paid amount for completed transactions
                if ($transaction->payment_status == 2) {
                    if ($isFirstTransaction) {
                        $totalPaidAmount += $transaction->token_amt;
                        $isFirstTransaction = false;
                    } else {
                        $totalPaidAmount += $transaction->next_payable_amt;
                    }
                }

                // Prepare payment schedule
                $paymentType = PaymentType::find($transaction->payment_type);
                $paymentTypeName = $paymentType ? $paymentType->name : '';
                $paymentTypeId = $paymentType ? $paymentType->id : '';

                if ($transaction->token_amt || $transaction->next_payable_amt || $transaction->booking_date || $transaction->payment_due_date) {
                    // Only add the object if it has at least one non-null value
                    $paymentScheduleEntry = [
                        'payment_id' => $transaction->id,
                        'payment_due_date' => $index == 0 ? $transaction->booking_date : $transaction->payment_due_date,
                        'next_payable_amt' => $index == 0 ? $transaction->token_amt : $transaction->next_payable_amt,
                        'payment_status' => $transaction->payment_status,
                        // 'type' => $index == 0 ? 'Down Payment' : ($transaction->payment_status == 1 ? 'Next Payment' : ($transaction->payment_status == 2 ? 'Last Payment' : '')),
                        'type' => $paymentTypeName,
                        'type_id' => $paymentTypeId,
                        'reference_number' => $transaction->reference_number
                    ];

                    // Check if either next_payable_amt or payment_due_date is not null
                    if (!is_null($paymentScheduleEntry['next_payable_amt']) || !is_null($paymentScheduleEntry['payment_due_date'])) {
                        $responseData['payment_schedule'][] = $paymentScheduleEntry;
                    }
                }
                // if ($transaction->token_amt || $transaction->next_payable_amt || $transaction->booking_date || $transaction->payment_due_date) {
                //     if ($index == 0) {
                //         $responseData['payment_schedule'][] = [
                //             'payment_due_date' => $transaction->booking_date,
                //             'next_payable_amt' => $transaction->token_amt,
                //             'payment_status' => $transaction->payment_status,
                //         ];
                //     } else {
                //         $responseData['payment_schedule'][] = [
                //             'payment_due_date' => $transaction->payment_due_date,
                //             'next_payable_amt' => $transaction->next_payable_amt,
                //             'payment_status' => $transaction->payment_status,
                //         ];
                //     }
                // }
            }

            $responseData['total_paid_amount'] = $totalPaidAmount;

            // Fetch unit details if they exist
            if ($leadUnit->unit) {
                $unitDetail = $leadUnit->unit;
                $responseData['unit_details'] = [
                    'wing_name' => $unitDetail->wingDetail->name ?? null,
                    'unit_name' => $unitDetail->name ?? null,
                    'unit_size' => $unitDetail->square_feet ?? null,
                    'unit_price' => $unitDetail->price ?? null,
                ];
            } else {
                $responseData['unit_details'] = null;
            }

            // Add total amount from the latest transaction if it exists
            $latestTransaction = $paymentTransactions->last();
            $responseData['total_amt'] = $latestTransaction->amount ?? null;

            return response()->json([
                'status' => 'success',
                'data' => $responseData,
            ], 200);
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getBookedUnitDetail';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public function addUnitBookingInfo(Request $request)
    {

        try {
            $unitId = $request->input(key: 'unit_id');
            $propertyId = $request->input('property_id');
            $entityId = $request->input('entity_id'); // Now used for lead_id or customer_id based on $type
            $contactName = $request->input('contact_name');
            $contactEmail = $request->input('contact_email');
            $contactNumber = $request->input('contact_number');
            $bookingDate = $request->input('booking_date');
            $tokenAmt = $request->input('token_amt');
            $paymentDueDate = $request->input('payment_due_date');
            $nextPayableAmt = $request->input('next_payable_amt');
            $type = $request->input('type'); // 'lead' or 'customer'
            $bookingpaymenttype = $request->input('bookingpaymenttype');
            $bookingreferencenumber = $request->input('bookingreferencenumber');
            $nextpaymenttype = $request->input('nextpaymenttype');
            $nextpaymentreferencenumber = $request->input('nextpaymentreferencenumber');
            $notes = $request->input('notes');
            $userId = $request->input('userId');


            $leadUnit = LeadCustomerUnit::where('unit_id', $unitId)->first();

            // Determine which IDs are allocated based on the $type
            $allocatedIds = ($leadUnit && $leadUnit->leads_customers_id ? explode(',', $leadUnit->leads_customers_id) : []);


            if (is_null($entityId)) {
                // Null entity ID - handle as new customer
                $leadcustomer = LeadCustomer::where('property_id', $propertyId)
                    // ->where('unit_id', $unitId)
                    ->where('contact_no', $contactNumber)
                    ->first();

                if ($leadcustomer) {
                    // Update the existing customer's name if it's different
                    return response()->json([
                        'status' => 'error',
                        'message' => $leadcustomer->name . ' is already added with this contact no.',
                        'data' => null
                    ], 200);
                } else {
                    // Create a new customer if no existing customer with the same contact number was found
                    $leadcustomer = leadcustomer::create([
                        'property_id' => $propertyId,
                        'user_id' => $userId,
                        'email' => $contactEmail,
                        'name' => $contactName,
                        'contact_no' => $contactNumber,
                        'entity_type' => 2,
                        'notes' => $notes,
                        'status_id' => 1
                    ]);
                }

                // Update allocated_customer_id in lead_unit
                $leadUnit = $leadUnit ?: new LeadCustomerUnit();
                $leadUnit->unit_id = $unitId;
                if (!in_array($leadcustomer->id, $allocatedIds)) {
                    $allocatedIds[] = $leadcustomer->id;
                    $leadUnit->leads_customers_id = implode(',', $allocatedIds);
                }
                $leadUnit->booking_status = 4;
                $leadUnit->save();

                $allocatedId = $leadcustomer->id;
            } else {
                // Provided entity ID - handle as lead or customer based on type

                $leadcustomer = LeadCustomer::find($entityId);
                if (!$leadcustomer) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Lead/Customer not found',
                    ], 200);
                }

                if( $leadcustomer->entity_type==1){
                    Leadcustomer::where('id', $entityId)->update(['entity_type' => 2,'status_id' => 2]);
                }
                
                $leadUnit = $leadUnit ?: new LeadCustomerUnit();
                $leadUnit->unit_id = $unitId;
                if (!in_array($entityId, $allocatedIds)) {
                    $allocatedIds[] = $entityId;
                    $leadUnit->leads_customers_id = implode(',', $allocatedIds);
                }
                $leadUnit->booking_status = 4;
                $leadUnit->save();

                $allocatedId = $entityId;
            }

            // Create the first payment transaction entry

            if ($tokenAmt != null) {
                $paymentTransaction = new PaymentTransaction();
                $paymentTransaction->unit_id = $unitId;
                $paymentTransaction->property_id = $propertyId;
                $paymentTransaction->leads_customers_id = $allocatedId;
                $paymentTransaction->booking_date = $bookingDate;
                $paymentTransaction->token_amt = $tokenAmt;
                $paymentTransaction->payment_status = 2;
                $paymentTransaction->payment_type = $bookingpaymenttype; // Use bookingpaymenttype for the first transaction
                $paymentTransaction->reference_number = $bookingreferencenumber; // Set reference number for first transaction
                $paymentTransaction->transaction_notes = "Booking entry saved";
                $paymentTransaction->save();
            }


            // Handle additional transaction for next payment if applicable
            if ($nextPayableAmt) {
                $paymentTransactionSecond = new PaymentTransaction();
                $paymentTransactionSecond->unit_id = $unitId;
                $paymentTransactionSecond->property_id = $propertyId;
                $paymentTransactionSecond->leads_customers_id = $allocatedId;
                $paymentTransactionSecond->booking_date = $bookingDate;
                $paymentTransactionSecond->payment_due_date = $paymentDueDate;
                $paymentTransactionSecond->token_amt = $tokenAmt;
                $paymentTransactionSecond->next_payable_amt = $nextPayableAmt;

                if ($paymentDueDate) {
                    $paymentDueDateObj = \Carbon\Carbon::parse($paymentDueDate);
                    $paymentTransactionSecond->payment_status = $paymentDueDateObj->isFuture() ? 1 : 2;
                } else {
                    $paymentTransactionSecond->payment_status = 1;
                }

                $paymentTransactionSecond->payment_type = $nextpaymenttype; // Use nextpaymenttype for the next transaction
                $paymentTransactionSecond->reference_number = $nextpaymentreferencenumber; // Set reference number for the next transaction
                $paymentTransactionSecond->transaction_notes = "Next payment saved";
                $paymentTransactionSecond->save();
            }


            // Retrieve all payment transactions for the unit
            $paymentTransactions = PaymentTransaction::where('unit_id', $unitId)
                ->where('payment_status', 2)
                ->get();

            // Calculate the total for next_payable_amt
            $totalNextPayableAmt = $paymentTransactions->sum('next_payable_amt');

            // Retrieve the first payment transaction to include token_amt
            $firstPaymentTransaction = $paymentTransactions->first();
            if ($firstPaymentTransaction) {
                // Add the token_amt of the first entry to the total next_payable_amt
                $totalNextPayableAmt += $firstPaymentTransaction->token_amt;
            }

            $leadUnit = LeadCustomerUnit::where('unit_id', $unitId)->first();
            $unitdata = UnitDetail::where('id', $unitId)->first();
            // Update LeadUnit booking status if totalNextPayableAmt reaches or exceeds the required amount
            if ($unitdata->price != '' && $leadUnit != '') {
                // if ($lastPaymentTransaction && $totalNextPayableAmt >= $lastPaymentTransaction->amount) {
                //     $leadUnit->booking_status = 3; // Mark as confirmed
                //     $leadUnit->save();
                // }
                if ($totalNextPayableAmt >= $unitdata->price) {
                    if ($unitdata->price <= 0) {
                        $leadUnit->booking_status = 4; // Mark as pending
                    } else {
                        $leadUnit->booking_status = 3; // Mark as confirmed
                    }
                    $leadUnit->save();
                }
                $leadUnit->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Unit booking information saved successfully',
            ], 200);
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'addUnitBookingInfo';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving the data',
            ], 400);
        }
    }

    public function addUnitPaymentDetail(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'amount' => 'required|numeric',
                'date' => 'required|date',
                'unit_id' => 'required|integer',
                'payment_id' => 'nullable|integer',
                'bookingpaymenttype' => 'nullable|integer',
                'bookingreferencenumber' => 'nullable|numeric',
                'userid' => 'required|integer' //lead/customerid
            ]);

            $amount = $validatedData['amount'];
            $paymentDate = $validatedData['date'];
            $unitId = $validatedData['unit_id'];
            $paymentId = $validatedData['payment_id'];
            $bookingpaymenttype = $validatedData['bookingpaymenttype'];
            $bookingreferencenumber = $validatedData['bookingreferencenumber'];
            $leadscustomerid = $validatedData['userid'];


            // Retrieve the LeadUnit associated with the unit_id
            $leadUnit = LeadCustomerUnit::where('unit_id', $unitId)->first();
            $unitdata = UnitDetail::where('id', $unitId)->first();

            if (!$leadUnit) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lead Unit not found.',
                ], 200);
            }

            $lastPaymentTransaction = PaymentTransaction::where('unit_id', $unitId)
                ->orderBy('created_at', 'desc') // Get the most recent transaction
                ->first();


            if ($paymentId != null) {
                // Update existing payment record
                $paymentTransaction = PaymentTransaction::find($paymentId);

                if (!$paymentTransaction) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Payment transaction not found.',
                    ], 200);
                }

                // Update payment details
                $paymentTransaction->leads_customers_id = $leadscustomerid;
                $paymentTransaction->next_payable_amt = $amount; // Assuming amount is the next payable amount
                $paymentTransaction->payment_due_date = $paymentDate;
                $paymentTransaction->payment_type = $bookingpaymenttype; // Use bookingpaymenttype for the first transaction
                $paymentTransaction->reference_number = $bookingreferencenumber;
                // $paymentTransaction->payment_status = now()->gt($paymentDate) ? 1 : 2;
                $paymentTransaction->payment_status = 2;
                $paymentTransaction->save();
            } else {

                // Create a new payment record or update existing one based on conditions
                $previousPayments = PaymentTransaction::where('unit_id', $unitId)->get();
                // return $previousPayments;
                $existingPayment = $previousPayments->first();

                // Check if booking_date and token_amt are both null and have only one previous entry
                if ($existingPayment) {
                    if ((is_null($existingPayment->token_amt)) && $previousPayments->count() == 1) {
                        // Update the existing payment entry
                        $existingPayment->leads_customers_id = $existingPayment->leads_customers_id;
                        $existingPayment->token_amt = $amount; // Update token_amt
                        $existingPayment->booking_date = $paymentDate; // Update booking_date
                        $existingPayment->payment_type = $bookingpaymenttype; // Use bookingpaymenttype for the first transaction
                        $existingPayment->reference_number = $bookingreferencenumber;
                        $existingPayment->payment_status = 2;
                        $existingPayment->save();
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Payment details updated successfully.',
                        ], 200);
                    } else {
                        // Create a new payment record
                        // Retrieve the last payment transaction for the unit


                        $paymentTransaction = new PaymentTransaction();
                        $paymentTransaction->unit_id = $unitId;
                        $paymentTransaction->payment_due_date = $paymentDate; // The new payment amount
                        $paymentTransaction->next_payable_amt = $amount; // Set the initial amount

                        if ($lastPaymentTransaction) {
                            // Populate fields from the last payment transaction if it exists
                            $paymentTransaction->booking_date = $lastPaymentTransaction->booking_date ?? null; // Use the last booking date
                            $paymentTransaction->token_amt = $lastPaymentTransaction->token_amt ??  null; // Use the last token amount
                            $paymentTransaction->property_id = $lastPaymentTransaction->property_id ?? null; // Use the last payment due date
                            $paymentTransaction->leads_customers_id =  $leadscustomerid;
                            $paymentTransaction->payment_type = $bookingpaymenttype; // Use bookingpaymenttype for the first transaction
                            $paymentTransaction->reference_number = $bookingreferencenumber;
                            $paymentTransaction->transaction_notes = "New payment added";
                            $paymentTransaction->payment_status = now()->gt($paymentDate) ? 2 : 1;
                        } else {
                            $unitDetail = UnitDetail::where('id', $unitId)->first();

                            if ($unitDetail) {
                                $propertyId = $unitDetail->property_id; // Get the property_id
                            } else {
                                // Handle case where no unit_detail is found for the given unit_id
                                $propertyId = null;
                            }

                            $leadUnit = LeadCustomerUnit::where('unit_id', $unitId)->first();

                            if ($leadUnit) {
                                // Check for allocated_lead_id or allocated_customer_id
                                if ($leadUnit->leads_customers_id) {
                                    // If allocated_lead_id exists, set allocated_type to 1 (Lead)
                                    $allocatedId = $leadUnit->leads_customers_id;
                                }
                            } else {
                                // Handle case where no LeadUnit is found for the given unit_id
                                $allocatedId = null;
                            }
                        }

                        // Set the initial payment status

                        $paymentTransaction->save();
                    }
                } else {
                    $unitDetail = UnitDetail::where('id', $unitId)->first();

                    if ($unitDetail) {
                        $propertyId = $unitDetail->property_id; // Get the property_id
                    } else {
                        // Handle case where no unit_detail is found for the given unit_id
                        $propertyId = null;
                    }
                    //down payment adding if not
                    $paymentTransaction = new PaymentTransaction();
                    $paymentTransaction->unit_id = $unitId;
                    $paymentTransaction->property_id = $propertyId;
                    $paymentTransaction->leads_customers_id = $leadscustomerid;
                    $paymentTransaction->booking_date = $paymentDate;
                    $paymentTransaction->token_amt = $amount;
                    $paymentTransaction->payment_status = 2;
                    $paymentTransaction->payment_type = $bookingpaymenttype; // Use bookingpaymenttype for the first transaction
                    $paymentTransaction->reference_number = $bookingreferencenumber; // Set reference number for first transaction
                    $paymentTransaction->transaction_notes = 'Booking entry created';
                    $paymentTransaction->save();
                }
            }


            // Retrieve all payment transactions for the unit
            $paymentTransactions = PaymentTransaction::where('unit_id', $unitId)
                ->where('payment_status', 2)
                ->get();

            // Calculate the total for next_payable_amt
            $totalNextPayableAmt = $paymentTransactions->sum('next_payable_amt');

            // Retrieve the first payment transaction to include token_amt
            $firstPaymentTransaction = $paymentTransactions->first();
            if ($firstPaymentTransaction) {
                // Add the token_amt of the first entry to the total next_payable_amt
                $totalNextPayableAmt += $firstPaymentTransaction->token_amt;
            }

            // Update LeadUnit booking status if totalNextPayableAmt reaches or exceeds the required amount
            if ($unitdata->price != '' && $leadUnit != '') {
                // if ($lastPaymentTransaction && $totalNextPayableAmt >= $lastPaymentTransaction->amount) {
                //     $leadUnit->booking_status = 3; // Mark as confirmed
                //     $leadUnit->save();
                // }

                if ($totalNextPayableAmt >= $unitdata->price) {
                    if ($unitdata->price <= 0) {
                        $leadUnit->booking_status = 4; // Mark as pending
                    } else {
                        $leadUnit->booking_status = 3; // Mark as confirmed
                    }
                    $leadUnit->save();
                }
                $leadUnit->save();
            }


            return response()->json([
                'status' => 'success',
                'message' => 'Payment details added/updated successfully.',
            ], 200);
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'addUnitPaymentDetail';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    // public function addEntityAttachWithUnitsUsingCheque(Request $request)
    // {
    //     try {
    //         $unitId = $request->input('unit_id');
    //         $propertyId = $request->input('property_id');
    //         $leadId = $request->input('lead_id');
    //         $contactName = $request->input('contact_name');
    //         $contactEmail = $request->input('contact_email');
    //         $contactNumber = $request->input('contact_number');
    //         $nextPayableAmt = $request->input('next_payable_amt');
    //         $totalAmt = $request->input('total_amt');
    //         $flag = $request->input('flag');

    //         // Check if lead_unit entry exists for the given unit_id
    //         $leadUnit = LeadUnit::where('unit_id', $unitId)->first();

    //         $allocatedLeadIds = $leadUnit && $leadUnit->allocated_lead_id ? explode(',', $leadUnit->allocated_lead_id) : [];
    //         $allocatedCustomerIds = $leadUnit && $leadUnit->allocated_customer_id ? explode(',', $leadUnit->allocated_customer_id) : [];

    //         // Flag-specific logic
    //         if ($flag == 1) {
    //             // If the unit has any associated lead or customer, return a matched status
    //             if (!empty($allocatedLeadIds) || !empty($allocatedCustomerIds)) {
    //                 $names = !empty($allocatedCustomerIds)
    //                     ? Customer::whereIn('id', $allocatedCustomerIds)->pluck('name')->toArray()
    //                     : Lead::whereIn('id', $allocatedLeadIds)->pluck('name')->toArray();

    //                 return response()->json([
    //                     'status' => 'matched',
    //                     'name' => $names,
    //                 ], 200);
    //             }
    //         }

    //         // Determine allocation based on leadId
    //         $allocatedType = is_null($leadId) ? 2 : 1; // 2 for customer, 1 for lead
    //         $allocatedId = null;

    //         if (is_null($leadId)) {
    //             // Handle new customer logic
    //             $customer = Customer::where('property_id', $propertyId)
    //                 // ->where('unit_id', $unitId)
    //                 ->where('email', $contactEmail)
    //                 ->first();

    //             if ($customer) {
    //                 $customer->name = $contactName;
    //                 $customer->contact_no = $contactNumber;
    //                 $customer->save();
    //             } else {
    //                 $customer = Customer::create([
    //                     'property_id' => $propertyId,
    //                     // 'unit_id' => $unitId,
    //                     'email' => $contactEmail,
    //                     'name' => $contactName,
    //                     'contact_no' => $contactNumber,
    //                 ]);
    //             }

    //             if (!in_array($customer->id, $allocatedCustomerIds)) {
    //                 $allocatedCustomerIds[] = $customer->id;
    //                 $leadUnit->allocated_customer_id = implode(',', $allocatedCustomerIds);
    //             }
    //             $allocatedId = $customer->id;
    //         } else {
    //             // Handle existing lead logic
    //             $lead = Lead::find($leadId);
    //             if (!$lead) {
    //                 return response()->json(['status' => 'error', 'message' => 'Lead not found'], 200);
    //             }

    //             if (!in_array($leadId, $allocatedLeadIds)) {
    //                 $allocatedLeadIds[] = $leadId;
    //                 $leadUnit->allocated_lead_id = implode(',', $allocatedLeadIds);
    //             }
    //             $allocatedId = $leadId;
    //         }

    //         // Update lead_unit and set booking status
    //         $leadUnit = $leadUnit ?: new LeadUnit();
    //         $leadUnit->unit_id = $unitId;
    //         $leadUnit->booking_status = 4; // Set booking status to 4
    //         $leadUnit->save();

    //         // Check and update unit price
    //         $unit = UnitDetail::find($unitId);
    //         if ($unit && !is_null($totalAmt)) {
    //             $unit->price = $totalAmt;
    //             $unit->save();
    //         }

    //         // Log payment transactions
    //         $this->logEntity([
    //             'unit_id' => $unitId,
    //             'property_id' => $propertyId,
    //             'allocated_id' => $allocatedId,
    //             'allocated_type' => $allocatedType,
    //             'next_payable_amt' => $nextPayableAmt,
    //         ]);

    //         return response()->json([
    //             'status' => 'success',
    //             'name' => null
    //         ], 200);
    //     } catch (Exception $e) {
    //         // Log the error
    //         $errorFrom = 'addEntityAttachWithUnitsUsingCheque';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::errorLog($errorFrom, $errorMessage, $priority);

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Not found',
    //         ], 400);
    //     }
    // }


    // public function addMatchedEntityUsingCheque(Request $request)
    // {

    //     try {

    //         // Initialize variables
    //         $propertyId = $request->input('property_id');
    //         $unitId = $request->input('unit_id');
    //         $wingId = $request->input('wing_id');
    //         $leadId = $request->input('id'); // Lead or Customer ID
    //         $leadType = $request->input('lead_type');
    //         $amount = $request->input('amount');
    //         $flag = $request->input('flag');

    //         // Fetch the associated LeadUnit record based on property and unit
    //         $associatedEntity = LeadUnit::where('unit_id', $unitId)
    //             ->with(['unit']) // Eager load the unit relation to access property_id
    //             ->first();



    //         if ($flag == 1) {
    //             // Check if there are any entities attached with the same property and unit
    //             if ($associatedEntity) {
    //                 $matchedNames = [];
    //                 $nameExists = false;

    //                 // Check for leads
    //                 $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
    //                 foreach ($allocatedLeadIds as $allocatedLeadId) {
    //                     $lead = Lead::where('id', $allocatedLeadId)
    //                         ->where('property_id', $propertyId)
    //                         ->first();
    //                     if ($lead) {
    //                         $matchedNames[] = $lead->name; // Collecting names of leads
    //                         if ($allocatedLeadId == $leadId) {
    //                             $nameExists = true;
    //                         }
    //                     }
    //                 }

    //                 // Check for customers
    //                 $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);
    //                 foreach ($allocatedCustomerIds as $allocatedCustomerId) {
    //                     $customer = Customer::where('id', $allocatedCustomerId)
    //                         ->where('property_id', $propertyId)
    //                         ->first();
    //                     if ($customer) {
    //                         $matchedNames[] = $customer->name; // Collecting names of customers
    //                         if ($allocatedCustomerId == $leadId) {
    //                             $nameExists = true;
    //                         }
    //                     }
    //                 }


    //                 if ($nameExists) {
    //                     // Directly log the entity in PaymentTransaction if name exists
    //                     $this->logEntity([
    //                         'unit_id' => $associatedEntity->unit_id,
    //                         'property_id' => $propertyId,
    //                         'allocated_id' => $leadId,
    //                         'allocated_type' => $leadType, // 1 for lead, 2 for customer
    //                         'next_payable_amt' => $amount,
    //                     ]);

    //                     return response()->json([
    //                         'status' => 'success',
    //                         'names' => null,
    //                     ], 200);
    //                 }

    //                 // If any matched names are found, return them
    //                 if (!empty($matchedNames)) {
    //                     return response()->json([
    //                         'status' => 'matched',
    //                         'names' => implode(', ', $matchedNames) // Ensures names are unique
    //                     ], 200);
    //                 }
    //             }


    //             // If no match found, either update existing or create new LeadUnit entry
    //             if ($associatedEntity) {
    //                 // Update allocated IDs if entry exists
    //                 if ($leadType == 'lead') {
    //                     $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
    //                     if (!in_array($leadId, $allocatedLeadIds)) {
    //                         $allocatedLeadIds[] = $leadId;
    //                         $associatedEntity->allocated_lead_id = implode(',', $allocatedLeadIds);
    //                     }
    //                 } elseif ($leadType == 'customer') {
    //                     $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);
    //                     if (!in_array($leadId, $allocatedCustomerIds)) {
    //                         $allocatedCustomerIds[] = $leadId;
    //                         $associatedEntity->allocated_customer_id = implode(',', $allocatedCustomerIds);
    //                     }
    //                 }
    //             } else {
    //                 // Create a new LeadUnit entry if it doesn't exist
    //                 $associatedEntity = new LeadUnit();
    //                 $associatedEntity->unit_id = $unitId;
    //                 $associatedEntity->booking_status = 4; // Set an appropriate default status
    //                 if ($leadType == 'lead') {
    //                     $associatedEntity->allocated_lead_id = $leadId;
    //                 } elseif ($leadType == 'customer') {
    //                     $associatedEntity->allocated_customer_id = $leadId;
    //                 }
    //                 $associatedEntity->save();
    //             }


    //             // If no match found, log the entity
    //             $this->logEntity([
    //                 'unit_id' => $associatedEntity->unit_id,
    //                 'property_id' => $propertyId,
    //                 'allocated_id' => $leadId,
    //                 'allocated_type' => $leadType, // 1 for lead, 2 for customer
    //                 'next_payable_amt' => $amount,
    //             ]);

    //             return response()->json([
    //                 'status' => 'success',
    //                 'names' => null,
    //             ], 200);
    //         } elseif ($flag == 2) { //2 means yes  call 
    //             // Check if there are any entities attached as a lead or customer with this unit
    //             if ($associatedEntity) {
    //                 if ($leadType == 'lead') {
    //                     $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
    //                     if (!in_array($leadId, $allocatedLeadIds)) {
    //                         // Add lead ID to allocated_lead_id
    //                         $allocatedLeadIds[] = $leadId;
    //                         $associatedEntity->allocated_lead_id = implode(',', $allocatedLeadIds);
    //                     }
    //                 } elseif ($leadType == 'customer') {
    //                     $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);
    //                     if (!in_array($leadId, $allocatedCustomerIds)) {
    //                         // Check if `allocated_customer_id` is empty
    //                         if (empty($associatedEntity->allocated_customer_id)) {
    //                             $associatedEntity->allocated_customer_id = $leadId;
    //                         } else {
    //                             $associatedEntity->allocated_customer_id .= ',' . $leadId;
    //                         }
    //                     }
    //                 }

    //                 // Save the updates
    //                 $associatedEntity->save();
    //             } else {
    //                 // No associated entity found; create a new LeadUnit entry
    //                 $associatedEntity = new LeadUnit();
    //                 $associatedEntity->unit_id = $unitId; // Set the unit ID
    //                 $associatedEntity->booking_status = 4; // Set an appropriate default status

    //                 if ($leadType == 'lead') {
    //                     $associatedEntity->allocated_lead_id = $leadId; // Allocate the lead ID
    //                 } elseif ($leadType == 'customer') {
    //                     $associatedEntity->allocated_customer_id = $leadId; // Allocate the customer ID
    //                 }

    //                 // Save the new LeadUnit entry
    //                 $associatedEntity->save();
    //             }

    //             // Log the transaction
    //             $this->logEntity([
    //                 'unit_id' => $associatedEntity->unit_id,
    //                 'property_id' => $propertyId,
    //                 'allocated_id' => $leadId,
    //                 'allocated_type' => $leadType, // 1 for lead, 2 for customer
    //                 'next_payable_amt' => $amount,
    //             ]);

    //             return response()->json([
    //                 'status' => 'success',
    //                 'names' => null,
    //             ], 200);
    //         } elseif ($flag == 3) { //and 3 means no call
    //             // Check if there are any entities attached as a lead or customer with this unit
    //             $matchedNames = [];
    //             if ($associatedEntity) {


    //                 $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
    //                 // Check for leads
    //                 $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);
    //                 foreach ($allocatedLeadIds as $allocatedLeadId) {
    //                     $lead = Lead::where('id', $allocatedLeadId)
    //                         ->where('property_id', $propertyId)
    //                         ->first();
    //                     if ($lead) {
    //                         $matchedNames[] = $lead->name; // Collecting names of leads

    //                     }
    //                 }

    //                 // Check for customers
    //                 $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);
    //                 foreach ($allocatedCustomerIds as $allocatedCustomerId) {
    //                     $customer = Customer::where('id', $allocatedCustomerId)
    //                         ->where('property_id', $propertyId)
    //                         ->first();
    //                     if ($customer) {
    //                         $matchedNames[] = $customer->name; // Collecting names of customers

    //                     }
    //                 }


    //                 if (!empty($matchedNames)) {
    //                     return response()->json([
    //                         'status' => 'matched',
    //                         'names' => implode(', ', $matchedNames) // Ensures names are unique
    //                     ], 200);
    //                 }


    //                 if ($leadType == 'lead') {
    //                     $allocatedLeadIds = explode(',', $associatedEntity->allocated_lead_id);

    //                     if (!in_array($leadId, $allocatedLeadIds)) {
    //                         // Add lead ID to allocated_lead_id
    //                         if (empty($associatedEntity->allocated_lead_id)) {
    //                             // If it's the first entry, set without a comma
    //                             $associatedEntity->allocated_lead_id = $leadId;
    //                         } else {
    //                             // Append with a comma for subsequent entries
    //                             $associatedEntity->allocated_lead_id .= ',' . $leadId;
    //                         }
    //                     }
    //                 } elseif ($leadType == 'customer') {
    //                     $allocatedCustomerIds = explode(',', $associatedEntity->allocated_customer_id);

    //                     if (!in_array($leadId, $allocatedCustomerIds)) {
    //                         if (empty($associatedEntity->allocated_customer_id)) {
    //                             // If it's the first entry, set without a comma
    //                             $associatedEntity->allocated_customer_id = $leadId;
    //                         } else {
    //                             // Append with a comma for subsequent entries
    //                             $associatedEntity->allocated_customer_id .= ',' . $leadId;
    //                         }
    //                     }
    //                 }

    //                 // Save the updates
    //                 $associatedEntity->save();
    //             } else {
    //                 // No associated entity found; create a new LeadUnit entry
    //                 $associatedEntity = new LeadUnit();
    //                 $associatedEntity->unit_id = $unitId; // Set the unit ID
    //                 $associatedEntity->booking_status = 4; // Set an appropriate default status

    //                 if ($leadType == 'lead') {
    //                     $associatedEntity->allocated_lead_id = $leadId; // Allocate the lead ID
    //                 } elseif ($leadType == 'customer') {
    //                     $associatedEntity->allocated_customer_id = $leadId; // Allocate the customer ID
    //                 }

    //                 // Save the new LeadUnit entry
    //                 $associatedEntity->save();
    //             }

    //             // Log the transaction
    //             $this->logEntity([
    //                 'unit_id' => $associatedEntity->unit_id,
    //                 'property_id' => $propertyId,
    //                 'allocated_id' => $leadId,
    //                 'allocated_type' => $leadType, // 1 for lead, 2 for customer
    //                 'next_payable_amt' => $amount,
    //             ]);

    //             return response()->json([
    //                 'status' => 'success',
    //                 'names' => null,
    //             ], 200);
    //         }
    //     } catch (Exception $e) {
    //         // Log the error
    //         $errorFrom = 'addMatchedEntityUsingCheque';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::errorLog($errorFrom, $errorMessage, $priority);

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Not found',
    //         ], 400);
    //     }
    // }


    // private function logEntity(array $data)
    // {

    //     $unitId = $data['unit_id'];
    //     $propertyId = $data['property_id'];
    //     $leadId = $data['allocated_id'];
    //     $leadType = $data['allocated_type'];
    //     $amount = $data['next_payable_amt'];


    //     // $addSecondTransactionOnly = $data['add_second_transaction_only'] ?? false;

    //     Log::info('Parsed Variables:', [
    //         'unitId' => $unitId,
    //         'propertyId' => $propertyId,
    //         'leadId' => $leadId,
    //         'leadType' => $leadType,
    //         'amount' => $amount,
    //     ]);

    //     $existingTransaction = PaymentTransaction::where('unit_id', $unitId)
    //         ->where('property_id', $propertyId)
    //         ->exists();


    //     Log::info('Existing transaction found:', ['exists' => $existingTransaction]);



    //     $amount = str_replace(',', '', $amount); // Remove all commas

    //     // Add only the second transaction if an entry exists and flag is set
    //     if ($existingTransaction) {
    //         Log::info('logEntity called with data:', $data);
    //         $transaction2 = new PaymentTransaction();
    //         $transaction2->unit_id = $unitId;
    //         $transaction2->property_id = $propertyId;
    //         $transaction2->allocated_id = $leadId;
    //         $transaction2->allocated_type = ($leadType == 'lead') ? 1 : 2;
    //         $transaction2->payment_status = 2; // Final payment status
    //         $transaction2->payment_due_date = today();
    //         $transaction2->booking_date = today();
    //         $transaction2->next_payable_amt = $amount;
    //         $transaction2->created_at = now();
    //         $transaction2->updated_at = now();
    //         $transaction2->save();
    //         // return;
    //     }

    //     if (!$existingTransaction) {
    //         // Log the payment transaction entries
    //         $transaction1 = new PaymentTransaction();
    //         $transaction1->unit_id = $unitId;
    //         $transaction1->property_id = $propertyId;
    //         $transaction1->allocated_id = $leadId; // Lead or Customer ID
    //         $transaction1->allocated_type = ($leadType == 'lead') ? 1 : 2; // 1 for lead, 2 for customer
    //         $transaction1->payment_status = 2; // Initial payment status
    //         $transaction1->payment_due_date = today();
    //         $transaction1->booking_date = today();
    //         $transaction1->created_at = now();
    //         $transaction1->updated_at = now();
    //         $transaction1->save();

    //         // Create the second transaction entry
    //         $transaction2 = new PaymentTransaction();
    //         $transaction2->unit_id = $unitId;
    //         $transaction2->property_id = $propertyId;
    //         $transaction2->allocated_id = $leadId; // Lead or Customer ID
    //         $transaction2->allocated_type = ($leadType == 'lead') ? 1 : 2; // 1 for lead, 2 for customer
    //         $transaction2->payment_status = 2; // Final payment status
    //         $transaction2->payment_due_date = today();
    //         $transaction2->booking_date = today();
    //         $transaction2->next_payable_amt = $amount;
    //         $transaction2->created_at = now();
    //         $transaction2->updated_at = now();
    //         $transaction2->save();
    //     }


    //     // Retrieve all payment transactions for the unit
    //     $paymentTransactions = PaymentTransaction::where('unit_id', $unitId)
    //         ->where('payment_status', 2)
    //         ->get();

    //     // Calculate the total for next_payable_amt
    //     $totalNextPayableAmt = $paymentTransactions->sum('next_payable_amt');

    //     // Retrieve the first payment transaction to include token_amt
    //     $firstPaymentTransaction = $paymentTransactions->first();
    //     if ($firstPaymentTransaction) {
    //         // Add the token_amt of the first entry to the total next_payable_amt
    //         $totalNextPayableAmt += $firstPaymentTransaction->token_amt;
    //     }

    //     $unitdata = UnitDetail::where('id', $unitId)->first();
    //     $leadUnit = LeadUnit::where('unit_id', $unitId)->first();
    //     // Update LeadUnit booking status if totalNextPayableAmt reaches or exceeds the required amount
    //     if ($unitdata->price) {
    //         if ($totalNextPayableAmt >= $unitdata->price) {
    //             $leadUnit->booking_status = 3; // Mark as confirmed
    //             $leadUnit->save();
    //         }
    //     }
    // }

    public function getPaymentTypes()
    {
        try {
            $data = PaymentType::all();

            return $data;
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getPaymentTypes';
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
