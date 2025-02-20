<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helper;
use App\Models\Feature;
use App\Models\Module;
use App\Models\ModulePlanFeature;
use App\Models\ModulePlanPricing;
use App\Models\Plan;
use App\Models\UserCapability;



use App\Models\Property;
use App\Models\PaymentTransaction;
use App\Models\UserProperty;
use App\Models\User;
use Exception;








class PlanModuleController extends Controller
{


    public function getModulesWithPricing()
    {

        try {
            $modules = Module::with(['plans' => function ($query) {
                $query->select('plans.id', 'module_plan_pricing.module_id', 'monthly_price')
                    ->where('monthly_price', '>', 0) // Exclude prices equal to 0
                    ->orderBy('monthly_price', 'asc');
            }])->get();

            // Format the data to show the module name and its starting monthly price
            $formattedModules = $modules->map(function ($module) {
                return [
                    'id' => $module->id,
                    'module_name' => $module->name,
                    'starting_price' => $module->plans->isNotEmpty()
                        ? $module->plans->first()->pivot->monthly_price
                        : null,
                ];
            });

            return $formattedModules;
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getModulesWithPricing';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }


    public function getModulePlanDetails($uid, $mid)
    {
        try {
            // Retrieve the module with its associated pricing plans and features
            // $module = Module::with(['modulePricingPlans.plan.pricingPlanFeatures'])->findOrFail($mid);

            $module = Module::with(['modulePricingPlans.plan' => function ($query) use ($mid) {
                $query->with(['pricingPlanFeatures' => function ($query) use ($mid) {
                    $query->where('module_id', $mid);
                }]);
            }])->findOrFail($mid);
            $features = Feature::where('module_id', $mid)
                ->select('description', 'Basic', 'Standard', 'Premium', 'Enterprise')
                ->get();


            // Format the response
            // Format features as required



            // Get the active plan for the user and the module from user_capabilities
            $activePlan = UserCapability::where('user_id', $uid)
                ->where('module_id', $mid)
                ->first(['plan_id']);

            // Add active plan information to the response
            $activePlanId = $activePlan ? $activePlan->plan_id : null;

            $plans = $module->modulePricingPlans->map(function ($pricing) {
                return $pricing->plan;
            });

  

            // Dynamically format features based on activePlanId and the plans
            $featureDetails = $features->map(function ($feature) use ($activePlanId, $plans) {
                $filteredFeature = [
                    'feature_description' => $feature->description,
                ];

                foreach ($plans as $plan) {
                    // Include features only for plans at or above the active plan
                    if ($activePlanId === null || $plan->id >= $activePlanId) {
                        $planName = $plan->name; // Example: 'Basic', 'Standard', etc.
                        $filteredFeature["{$planName}_plan"] = $feature->{$planName} ?? '';
                    }
                }

                return $filteredFeature;
            });

            $response = [
                'module_id' => $module->id,
                'module_name' => $module->name,
                'active_plan_id' => $activePlanId, // Assuming the first plan is active
                'plandetails' => [],
                'featuredetails' => $featureDetails
            ];


            foreach ($module->modulePricingPlans as $pricing) {
                // echo $pricing;
                $plan = $pricing->plan;
                if ($activePlanId == null || $plan->id >= $activePlanId) {
                    $planDetails = [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'monthly_price' => $pricing->monthly_price,
                        'yearly_price' => $pricing->yearly_price,
                        'features' => []
                    ];

                    foreach ($plan->pricingPlanFeatures as $feature) {
                        $planDetails['features'][] = [
                            'data' => $feature->description
                        ];
                    }

                    $response['plandetails'][] = $planDetails;
                }
            }

            return response()->json($response);
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'getModulePlanDetails';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    // public function getFeaturesWithPlans($mid)
    // {
    //     try {
    //         // Fetch Features with the Module and Plans
    //         $features = Feature::where('module_id', $mid)
    //             ->select('description', 'Basic', 'Standard', 'Premium', 'Enterprise')
    //             ->get();

    //         // Format the response
    //         $response = $features->map(function ($feature) {
    //             return [
    //                 'feature_description' => $feature->description,
    //                 'Basic_plan' => $feature->Basic ?? '',
    //                 'Standard_plan' => $feature->Standard ?? '',
    //                 'Premium_plan' => $feature->Premium ?? '',
    //                 'Enterprise_plan' => $feature->Enterprise ?? '',
    //             ];
    //         });


    //         return response()->json($response);
    //     } catch (Exception $e) {
    //         echo $e->getMessage() . $e->getLine();
    //         // Log the error
    //         $errorFrom = 'getFeaturesWithPlans';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::errorLog($errorFrom, $errorMessage, $priority);

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Not found',
    //         ], 400);
    //     }
    // }

    public function addUserModulePlan(Request $request)
    {
        try {
            $validated = $request->validate([
                'userId' => 'required|exists:users,id',
                'moduleId' => 'required|exists:modules,id',
                'planId' => 'required|exists:plans,id',
                'mothly_yearly' => 'required' //1 means monthly,2 means yearly
            ]);

            $userId = $validated['userId'];
            $moduleId = $validated['moduleId'];
            $planId = $validated['planId'];
            $planduration = $validated['mothly_yearly'];

            // Fetch all features for the given module and plan
            $features = ModulePlanFeature::where('module_id', $moduleId)
                ->where('plan_id', $planId)
                ->with('feature') // Eager load feature to get action_name
                ->get();

            if ($features->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No features found for the given module and plan.',
                ], 200);
            }


            // Remove previous user capabilities for the same module
            UserCapability::where('user_id', $userId)
                ->where('module_id', $moduleId)
                ->delete();

            // Add new user capabilities based on the features of the selected plan
            $newCapabilities = [];
            foreach ($features as $feature) {
                $newCapabilities[] = [
                    'user_id' => $userId,
                    'plan_id' => $planId,
                    'module_id' => $moduleId,
                    'feature_id' => $feature->feature_id,
                    'plan_duration' => $planduration,
                    'limit' => $feature->limit,
                    'object_name' => $feature->feature->action_name, // Map action_name to object_name
                ];
            }

            // Insert new capabilities
            UserCapability::insert($newCapabilities);

            return response()->json([
                'status' => 'success',
                'message' => 'Plan and features successfully assigned to the user.',
            ], 200);
        } catch (Exception $e) {
            // Log the error
            $errorFrom = 'addUserModulePlan';
            $errorMessage = $e->getMessage() . $e->getLine();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }
}
