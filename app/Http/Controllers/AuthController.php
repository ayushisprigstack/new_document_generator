<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\UserOtp;
use Illuminate\Http\Request;
use App\Models\User;
use Hash;
use App\Helper;
// use Twilio\Rest\Client;
use App\Mail\GetOtpMail;
use App\Models\CompanyDetail;
use App\Models\UserProperty;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use App\Models\Feature;
use App\Models\MenuAccess;
use App\Models\Module;
use App\Models\ModulePlanFeature;
use App\Models\ModulePlanPricing;
use App\Models\Plan;
use App\Models\UserCapability;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;



class AuthController extends Controller
{

    public function generateAndSendOtp($mobile_number, $flag)
    {
        try {
            $otp = rand(100000, 999999);
            $checkUserOtp = UserOtp::where('contact_no', $mobile_number)->where('expire_at', '>', now())->first();

            // Remove expired OTPs
            UserOtp::where('contact_no', $mobile_number)
                ->where(function ($query) {
                    $query->where('expire_at', '<', now())
                        ->orWhere('verified', '1')
                        ->orWhereNotNull('deleted_at');
                })
                ->forceDelete();
            if ($checkUserOtp) {
                try {
                    // $response = $this->sendOtpToWhatsapp($mobile_number, $otp);
                    // $response = $this->sendOtpToWhatsapp($mobile_number, $checkUserOtp->otp);
                    // if ($response['status'] == 'error') {
                    //     return 'error'; // Return error so the flow can handle it
                    // }
                    // $response = $this->sendOtpToWhatsapp($mobile_number, $otp);
                    // Mail::to($email)->send(new GetOtpMail($checkUserOtp->otp));
                } catch (\Exception $e) {
                    Log::error("Otp message sending failed: " . $e->getMessage());
                }

                // User::where('contact_no', $contact_no)->update(['name' =>$username]);
            } else {
                $otpExpire = UserOtp::where('contact_no', $mobile_number)->where('expire_at', '<', now())->first();
                $fetchotpuser = UserOtp::where('contact_no', $mobile_number)->first();
                if ($otpExpire) {
                    $otpExpire->delete();
                }
                $userOtp = new UserOtp();
                $userOtp->otp = $otp;
                $userOtp->contact_no = $mobile_number;
                $userOtp->verified = false;
                if ($flag == 1) { //first setp when phone number adds then add minutes otherwise in resend time flag==2 dont add minutes
                    $userOtp->expire_at = now()->addMinutes(15);
                } else {
                    if ($fetchotpuser == "" && $flag == 2) {
                        $userOtp->expire_at = now()->addMinutes(15);
                    }
                }

                $userOtp->save();


                // User::where('email', $email)->update(['name' =>$username]);
                try {
                    // $response = $this->sendOtpToWhatsapp($mobile_number, $otp);
                    // Mail::to($email)->send(new GetOtpMail($otp));
                  //  $response = $this->sendOtpToWhatsapp($mobile_number, $otp);
                    // Mail::to($email)->send(new GetOtpMail($otp));
                    // $response = $this->sendOtpToWhatsapp($mobile_number, $otp);

                    // if ($response['status'] == 'error') {
                    //     return 'error'; // Return error so the flow can handle it
                    // } else {


                    //     $userOtp = new UserOtp();
                    //     $userOtp->otp = $otp;
                    //     $userOtp->contact_no = $mobile_number;
                    //     $userOtp->verified = false;
                    //     if ($flag == 1) { //first setp when phone number adds then add minutes otherwise in resend time flag==2 dont add minutes
                    //         $userOtp->expire_at = now()->addMinutes(15);
                    //     } else {
                    //         if ($fetchotpuser == "" && $flag == 2) {
                    //             $userOtp->expire_at = now()->addMinutes(15);
                    //         }
                    //     }

                    //     $userOtp->save();
                    // }
                } catch (\Exception $e) {
                    Log::error("Otp message sending failed: " . $e->getMessage());
                }
            }

            return 'success';
        } catch (\Exception $e) {
            $errorFrom = 'generateAndSendOtp';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return 'Something Went Wrong';
        }
    }


    public function registerUser(Request $request)
    {
        try {
            $flag = 0; //0 means first tym, 1 means exists

            $contact_no = $request->mobile_number;
            // $checkUser = User::where('contact_no', $validatedData['contact_no'])->first();
            $checkUserDetails = UserOtp::withTrashed()->where('contact_no', $contact_no)->first();
            $ifUser = User::where('contact_no', $contact_no)->first();

            
            if ($ifUser) {
                // Check if the user is active
                if ($ifUser->is_active == 0) {
                    return response()->json([
                        'status' => 'error',
                        'userExists' => 1,
                        'message' => 'User is inactive.',
                    ], 200); // Forbidden status code
                }
            }

            if ($checkUserDetails == "") {
                $flag = 0;
            } else if ($checkUserDetails) {
                $verifiedStatus = $checkUserDetails->verified;
                if ($verifiedStatus == 1 || $ifUser) {
                    $flag = 1;
                } else {
                    $flag = 0;
                }
            }

            // $isValid = $this->isValidWhatsappNumber($contact_no);
            // if (!$isValid['isWhatsApp']) {
            //     return response()->json([
            //         'status' => 'error', 
            //         'userExists'=>null,
            //         'message' => 'Invalid WhatsApp number'
            //     ]);
            // }
            $response = $this->generateAndSendOtp($contact_no, $request->flag);


            if ($response == 'success') {
                return response()->json([
                    'status' => 'success',
                    'userExists' => $flag,
                    'message' => 'otp sent successfully',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'userExists' => $flag,
                    'message' => 'something went wrong',
                ], 200);
            }
        } catch (\Exception $e) {
            $errorFrom = 'RegisterUser';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }
    public function checkUserOtp(Request $request)
    {
        try {
            $otp = $request->input('otp');
            $contact_no = $request->input('mobile_number');
            $userexitsflag = $request->input('flag');

            // Default value for user property flag
            $flag = 0;
            $token = null;

            // Check OTP and expiration
            $checkUserDetails = UserOtp::where('contact_no', $contact_no)->where('otp', $otp)->first();

            if ($checkUserDetails) {
                if ($checkUserDetails->expire_at > now()) {
                    $checkUserDetails->update(['verified' => 1]);

                    // Handle existing user scenario
                    if ($userexitsflag == 1) {
                        // User exists, fetch user details
                        $userExist = User::where('contact_no', $contact_no)->first();

                        if ($userExist) {
                            // Clear any existing tokens
                            if ($userExist->tokens()) {
                                $userExist->tokens()->delete();
                            }
                            // Generate new token for existing user
                            $token = $userExist->createToken('access_token')->accessToken;
                            $userId = $userExist->id;

                            // Check if the user has any properties and set the flag
                            $userPropertyCount = UserProperty::where('user_id', $userId)->count();
                            if ($userPropertyCount > 0) {
                                $flag = 1;
                            }
                        }
                    } else {
                        // Handle new user scenario
                        $company_name = $request->input('company_name');
                        $user_name = $request->input('user_name');

                        // Create a new user and token
                        $newUser = new User();
                        $newUser->name = $user_name;
                        $newUser->contact_no = $contact_no;
                        $newUser->save();

                        $userId = $newUser->id;
                        $token = $newUser->createToken('access_token')->accessToken;

                        // Create new company details for the user
                        $newCompany = new CompanyDetail();
                        $newCompany->user_id = $userId;
                        $newCompany->name = $company_name;
                        $newCompany->save();


                        // Add menu access for the new user
                        $this->addMenuAccess($userId);

                        // Add plan and capabilities for the new user
                        $this->assignBasicPlanToUser($userId);


                    }

                    // Delete the OTP record after successful verification
                    $checkUserDetails->delete();

                    return response()->json([
                        'status' => 'success',
                        'message' => null,
                        'token' => $token,
                        'userId' => $userId,
                        'userProperty' => $flag,
                    ], 200);
                } else {
                    // OTP expired, delete it and return error
                    $checkUserDetails->delete();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'OTP expired. Please try again.',
                        'token' => null,
                        'userId' => null,
                        'userProperty' => $flag,
                    ], 200);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP. Please try again.',
                    'token' => null,
                    'userId' => null,
                    'userProperty' => $flag,
                ], 200);
            }
        } catch (\Exception $e) {
            $errorFrom = 'CheckUserOtp';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
            ], 400);
        }
    }


    public function sendOtpToWhatsapp($contact_no, $otp)
    {
        $accessToken = getenv("whatsapp_api_token");
        // $senderNumberId = '519294787941225';  // Your registered WhatsApp number ID //for test number
        $senderNumberId="496077890265610"; //for origin superbuildup number
        $contact_no = "91" . $contact_no;
        try {
            $client = new Client();
            $response = $client->post("https://graph.facebook.com/v21.0/{$senderNumberId}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $contact_no,
                    'type' => 'template',
                    'template' => [
                        'name' => 'verify_otp',
                        'language' => [
                            'code' => 'en_US',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $otp, // Replace with your OTP or dynamic text
                                    ],
                                ],
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $otp, // Replace with the dynamic or static URL
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            Log::info('API Response: ' . json_encode(json_decode($response->getBody()->getContents(), true)));
            $responseBody = json_decode($response->getBody()->getContents(), true);

            // Log::info('API Response: ' . json_encode(json_decode($response->getBody()->getContents(), true)));
            return [
                'status' => 'success',
                'message' => 'OTP sent successfully',
            ];
        } catch (ClientException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null;

            $errorFrom = 'sendOtpToWhatsapp';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            if ($responseBody) {
                Log::error("Full response body: " . $responseBody);
            }

            Log::error("WhatsApp OTP sending failed: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'WhatsApp OTP sending failed',
            ];
            // return response()->json([
            //     'status' => 'error',
            //     'message' => 'something went wrong',
            // ], 400);
        }


        // $apiUrl = 'https://api.gupshup.io/wa/api/v1/template/msg';
        // $apiKey = 'x7pcbvdpvzxjfdnc1qelyqja4slvu9va'; // Replace with your actual API key
        // $sourceNumber = '916359506160'; // Your WhatsApp source number
        // $appName = 'Superbuildup'; // Your application name
        // $templateId = 'ca17eedb-8261-4fe0-8bd9-6f82c2bccb9c'; // Replace with your template ID
        // $destination = $contact_no; // Recipient's WhatsApp number
        // $params = [$otp];

        // // API request payload
        // $payload = [
        //     'channel' => 'whatsapp',
        //     'source' => $sourceNumber,
        //     'destination' => $destination,
        //     'src.name' => $appName,
        //     'template' => json_encode([
        //         'id' => $templateId,
        //         'params' => $params, // Dynamic parameters for the template
        //     ]),
        // ];
        // // $apiUrl = env('GUPSHUP_API_URL'); // Example: 'https://api.gupshup.io/sm/api/v1/msg'
        // // $apiKey = env('GUPSHUP_API_KEY');
        // // $officialNumber = env('GUPSHUP_NUMBER');

        // // $payload = [
        // //     'channel' => 'whatsapp',
        // //     'source' => $officialNumber, // Your registered number in Gupshup
        // //     'destination' => $contact_no,
        // //     'template' => 'otp_verification',
        // //     'template_id' => 'ca17eedb-8261-4fe0-8bd9-6f82c2bccb9c',
        // //     'params' => [$otp], // Maps to the dynamic placeholder in your template
        // // ];

        // try {
        //     $response = Http::withHeaders([
        //         'apikey' => $apiKey,
        //         'Content-Type' => 'application/json',
        //     ])->post($apiUrl, $payload);

        //     if ($response->successful()) {
        //         return $response->json(); // Response from Gupshup
        //     } else {
        //         Log::error("Gupshup OTP send failed", ['response' => $response->body()]);
        //         return ['error' => 'Failed to send OTP'];
        //     }
        // } catch (\Exception $e) {
        //     Log::error("Exception in sending OTP via Gupshup: " . $e->getMessage());
        //     return ['error' => 'Exception occurred while sending OTP'];
        // }
    }


    public function isValidWhatsappNumber($contact_no)
    {
        $apiUrl = "https://api.gupshup.io/wa/phone/verify"; // Example endpoint
        $apiKey = config('services.gupshup.api_key');

        $response = Http::withHeaders([
            'apikey' => $apiKey,
            'Content-Type' => 'application/json',
        ])->get($apiUrl, ['phone' => $contact_no]);

        return $response->json();
    }
    // public function checkUserOtp(Request $request)
    // {
    //     try
    //     {
    //     $otp = $request->input('otp');
    //     $email = $request->input('email');
    //     $flag=0;

    //         $checkUserDetails = UserOtp::where('email', $email)->where('otp', $otp)->first();
    //         if ($checkUserDetails) {
    //             if ($checkUserDetails->expire_at > now()) {
    //                 $checkUserDetails->update(['verified' => 1]);
    //                 $userExist = User::where('email', $email)->first();
    //                 if ($userExist) {
    //                     if ($userExist->tokens()) {
    //                         $userExist->tokens()->delete();
    //                     }
    //                     $userExist->update(['name' =>$checkUserDetails->username]);
    //                     $token = $userExist->createToken('access_token')->accessToken;
    //                     $userId = $userExist->id;
    //                 } else {
    //                     $newUser = new User();
    //                     $newUser->email = $email;
    //                     $newUser->name = $checkUserDetails->username;
    //                     $newUser->save();
    //                     $userId = $newUser->id;
    //                     $token = $newUser->createToken('access_token')->accessToken;
    //                 }
    //                 $checkUserDetails->delete();


    //                 //check if this user have any property if commercial or residential then send flag =1
    //                 $userPropertyCount=UserProperty::where('user_id',$userId)->count();
    //                 if($userPropertyCount>0){
    //                     $flag=1;
    //                 }

    //                 return response()->json([
    //                     'status' => 'success',
    //                     'message' => null,
    //                     'token' => $token,
    //                     'userId' => $userId,
    //                     'userProperty'=> $flag,
    //                 ], 200);
    //             } else {
    //                 $checkUserDetails->delete();
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => null,
    //                     'token' => null,
    //                     'userId' => null,
    //                     'userProperty'=> $flag,
    //                 ], 400);
    //             }
    //         } else {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Invalid Otp. Please try again.',
    //                 'token' => null,
    //                 'userId' => null,
    //                 'userProperty'=> $flag,
    //             ], 400);
    //         }
    //     } catch (\Exception $e) {
    //         $errorFrom = 'CheckUserOtp';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::errorLog($errorFrom, $errorMessage, $priority);
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'something went wrong',
    //         ],400);
    //     }
    // }



    public function logout(Request $request)
    {
        try {
            if ($request->user()) {
                $request->user()->token()->delete();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Logout Successfully',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'something went wrong',
                ], 401);
            }
        } catch (\Exception $e) {
            $errorFrom = 'logout';
            $errorMessage = $e->getMessage();
            $priority = 'high';
            Helper::errorLog($errorFrom, $errorMessage, $priority);
            return response()->json([
                'status' => 'error',
                'message' => 'something went wrong',
            ], 400);
        }
    }



    public function sendBulkMessages(Request $request)
    {
        $apiUrl = 'https://api.gupshup.io/wa/api/v1/msg'; // Gupshup API endpoint
        $apiKey = 'x7pcbvdpvzxjfdnc1qelyqja4slvu9va'; // Replace with your actual API key
        $sourceNumber = '916359506160'; // Your Gupshup source number
        $destinationNumber = '+918320064478'; // The number to send the message to 918780496028
        $messageText = "hi"; // The message content

        // Prepare the message payload
        $payload = [
            'source' => $sourceNumber,
            'destination' => $destinationNumber,
            'src.name' => 'Superbuildup', // You can change this as needed
            'message' => json_encode([
                'type' => 'text',
                'text' => $messageText,
                'previewUrl' => true, // Optional: Show preview for links
            ]),
        ];

        try {
            // Send the POST request to Gupshup API
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($apiUrl, $payload);

            // Check the response status
            if ($response->successful()) {
                // Log::info("Message sent successfully to {$destinationNumber}: " . $response->json());
                return response()->json([
                    'status' => 'success',
                    'message' => 'Message sent successfully!',
                    'data' => $response->json(),
                ]);
            } else {
                Log::error("Failed to send message to {$destinationNumber}: " . $response->body());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send message.',
                    'error' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while sending the message.',
                'error' => $e->getMessage() . $e->getLine(),
            ]);
        }
    }


    function sendGupshupTemplateMessage()
    {
        $otp = "123456";
        $apiUrl = 'https://api.gupshup.io/wa/api/v1/template/msg';
        $apiKey = 'x7pcbvdpvzxjfdnc1qelyqja4slvu9va'; // Replace with your actual API key
        $sourceNumber = '916359506160'; // Your WhatsApp source number
        $appName = 'Superbuildup'; // Your application name
        $templateId = 'ca17eedb-8261-4fe0-8bd9-6f82c2bccb9c'; // Replace with your template ID
        $destination = '+918320064478'; // Recipient's WhatsApp number
        $params = [$otp];

        // API request payload
        $payload = [
            'channel' => 'whatsapp',
            'source' => $sourceNumber,
            'destination' => $destination,
            'src.name' => $appName,
            'template' => json_encode([
                'id' => $templateId,
                'params' => $params, // Dynamic parameters for the template
            ]),
        ];

        // Send POST request to Gupshup API
        $response = Http::withHeaders([
            'apikey' => $apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($apiUrl, $payload);

        // Check response and return
        if ($response->successful()) {
            return [
                'success' => true,
                'response' => $response->json(),
            ];
        } else {
            return [
                'success' => false,
                'error' => $response->body(),
            ];
        }
    }


    private function addMenuAccess($userId)
    {
        // You can customize this list based on which menus the user should have access to
        // Get top-level menus (menus with no parent)
        $menus = Menu::get();

        // Check if menus are found
        if ($menus->isEmpty()) {
            return; // If no menus found, exit the method
        }

        foreach ($menus as $menu) {
            // Create menu access for the top-level menu
            MenuAccess::create([
                'menu_id' => $menu->id,
                'user_id' => $userId,
                'type' => 1, // Customize this type if needed
            ]);

            // Check if the submenu relationship exists and has items
            if ($menu->subMenus && $menu->subMenus->isNotEmpty()) {
                foreach ($menu->subMenus as $submenu) {
                    // Create menu access for each submenu
                    MenuAccess::create([
                        'menu_id' => $submenu->id,
                        'user_id' => $userId,
                        'type' => 1, // Customize this type if needed
                    ]);
                }
            }
        }
    }

    public static function  assignBasicPlanToUser($userId)
    {
        //add plan while first signup
        $basicPlan = Plan::where('id', 1)->first();
        if ($basicPlan) {
            $modules = ModulePlanPricing::where('plan_id', $basicPlan->id)
                ->pluck('module_id')
                ->toArray();

            foreach ($modules as $moduleId) {
                $features = ModulePlanFeature::where('module_id', $moduleId)
                    ->where('plan_id', 1)
                    ->with('feature') // Eager load feature to get action_name
                    ->get();
                // $features = Feature::where('module_id', $moduleId)->get();

                foreach ($features as $feature) {
                    UserCapability::create([
                        'user_id' => $userId,
                        'plan_id' => $basicPlan->id,
                        'module_id' => $moduleId,
                        'feature_id' => $feature->feature_id,
                        'limit' => $feature->limit ?? 0,
                        'object_name' => $feature->feature->action_name,

                    ]);
                }
            }
        }
    }

    public function sendWhatsAppMessage(Request $request)
    {
        $request->validate([
            'phone' => 'required|numeric',  // WhatsApp phone number (with country code)
        ]);

        $phone = $request->input('phone');
        $accessToken = 'EAAiCxV1licUBOZBwxT2CNqyMhnS7w69xFQTHXZAZB8siO1r5EllHZBRLobLmJwPoHwVBMwBoiWNaMFjLDPLjZAKzYLQQjwBR3JezHMySZCMZCfcpCXw0fPQrJw1rPpZBqtKVUUEjQ3gZCOZAoznyINmjhSiUshQE94RVu8wpyQCZAQVI88MTt5gNvNs5BrOEEnsWBRuuaKUzIpmULyegv6iZBjkv7hH0HuZBy3L9vtGMZD';  // Replace with your token
        $senderNumberId = '519294787941225';  // Your registered WhatsApp number 519294787941225

        try {
            $client = new Client();
            $response = $client->post("https://graph.facebook.com/v21.0/{$senderNumberId}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => 'hello_world',  // Your template name
                        'language' => [
                            'code' => 'en_US',  // Language code
                        ]
                    ]
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp message sent successfully.',
                'response' => json_decode($response->getBody()->getContents())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send WhatsApp message.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
