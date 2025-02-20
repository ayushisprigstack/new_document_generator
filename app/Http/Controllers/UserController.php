<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompanyDetail;
use Illuminate\Http\Request;
use App\Models\User;
use App\Helper;
use App\Models\Feature;
use Illuminate\Support\Facades\Storage;
use App\Models\Menu;
use App\Models\MenuAccess;
use App\Models\Plan;
use App\Models\UserCapability;
use App\Models\UserOtp;

class UserController extends Controller
{
    public function userProfile($uid)
    {
        $companyDetails = CompanyDetail::with('user')->where('user_id', $uid)->first();
        if ($companyDetails) {
            return response()->json([
                'message' => $companyDetails
            ], 200);
        } else {
            return response()->json([
                'message' => null,
            ], 200);
        }
    }

    public function addUpdateUserProfile(Request $request)
    {
        try {
            $validator = validator($request->all(), [
                'userName' => 'required|string|max:255',
                'contactNum' => 'required|string|max:15',
                'companyEmail' => 'required|string|email|max:255',
                'companyName' => 'required|string|max:255',
                'companyContactNum' => 'nullable|string|max:15',
                'companyAddress' => 'nullable|string',
                'companyLogo' => 'nullable|string',
                'userId' => 'required|integer'
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            $validatedData = $validator->validated();
            $checkUser = User::where('id', $validatedData['userId'])->first();

            if (isset($checkUser)) {
                $checkUser->update(['name' => $validatedData['userName'], 'contact_no' => $validatedData['contactNum']]);
                $checkCompanyDetails = CompanyDetail::where('user_id', $checkUser->id)->first();
                if ($checkCompanyDetails) {
                    $checkCompanyDetails->update([
                        'name' => $validatedData['companyName'],
                        'contact_no' => $validatedData['companyContactNum'],
                        'email' => $validatedData['companyEmail'],
                        'address' => $validatedData['companyAddress'],
                        'logo' => $validatedData['companyLogo']
                    ]);
                } else {
                    CompanyDetail::create([
                        'user_id' => $validatedData['userId'],
                        'name' => $validatedData['companyName'],
                        'contact_no' => $validatedData['companyContactNum'],
                        'email' => $validatedData['companyEmail'],
                        'address' => $validatedData['companyAddress'],
                        'logo' => $validatedData['companyLogo']
                    ]);
                }
                return response()->json([
                    'status' => 'success',
                    'message' => 'Profile update successfully',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'user not found',
                ], 400);
            }
        } catch (\Exception $e) {
            $errorFrom = 'addUpdateUserProfile';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }

    public function getUserDetails($uid)
    {
        $userDetails = User::with('companyDetail')->where('id', $uid)->first();
        if ($userDetails) {
            // Check if client_id and client_secret_key are missing, generate if needed
            if (empty($userDetails->client_id)) {
                $userDetails->client_id = uniqid('client_', true);
            }

            if (empty($userDetails->client_secret_key)) {
                $userDetails->client_secret_key = bin2hex(random_bytes(16)); // Generate a random 32-character key
            }

            // Save the new values if they were generated
            $userDetails->save();
            return response()->json([
                'message' => $userDetails
            ], 200);
        } else {
            return response()->json([
                'message' => null,
            ], 400);
        }
    }

    public function getUserMenuAccess($uid)
    {
        try {
            if ($uid != 'null') {
                // Get the menu access for the user and load parent relationships
                $menuAccess = MenuAccess::with(['menu.parent'])
                    ->where('user_id', $uid)
                    ->get()
                    ->map(function ($access) {
                        return $access->menu;
                    });

                // Group menus by parent_id (null for top-level menus)
                $menuGroups = $menuAccess->groupBy('parent_id');

                // Build the response structure
                $result = $this->buildMenuTree($menuGroups, null);

                return response()->json($result);
            } else {
                return null;
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'getUserMenuAccess';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    private function buildMenuTree($menuGroups, $parentId)
    {
        $menus = [];

        if (isset($menuGroups[$parentId])) {
            foreach ($menuGroups[$parentId] as $menu) {
                // Build the submenu recursively
                $submenu = $this->buildMenuTree($menuGroups, $menu->id);

                // Add the menu to the structure
                $menus[] = [
                    'id' => $menu->id,
                    'path' =>  $menu->menu_path,
                    'imageKey' => $menu->menu_img,
                    'label' => $menu->name,
                    'submenu' => $submenu
                ];
            }
        }

        return $menus;
    }
    public function addSubUser(Request $request)
    {
        try {

            $parentUserId = $request->userId; // Parent user ID from the request
            $username = $request->username;
            $usercontact = $request->usercontact;
            $selectedModules = $request->selectedModules;
            $actionName = $request->input('userCapabilities');
            $subUserId = $request->subuserId; // Sub-user ID for edit (if provided)



            if ($subUserId) {
                //edit case
                // Editing an existing sub-user
                $subUser = User::find($subUserId);

                if (!$subUser || $subUser->parent_user_id != $parentUserId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Sub-user not found or does not belong to the parent user.',
                    ], 404);
                }

                // Check for duplicate contact number within the same parent user, excluding the current sub-user
                $existingUser = User::where('contact_no', $usercontact)
                    ->where('parent_user_id', $parentUserId)
                    ->where('id', '!=', $subUserId)
                    ->first();

                if ($existingUser) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Contact number already exists. Please use a different contact number.',
                    ], 200);
                }

                // Update sub-user details
                $subUser->update([
                    'name' => $username,
                    'contact_no' => $usercontact,
                ]);

                // Handle menu access (update logic)
                $existingMenuAccess = MenuAccess::where('user_id', $subUserId)->pluck('menu_id')->toArray();

                // Find menus to remove
                $menusToRemove = array_diff($existingMenuAccess, $selectedModules);
                MenuAccess::where('user_id', $subUserId)->whereIn('menu_id', $menusToRemove)->forcedelete();

                // Find menus to add
                $menusToAdd = array_diff($selectedModules, $existingMenuAccess);
                foreach ($menusToAdd as $menuId) {
                    MenuAccess::create([
                        'menu_id' => $menuId,
                        'user_id' => $subUserId,
                        'type' => 2,
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sub-user updated and menu access updated successfully.',
                ], 200);
            } else {
                //add case
                // Check for duplicate contact number for the same parent user only
                $existingUser = User::where('contact_no', $usercontact)
                    ->where('parent_user_id', $parentUserId) // Add condition to check against the same parent ID
                    ->first();
                if ($existingUser) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Contact number already exists. Please use a different contact number.',
                    ], 200);
                }

                //add sub user condidtions plan limit check
                $moduleId = Helper::getModuleIdFromAction($actionName);

                $feature = Feature::where('action_name', $actionName)
                    ->where('module_id', $moduleId)
                    ->first();

                $limitCheck = $this->checkFeatureLimits($parentUserId, $moduleId, $feature, null);
                if ($limitCheck) {
                    return $limitCheck; // Return upgrade response if limit is exceeded
                }

                // Create a new sub-user with parent_user_id set
                $newUserOtp = UserOtp::create([
                    'contact_no' => $usercontact,
                    'verified' => 0
                ]);

                $newUser = User::create([
                    'name' => $username,
                    'contact_no' => $usercontact,
                    'parent_user_id' => $parentUserId,
                ]);

                // Fetch the parent user's company details
                $parentCompanyDetails = CompanyDetail::where('user_id', $parentUserId)->first();

                // Check if parent user has company details and copy them to the new user
                if ($parentCompanyDetails) {
                    CompanyDetail::create([
                        'user_id' => $newUser->id,  // New user ID
                        'name' => $parentCompanyDetails->name,
                        'address' => $parentCompanyDetails->address,
                        'email' => $parentCompanyDetails->email,
                        'contact_no' => $parentCompanyDetails->contact_no,
                        'logo' => $parentCompanyDetails->logo, // Assuming logo is a file path or URL
                    ]);
                }

                // Loop through selected modules and assign them to the new user
                foreach ($selectedModules as $moduleId) {
                    // Get the menu by module ID
                    $menu = Menu::find($moduleId);

                    if ($menu) {
                        // Insert the menu access for the selected module
                        MenuAccess::create([
                            'menu_id' => $menu->id,
                            'user_id' => $newUser->id,
                            'type' => 2,  // subuser
                        ]);

                        // Check if the menu has submenus (child menus)
                        $subMenus = $menu->children;  // This assumes `subMenus` is a relationship method in the Menu model
                        if ($subMenus && $subMenus->isNotEmpty()) {
                            foreach ($subMenus as $submenu) {
                                // Insert the submenu access for each child menu
                                MenuAccess::create([
                                    'menu_id' => $submenu->id,
                                    'user_id' => $newUser->id,
                                    'type' => 2,  // Customize this if needed
                                ]);
                            }
                        }
                    }
                }


                // Add plan and capabilities for the new user
                AuthController::assignBasicPlanToUser($newUser->id);
            }



            // Return success response with user details
            return response()->json([
                'status' => 'success',
                'message' => 'Sub-user created and menu access granted successfully.',
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'addSubUser';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            // Handle error
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong. Please try again later.',
            ], 400);
        }
    }



    public function updateSubUser(Request $request)
    {
        try {

            $parentUserId = $request->userId; // Parent user ID from the request
            $subUserId = $request->subuserId; // Sub-user ID for edit (if provided)
            $status = $request->status; //1 ->active, 2 inactive , null for delete

            // Validate if sub-user exists under the parent user
            $subUser = User::where('id', $subUserId)
                ->where('parent_user_id', $parentUserId)
                ->first();
               
// echo "user". $parentUserId ."". $subUserId."".  $subUser;
            if (!$subUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sub-user not found or does not belong to the parent user.',
                ], 200);
            }

            // echo $status;

            // Handle operations based on the flag
            if ($status == 1) {
                // Activate or deactivate the sub-user
                $subUser->is_active = 1;
                $subUser->save();
                $message = 'Sub-user activated successfully.';
            }else if($status == 2){
                $subUser->is_active = 0;
                $subUser->save();
                $message = 'Sub-user deactivated successfully.';
            }elseif ($status == null) {
                // Soft delete the sub-user
                $subUser->delete();
                $message = 'Sub-user deleted successfully.';
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid operation flag.',
                ], 200);
            }

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Sub-user updated and menu access updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            $errorFrom = 'updateSubUser';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            // Handle error
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong. Please try again later.',
            ], 400);
        }
    }
    //fetch sub user detail for edit modal
    public function getSubUserDetail($uid)
    {
        try {
            // Fetch the user by ID
            $user = User::find($uid);

            // Check if user exists
            if (!$user) {
                return 'User not found.';
            }

            // Fetch menu access (selected modules) for the user
            $selectedModules = MenuAccess::where('user_id', $uid)
                ->pluck('menu_id')
                ->toArray();

            // Prepare the response data
            $response = [
                'username' => $user->name,
                'usercontact' => $user->contact_no,
                'selectedModules' => $selectedModules,
            ];

            return  $response;
        } catch (\Exception $e) {
            $errorFrom = 'getSubUserDetail';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 400);
        }
    }

    //fetch all sub-users
    public function fetchSubUsers($uid)
    {
        try {
            if ($uid != 'null') {

                // Fetch sub-users based on the parent_user_id
                $subUsers = User::where('parent_user_id', $uid)
                    ->get();

                if ($subUsers->isEmpty()) {
                    return response()->json([
                        'data' => [],
                    ], 200);
                }

                return response()->json([
                    'data' => $subUsers,
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User ID is invalid.',
                ], 200);
            }
        } catch (\Exception $e) {
            // Log the error
            $errorFrom = 'fetchSubUsers';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);

            return response()->json([
                'status' => 'error',
                'message' => 'Not found',
            ], 400);
        }
    }

    public static function checkFeatureLimits($userId, $moduleId, $feature, $flag = null)
    {

        // Get the user's plan limit for the feature
        $feature = Feature::where('id', $feature->id)
            ->first();
        $actionName = $feature->action_name;

        $userCapability = UserCapability::where('user_id', $userId)
            ->where('module_id', $moduleId)
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


        $subUserCount = User::where('parent_user_id', $userId)->count();


        if ($subUserCount >= $limit) {
            return response()->json([
                'status' => 'upgradeplan',
                'moduleid' => $moduleId,
                'activeplanname' => $plandetail->name ?? 'Unknown',
            ], 200);
        }







        // If the limit is not exceeded, proceed with the request
        return null; // Proceed
    }
}
