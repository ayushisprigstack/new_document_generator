<?php

use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WingController;
use App\Http\Controllers\ChequeScanController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\VillaBunglowController;
use App\Http\Controllers\PlanModuleController;

Route::post('/register-user', [AuthController::class, 'registerUser']);
// Route::post('/check-user-otp', [AuthController::class, 'checkUserOtp']);
Route::post('/check-user-otp', [AuthController::class, 'checkUserOtp']);
Route::post('/send-bulk-messages', [AuthController::class, 'sendBulkMessages']);
Route::post('/send-template-messages', [AuthController::class, 'sendGupshupTemplateMessage']);

Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/get-user-details/{uid}', [UserController::class, 'getUserDetails']);
//Route::get('/user-profile/{uid}', [UserController::class, 'userProfile']);
Route::post('/add-update-user-profile', [UserController::class, 'addUpdateUserProfile']);
Route::get('/get-user-menu-access/{uid}', [UserController::class, 'getUserMenuAccess']);
Route::post('/add-sub-user', [UserController::class, 'addSubUser']);
Route::get('/fetch-sub-user/{uid}', [UserController::class, 'fetchSubUsers']);
Route::get('/get-sub-user-detail/{uid}', [UserController::class, 'getSubUserDetail']);
Route::post('/update-sub-user', [UserController::class, 'updateSubUser']);


//properties call
Route::get('/get-property-types/{typeFlag}', [PropertyController::class, 'getPropertyTypes']);
Route::post('/add-property-details', [PropertyController::class, 'addPropertyDetails']);
Route::get('/get-property-details/{pid}', [PropertyController::class, 'getPropertyDetails']);


//remove this call later
Route::get('/get-user-property-details/{uid}', [PropertyController::class, 'getUserPropertyDetails']);

// Route::get('/get-property-statuses/{statusFlag}', [PropertyController::class, 'getPropertyStatues']);
// Route::get('/get-property-amenities', [PropertyController::class, 'getPropertyAmenities']);
Route::get('/get-property-wings-basic-details/{pid}', [PropertyController::class, 'getPropertyWingsBasicDetails']);
Route::get('/exportSales/{pid}', [PropertyController::class, 'exportSales']);


//sales dashboard
Route::get('/get-sales-basic-details/{uid}/{pid}', [PropertyController::class, 'getSalesBasicDetails']);
Route::get('/get-recent-interested-leads/{uid}/{pid}', [PropertyController::class, 'getRecentInterestedLeads']);
Route::get('/get-recent-customers/{uid}/{pid}', [PropertyController::class, 'getRecentCustomers']);
Route ::get('get-payment-type-summary/{uid}/{pid}', [PropertyController::class, 'getPaymentTypeSummary']);
Route ::get('/get-sales-analytics/{uid}/{pid}/{flag}', [PropertyController::class, 'getSalesAnalyticsReport']);


// Route::post('/add-unit-details', [PropertyController::class, 'addUnitDetails']);
// Route::get('/get-wing-details/{propertyId}', [PropertyController::class, 'getWingDetails']);
//  Route::post('/add-similar-wing', [PropertyController::class, 'addSimilarWing']);

Route::get('/get-all-properties/{uid}&{stateid}&{cityid}&{area}', [PropertyController::class, 'getAllProperties']);
Route::get('/get-state-details', [PropertyController::class, 'getStateDetails']);
Route::get('/get-state-with-cities-details/{id}', [PropertyController::class, 'getStateWithCities']);
Route::get('/get-area-with-cities-details/{uid}/{cid}', [PropertyController::class, 'getAreaWithCities']);



//leads call
Route::get('/get-leads/{pid}&{flag}&{skey}&{sort}&{sortbykey}&{statusid}&{customfieldid}&{customfieldvalue}&{tagid}&{offset}&{limit}', [LeadController::class, 'getLeads']); 
Route::get('/fetch-lead-detail/{pid}/{lid}', [LeadController::class, 'fetchLeadDetail']);
Route::get('/fetch-tags/{pid}', [LeadController::class, 'fetchTags']);
Route::post('/add-edit-leads', [LeadController::class, 'addOrEditLeads'])->middleware('check.feature');
Route::post('/add-leads-csv', [LeadController::class, 'addLeadsCsv'])->middleware('check.feature');
Route::post('/lead-messages/send', [LeadController::class, 'sendBulkMessages']);
Route::get('/fetch-lead-intersted-booked-detail/{pid}/{lid}', [LeadController::class, 'fetchLeadInterestedBookedDetail']);
Route::get('/fetch-single-multi-custom-field-values/{pid}/{cid}', [LeadController::class, 'fetchSingleMultiCustomFieldValue']);
Route::get('/exportLeads/{pid}', [LeadController::class, 'exportLeads'])->middleware('check.feature');


//wings call
Route::post('/add-wing-details', [WingController::class, 'addWingDetails']);
Route::get('/get-wings-basic-details/{wid}', [WingController::class, 'getWingsBasicDetails']);
Route::get('/wings-with-units-floors/{pid}', [WingController::class, 'getWingsWithUnitsAndFloors']);
Route::post('/add-wings-floor-details', [WingController::class, 'addWingsFloorDetails'])->middleware('check.feature');;
Route::post('/add-similar-wing-details', [WingController::class, 'addSimilarWingDetails'])->middleware('check.feature');;
Route::post('/bulk-updates-for-wings-details', [WingController::class, 'bulkUpdatesForWingsDetails']);
Route::post('/update-wing-details', [WingController::class, 'updateWingDetails']);
Route::get('/get-unit-basic-details/{uid}', [WingController::class, 'getunitBasicDetails']);
Route::post('/add-new-unit', [WingController::class, 'addNewUnitForFloor'])->middleware('check.feature');;


//units call
Route::post('/add-interested-leads', [UnitController::class, 'addInterestedLeads']);
Route::get('/get-unit-interested-leads/{uid}', [UnitController::class, 'getUnitInterestedLeads']);
Route::get('/get-unit-wing-wise/{wid}', [UnitController::class, 'getUnitsBasedOnWing']);
Route::post('/send-reminder/{uid}', [UnitController::class, 'sendReminderToBookedPerson']);
Route::get('/get-lead-name-with-detail/{pid}', [UnitController::class, 'getLeadNames']);
Route::get('/get-lead-customer-name-with-detail/{pid}', [UnitController::class, 'getLeadCustomerNames']);
Route::post('/lead-attach-with-units', [UnitController::class, 'addLeadsAttachingWithUnits']);
Route::post('/update-unit-series-numbers', [UnitController::class, 'updateUnitSeriesNumber']);




//booking calls
Route::get('/get-payment-types', [BookingController::class, 'getPaymentTypes']);
Route::get('/get-booked-unit-detail/{uid}/{bid}/{type}', [BookingController::class, 'getBookedUnitDetail']);
Route::post('/add-unit-booking-detail', [BookingController::class, 'addUnitBookingInfo']);
Route::post('/add-unit-payment-detail', [BookingController::class, 'addUnitPaymentDetail']);
Route::post('/add-matched-entity-using-cheque', [BookingController::class, 'addMatchedEntityUsingCheque']);
Route::post('/add-entity-attach-with-units-using-cheque', [BookingController::class, 'addEntityAttachWithUnitsUsingCheque']);


//villa/bunglow calls
Route::post('/add-villa-bunglow-details', [VillaBunglowController::class, 'addVillaBunglowDetails']);

//custom field calls
Route::post('/add-custom-fields', [CustomFieldController::class, 'addCustomFields']);
Route::get('/get-custom-fields/{pid}', [CustomFieldController::class, 'getCustomFields']);
Route::get('/get-custom-field-with-lead-values/{pid}/{lid}', [CustomFieldController::class, 'getCustomFieldWithLeadValues']);
Route::get('/fetch-custom-field/{cfid}', [CustomFieldController::class, 'fetchCustomField']);
Route::post('/remove-custom-field', [CustomFieldController::class, 'removeCustomField']);



//plan-module apis
Route::get('/get-module-with-price', [PlanModuleController::class, 'getModulesWithPricing']);
Route::get('/get-module-plan-details/{uid}/{mid}', [PlanModuleController::class, 'getModulePlanDetails']);
Route::post('/add-user-module-plan', [PlanModuleController::class, 'addUserModulePlan']);


//letter-head document apis
Route::post('/upload-letter-head', [DocumentController::class, 'uploadLetterhead']);
Route::post('/generate-payment-pdf', [DocumentController::class, 'generatePaymentPdf']);
Route::get('/get-scheme-generated-doc/{uid}/{pid}', [DocumentController::class, 'getGeneratedDoc']);



//apis without auth
Route::get('/get-sources', [LeadController::class, 'getLeadSources']);
Route::get('/get-field-types', [LeadController::class, 'getFieldTypes']);
Route::get('/get-lead-statuses', [LeadController::class, 'getLeadStatus']);

//remove this call later
Route::get('/get-user-properties/{uid}', [LeadController::class, 'getUserProperties']);


//rest api/webform api for leads
Route::post('/generate-lead', [LeadController::class, 'generateLead'])->middleware('check.feature');
Route::post('/web-form-lead', [LeadController::class, 'webFormLead'])->middleware('check.feature');
Route::post('/detect-cheque', [ChequeScanController::class, 'detectCheque']);



Route::get('/testapi', function () {
  return 'Hello, this is an echo route api!';
});


Route::post('/send-whatsapp', [AuthController::class, 'sendWhatsAppMessage']);