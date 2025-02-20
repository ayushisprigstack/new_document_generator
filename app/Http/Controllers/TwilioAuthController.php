<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Hash;
use App\Helper;
use Twilio\Rest\Client;



class TwilioAuthController extends Controller
{
    // public function register(Request $request)
    // {
    //     try {
    //         $validator = validator($request->all(), [
    //             'name' => 'required|string|max:255',
    //             'contact_number' => 'required|regex:/^\+(?:\d{1,3})\s?(?:\d{1,4})?\s?\d{1,14}(?:\s?\d{1,13})?$/',
    //             'verification_method' => 'required|in:0,1'
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json($validator->errors(), 422);
    //         }

    //         $validatedData = $validator->validated();
    //         $verification_method = $validatedData['verification_method'] == 0 ? 'whatsapp' : 'sms';

    //         $token = getenv("TWILIO_AUTH_TOKEN");
    //         $twilio_sid = getenv("TWILIO_SID");
    //         $twilio_verify_sid = getenv("TWILIO_VERIFY_SID");
    //         $twilio_whatsapp_number = getenv("TWILIO_WHATSAPP_NUMBER");
    //         try {
    //             $otp = rand(100000, 999999);
    //             $twilio = new Client($twilio_sid, $token);
    //             if ($verification_method == 'whatsapp')
    //             $twilio->messages->create(
    //                 'whatsapp:' . $validatedData['contact_number'],
    //                 [
    //                     'from' => $twilio_whatsapp_number,
    //                     'body' => "Your OTP is: $otp",
    //                 ]
    //             );
    //             elseif ($verification_method == 'sms') {
    //                 $twilio->verify->v2->services($twilio_verify_sid)
    //                     ->verifications
    //                     ->create($validatedData['contact_number'], $verification_method);
    //             }

    //         } catch (\Exception $e) {
    //             $errorFrom = 'register';
    //             $errorMessage = $e->getMessage();
    //             $priority = 'high';
    //             Helper::ErrorLog($errorFrom, $errorMessage, $priority);
    //             return 'Something Went Wrong';
    //         }
    //         $userExist = User::where('contact_no', $validatedData['contact_number'])->first();
    //         if (!$userExist) {
    //             $newUser = new User();
    //             $newUser->name = $validatedData['name'];
    //             $newUser->contact_no = $validatedData['contact_number'];
    //             $newUser->save();
    //             return response()->json([
    //                 'user' => $newUser
    //             ]);
    //         } else {
    //             return response()->json([
    //                 'user' => $userExist
    //             ]);
    //         }
    //     } catch (\Exception $e) {
    //         $errorFrom = 'register';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::ErrorLog($errorFrom, $errorMessage, $priority);
    //         return 'Something Went Wrong';
    //     }
    // }
    // public function verifyOtp(Request $request)
    // {
    //     try {
    //         $validator = validator($request->all(), [
    //             'contact_number' => 'required|regex:/^\+(?:\d{1,3})\s?(?:\d{1,4})?\s?\d{1,14}(?:\s?\d{1,13})?$/',
    //             'verification_code' => 'required|numeric',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json($validator->errors(), 422);
    //         }

    //         $validatedData = $validator->validated();

    //         $token = getenv("TWILIO_AUTH_TOKEN");
    //         $twilio_sid = getenv("TWILIO_SID");
    //         $twilio_verify_sid = getenv("TWILIO_VERIFY_SID");
    //         $twilio = new Client($twilio_sid, $token);

    //         $verification = $twilio->verify->v2->services($twilio_verify_sid)
    //             ->verificationChecks
    //             ->create([
    //                 'code' => $validatedData['verification_code'],
    //                 'to' => $validatedData['contact_number']
    //             ]);

    //         if ($verification->valid) {
    //             $userExist = User::where('contact_no', $validatedData['contact_number'])->first();
    //             $token = $userExist->createToken($userExist->name)->accessToken;
    //             return response()->json([
    //                 'status' => 'success',
    //                 'token' => $token
    //             ]);
    //         }
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'invalid  verification code'
    //         ]);

    //     } catch (\Exception $e) {
    //         $errorFrom = 'verifyOtp';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::ErrorLog($errorFrom, $errorMessage, $priority);
    //         return 'Something Went Wrong';
    //     }
    // }
    // public function userInfo()
    // {
    //     try {
    //         $user = auth()->user();
    //         return $user->properties;
    //     } catch (\Exception $e) {
    //         $errorFrom = 'userInfo';
    //         $errorMessage = $e->getMessage();
    //         $priority = 'high';
    //         Helper::ErrorLog($errorFrom, $errorMessage, $priority);
    //         return 'Something Went Wrong';
    //     }
    // }
}
