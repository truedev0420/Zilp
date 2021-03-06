<?php

namespace App\Http\Controllers;

use App\RegisteredPlate;
use App\User;
use Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserController extends Controller
{
    // User sign up controller
    public function signUp(Request $request): JsonResponse
    {
        // Validate data
        $request->validate([
            'plateNumber' => 'required|alpha_num|min:3|max:20',
            'email' => 'required|email|unique:users',
            'firstName' => 'required|alpha',
            'lastName' => 'required|alpha',
            'password' => 'required|min:5',
            'phone' => 'required|numeric|unique:users'
        ]);

        // Check if plate number exists (owner)
        $plate_number = RegisteredPlate::where([
            'plate_number' => trim(strtoupper($request->input('plateNumber'))),
            'is_owner' => true
        ])->first();

        if ($plate_number) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'plateNumber' => [
                        "validation.unique"
                    ]
                ]
            ]);
        }

        // Create user
        $new_user = new User;

        $new_user->plate_number = trim(strtoupper($request->input('plateNumber')));
        $new_user->email = trim($request->input('email'));
        $new_user->first_name = trim($request->input('firstName'));
        $new_user->last_name = trim($request->input('lastName'));
        $new_user->password = Hash::make($request->input('password'));
        $new_user->phone = trim($request->input('phone'));

        $new_user->save();

        // Create registered plate
        $new_registered_plate = new RegisteredPlate;

        $new_registered_plate->user_id = $new_user->id;
        $new_registered_plate->plate_number = trim(strtoupper($request->input('plateNumber')));
        $new_registered_plate->is_owner = true;

        $new_registered_plate->save();

        // Send verification email with hash
        Log::info("Hash : " . Hash::make($new_user->created_at));
        self::sendVerificationEmail($new_user);
        
        return response()->json([
            'success' => true,
        ]);
    }

    public function sendVerificationEmail($receiver){
        $to      = $receiver->email; // Send email to our user
        $subject = 'Signup | Verification'; // Give the email a subject 
        $message = '
        
        Thanks for signing up!
        Your account has been created, you can login with the following credentials after you have activated your account by pressing the url below.
        
        Please click this link to activate your account:
        http://78.140.220.40:8080/api/v1/email-verification='.$receiver->email.'&hash='. Hash::make($receiver->created_at) .'
        
        '; // Our message above including the link
                            
        $headers = 'From:noreply@zilptext.com' . "\r\n"; // Set from headers
        mail($to, $subject, $message, $headers); // Send our email
    }

    public function emailVerification(Request $request){
        $email = $request->input('email');
        $hash = $request->input('hash');

        $user = User::where('email', $email);
        if($user && Hash::check($hash, $user->created_at)){
            $user->verified = 1;
            $user->save();
        }
    }

    public function socialSignin(Request $request): JsonResponse{
        $user = User::where('first_name', $request->input('firstName'))
            ->where('last_name', $request->input('lastName'))
            ->first();

        if(!$user){
            return response()->json([
                'success' => false,
                'error' => 'wrong_credentials'
            ], 401);
        }
            
        return response()->json([
            'success' => true,
            'token' => auth('api')->attempt(['email' => $user->email, 'password' => 'password']),
            'userId' => $user->id,
        ]);
    }

    public function socialSignup(Request $request) : JsonResponse{
        $request->validate([
            'plateNumber' => 'required|alpha_num|min:3|max:20',
            'firstName' => 'required|alpha',
            'email' => 'required|email|unique:users',
            'lastName' => 'required|alpha',
            'phone' => 'required|numeric|unique:users'
        ]);

        // Check if plate number exists (owner)
        $plate_number = RegisteredPlate::where([
            'plate_number' => trim(strtoupper($request->input('plateNumber'))),
            'is_owner' => true
        ])->first();

        if ($plate_number) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'plateNumber' => [
                        "validation.unique"
                    ]
                ]
            ]);
        }

        // Create user
        $new_user = new User;

        $new_user->plate_number = trim(strtoupper($request->input('plateNumber')));
        $new_user->first_name = trim($request->input('firstName'));
        $new_user->last_name = trim($request->input('lastName'));
        $new_user->phone = trim($request->input('phone'));
        $new_user->email = trim($request->input('email'));
        $new_user->password = Hash::make('password');

        $new_user->save();

        // Create registered plate
        $new_registered_plate = new RegisteredPlate;

        $new_registered_plate->user_id = $new_user->id;
        $new_registered_plate->plate_number = trim(strtoupper($request->input('plateNumber')));
        $new_registered_plate->is_owner = true;

        $new_registered_plate->save();

        // Send verification email with hash
        
        return response()->json([
            'success' => true,
            'token' => auth('api')->attempt(['email' => $request->input('email'), 'password' => 'password']),
            'userId' => $new_user->id,
        ]);
    }

    // User authenticate/login controller
    public function authenticate(Request $request): JsonResponse
    {
        // Get user based on email or plate number
        $user = User::where('phone', $request->input('loginId'))
            ->orWhere('email', $request->input('loginId'))
            ->first();

        if ($user) {
            // Check password
            if (Hash::check($request->input('password'), $user->password)) {

                // Check if the account is verified
                if ($user->verified) {
                    // Return token (attempt) and success message
                    return response()->json([
                        'success' => true,
                        'token' => auth('api')->attempt(['email' => $user->email, 'password' => $request->input('password')]),
                        'userId' => $user->id,
                        'firstname' => $user->first_name,
                        'lastname'  => $user->last_name
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'account_not_verified'
                    ], 401);
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'wrong_credentials'
            ], 401);
        }

        return response()->json([
            'success' => false,
            'error' => 'wrong_credentials'
        ], 401);
    }

    // Authorize the user
    public function checkAuthentication(Request $request): JsonResponse
    {
        // The middle ware will take care of error
        return response()->json([
            'success' => true
        ]);
    }

    // Profile pic
    public function profilePic($user_id): BinaryFileResponse
    {
        $user = User::where('id', $user_id)->first();
        
        if ($user) {
            return response()->file(storage_path('app/profile_pics/' . $user->profile_pic));
            // return Storage::url($user->profile_pic);
        }

        return ErrorsController::userNotFoundError();
    }
}
