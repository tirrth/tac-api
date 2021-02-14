<?php

namespace App\Http\Controllers;

// use Illuminate\Support\Facades\Auth;
// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Validator;
// use App\Models\User;
// use DB;
// use Carbon\Carbon;
// use Str;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\MediaPlatform;
use App\Models\UserAddedPlatform;
use App\Models\TempUser;
use App\Mail\UserVerification;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Log;
use DB;
use Validator;
use Str;
use Auth;
use Hash;
use Exception;

class AuthController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api', ['except' => ['login', 'register']]);
    // }

    public function login(Request $request)
    {
        //user_identifier = email, phone or username
        $rules = [
            'user_identifier' => 'required',
            'password' => 'required|string|min:6'
        ];

        $messages = [
            'user_identifier.required' => 'Email, phone or username is required.',
            'password.required' => 'Password is required.'
        ];

        // Validation of coming credentials
        $validator = Validator::make($request->json()->all(), $rules, $messages);
        if ($validator->fails())
        {
            return response()->json($validator->errors(), 400);
        }
        else
        {
            $data = $request->json()->all();

            if (filter_var($data['user_identifier'], FILTER_VALIDATE_EMAIL))
            {
                $credentials['email'] = $data['user_identifier'];
            }
            else if (preg_match('/^[0-9]*$/', $data['user_identifier']))
            {
                $credentials['phone'] = $data['user_identifier'];
            }
            else if(preg_match('/^[a-zA-Z0-9_]{2,}[a-zA-Z]+[0-9]*$/', $data['user_identifier'])){
                $credentials['username'] = $data['user_identifier'];
            }
            else
            {
                return response()->json(['message' => 'Invalid input.'], 400);
            }

            $credentials['password'] = $data['password'];

            if (! $token = auth()->attempt($credentials))
            {
                return response()->json(['message' => 'Credentials are not correct.'], 401);
            }
            else
            {
                if (auth()->user()->is_active)
                {
                    return $this->respondWithToken($token);
                }
                else
                {
                    return response()->json(['message'=> 'Your account has been de-activated.'], 200);
                }
            }
        }
    }

    // Method to store user in temporary table
    public function saveTempUser(Request $request)
    {
        $rules = [
            'email' => 'email|max:320',
            'phone' => 'numeric'
        ];

        $messages = [
            'email.email' => 'Email is not in a valid format.',
            'phone.numeric' => 'Phone is not in a valid format.'
        ];

        $validator = Validator::make($request->json()->all(), $rules, $messages);

        if ($validator->fails())
        {
            Log::info($request->json()->all());
            return response()->json($validator->errors(), 400);
        }
        else if(!$request->exists('phone') && !$request->exists('email')){
            $response['message'] = 'Either email or phone is required.';
            return response()->json($response, 400);
        }
        else
        {
            $data = $request->json()->all();

            try
            {
                // check whether email or phone already exist in our Users table or not
                if($request->exists('email')){
                    $activeEmailExists = User::where('email', $data['email'])->first();
                    if($activeEmailExists){
                        $response['email'] = 'One account already exists with this email address.';
                        return response()->json($response, 400);
                    }
                }
                if($request->exists('phone')){
                    $activePhoneExists = User::where('phone', $data['phone'])->first();
                    if ($activePhoneExists)
                    {
                        $response['phone'] = 'One account already exists with this phone number.';
                        return response()->json($response, 400);
                    }
                }

                DB::beginTransaction();

                try
                {
                    $random_otp = rand(1000, 9999);
                    $tempBuyer = new TempUser();

                    if($request->exists('email')){
                        $this->sendVerificationMail($data['email'], $random_otp);
                        $tempBuyer->email = $data['email'];
                    }
                    if($request->exists('phone')){
                        $tempBuyer->phone = $data['phone'];
                    }
                    $tempBuyer->otp = $random_otp;

                    $executeQuery = $tempBuyer->save();

                    if ($executeQuery)
                    {
                        DB::commit();
                        return response()->json([
                            'message' =>  $request->exists('email') ? 'OTP sent successfully to your email.' : 'OTP sent successfully to your phone.',
                            'otp' => $tempBuyer->otp,
                        ]);
                    }
                    else
                    {
                        DB::rollback();
                        return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
                    }
                }
                catch (Exception\Database\QueryException $e)
                {
                    Log::info('There was an error while storing users information in temp_users table. See the logs below.');
                    Log::info('Error: '.$e->getMesage());
                    Log::info('Error: Code: '.$e->getCode());
                    Log::info('Query: '.$e->getSql());
                    Log::info('Query: Bindings: '.$e->getBindings());

                    return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
                }
                catch (Exception $e)
                {
                    Log::info('There was an error while storing users information in temp_users table. See the logs below.');
                    Log::info('Error: '.$e->getMesage());
                    Log::info('Error: Code: '.$e->getCode());

                    return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
                }
            }
            catch (Exception\Database\QueryException $e)
            {
                Log::info('There is an error while checking if any email or phone already exists in Users table. See the logs below.');
                Log::info('Error: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());
                Log::info('Query: '.$e->getSql());
                Log::info('Query: Bindings: '.$e->getBindings());

                return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
            }
            catch (Exception $e)
            {
                Log::info('There is an error while checking if any email or phone already exists in Users table. See the logs below.');
                Log::info('Error: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());

                return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
            }
        }
    }

    // Method to change the flag to active in temp usera when the user is verified
    public function otpVerificationCheck(Request $request)
    {
        $rules = [
            'email' => 'email|max:320',
            'phone' => 'numeric',
            'otp' => 'required',
        ];

        $validator = Validator::make($request->json()->all(), $rules);

        Log::info($request->json()->all());

        if ($validator->fails())
        {
            return response()->json($validator->errors(), 400);
        }
        else if(!$request->exists('phone') && !$request->exists('email')){
            $response['message'] = 'Either email or phone is required.';
            return response()->json($response, 400);
        }
        else
        {
            $data = $request->json()->all();
            try
            {
                if($request->exists('email') && $request->exists('phone')){
                    $tempBuyer = TempUser::where('email', $data['email'])->where('phone', $data['phone'])->where('otp', $data['otp'])->first();
                }
                else if($request->exists('email')){
                    $tempBuyer = TempUser::where('email', $data['email'])->where('otp', $data['otp'])->first();
                }
                else{
                    $tempBuyer = TempUser::where('phone', $data['phone'])->where('otp', $data['otp'])->first();
                }

                if (! is_null($tempBuyer))
                {
                    if (! $tempBuyer->is_verified)
                    {
                        if ($tempBuyer->is_otp_active)
                        {
                            $difference = Carbon::now()->diffInSeconds($tempBuyer->updated_at);
                            if ($difference > 3600)
                            {
                                DB::beginTransaction();
                                try
                                {
                                    Log::info('Your otp is expired, generate another one.');
                                    $tempBuyer->is_otp_active = 0;
                                    $executeQuery = $tempBuyer->update();

                                    if ($executeQuery)
                                    {
                                        DB::commit();
                                        return response()->json(['message' => 'Your otp is expired, generate another one.'], 400);
                                    }
                                    else
                                    {
                                        DB::rollback();
                                        return response()->json(['message' => 'Internal Server Error. Could not verify your email and phone. Please try again later.'], 500);
                                    }
                                }
                                catch (Exception\Database\QueryException $e)
                                {
                                    DB::rollback();
                                    Log::info('There was an error while updating is_otp_active flag to 0. See the logs below.');
                                    Log::info('Query: '.$e->getSql());
                                    Log::info('Query: Bindings: '.$e->getBindings());
                                    Log::info('Error: Code: '.$e->getCode());
                                    Log::info('Error: Message: '.$e->getMessage());

                                    return response()->json(['message' => 'Internal Server Error. Could not verify your email and phone. Please try again later.'], 500);
                                }
                                catch (Exception $e)
                                {
                                    DB::rollback();
                                    Log::info('There was an error while updating is_otp_active flag to 0. See the logs below.');
                                    Log::info('Error: Code: '.$e->getCode());
                                    Log::info('Error: Message: '.$e->getMessage());

                                    return response()->json(['message' => 'Internal Server Error. Could not verify your email and phone. Please try again later.'], 500);
                                }
                            }
                            else
                            {
                                DB::beginTransaction();
                                try
                                {
                                    $tempBuyer->user_verified_at = Carbon::now()->format('Y-m-d H:i:s');
                                    // $tempBuyer->phone_verified_at = Carbon::now()->format('Y-m-d H:i:s');
                                    $tempBuyer->is_otp_active = false;
                                    $tempBuyer->is_verified = true;

                                    $executeQuery = $tempBuyer->update();

                                    if ($executeQuery)
                                    {
                                        DB::commit();
                                        return response()->json(['message' => 'Thank you for verifying your email address and phone. Continue ahead.']);
                                    }
                                    else
                                    {
                                        DB::rollback();
                                        return response()->json(['message' => 'Internal Server Error. Could not verify your email and phone. Please try again later.']);
                                    }
                                }
                                catch (Exception\Database\QueryException $e)
                                {
                                    DB::rollback();
                                    Log::info('There was an error while updating is_otp_active flag to 0. See the logs below.');
                                    Log::info('Query: '.$e->getSql());
                                    Log::info('Query: Bindings: '.$e->getBindings());
                                    Log::info('Error: Code: '.$e->getCode());
                                    Log::info('Error: Message: '.$e->getMessage());

                                    return response()->json(['message' => 'Internal Server Error. Could not verify your email and phone. Please try again later.'], 500);
                                }
                                catch (Exception $e)
                                {
                                    DB::rollback();
                                    Log::info('There was an error while updating is_otp_active flag to 0. See the logs below.');
                                    Log::info('Error: Code: '.$e->getCode());
                                    Log::info('Error: Message: '.$e->getMessage());

                                    return response()->json(['message' => 'Internal Server Error. Could not verify your email and phone. Please try again later.'], 500);
                                }
                            }
                        }
                        else
                        {
                            Log::info('Your otp is expired, generate another one.');
                            return response()->json(['message' => 'Your otp is expired, generate another one.'], 400);
                        }
                    }
                    else
                    {
                        return response()->json(['message' => 'Your email and phone are already verified. You can proceed further.']);
                    }
                }
                else
                {
                    return response()->json(['message' => 'Verify your email address and phone number to proceed further.'], 400);
                }
            }
            catch (Exception\Database\QueryException $e)
            {
                Log::info('There was an error while checking the buyer. See the logs below.');
                Log::info('Query: '.$e->getSql());
                Log::info('Query: Bindings: '.$e->getBindings());
                Log::info('Error: Code: '.$e->getCode());
                Log::info('Error: Message'.$e->getMessage());

                return response()->json(['message' => 'Internal Server Error. Could not verify your email and phone. Please try again later.'], 500);
            }
            catch (Exception $e)
            {
                Log::info('There was an error while checking the buyer. See the logs below.');
                Log::info('Error: Code: '.$e->getCode());
                Log::info('Error: Message: '.$e->getMessage());

                return response()->json(['message' => 'Internal Server Error. Could not verify your email and phone. Please try again later.'], 500);
            }
        }
    }

    // Save the buyer in the main table(users) after successful registration
    public function saveUserToMain(Request $request)
    {
        $rules = [
            'username' => ['regex:/^[a-zA-Z0-9_]{2,}[a-zA-Z]+[0-9]*$/', 'required', 'unique:users,username'],
            'email' => 'email|max:320',
            'phone' => 'numeric',
            'full_name' => 'required|max:60',
            'password' => 'required|max:125',
            'gender_id' => 'required|exists:genders,id',
            'profile_picture' => 'nullable|mimes:jpg,jpeg,bmp,png',
        ];

        $messages = [
            'username.regex' => 'Username is in invalid format.',
            'username.required' => 'Username is required.',
            'email.max' => 'Email is in invalid format.',
            'email.email' => 'Email is not in a valid format.',
            'phone.numeric' => 'Invalid phone number.',
            'full_name.required' => 'Full name is required.',
            'gender_id.exists' => 'Not selected valid gender.',
            'profile_picture.mimes' => 'Please select a file with jpg, jpeg, bmp or png extension.',
        ];

        $validator = Validator::make($request->json()->all(), $rules, $messages);

        if ($validator->fails())
        {
            return response()->json($validator->errors(), 400);
        }
        else
        {
            try
            {
                $data = $request->json()->all();

                if($request->exists('email') && $request->exists('phone')){
                    $tempBuyerExists = TempUser::where('email', $data['email'])->where('phone', $data['phone'])->where('is_verified', true)->first();
                }
                else if($request->exists('email')){
                    $tempBuyerExists = TempUser::where('email', $data['email'])->where('is_verified', true)->first();
                }
                else if($request->exists('phone')){
                    $tempBuyerExists = TempUser::where('phone', $data['phone'])->where('is_verified', true)->first();
                }

                if (is_null($tempBuyerExists))
                {
                    return response()->json(['message' => 'Verify your email address and phone number before registration.'], 400);
                }
                else
                {
                    try
                    {
                        if($request->exists('email')){
                            $emailExistInBuyersTable = User::where('email', $data['email'])->first();
                            if (! is_null($emailExistInBuyersTable))
                            {
                                $response['email'] = "One account already exists with this email address.";
                                return response()->json($response, 400);
                            }
                        }

                        if($request->exists('phone')){
                            $phoneExistInBuyersTable = User::where('phone', $data['phone'])->first();
                            if (! is_null($phoneExistInBuyersTable))
                            {
                                $response['phone'] = "One account already exists with this phone number.";
                                return response()->json($response, 400);
                            }
                        }

                        DB::beginTransaction();
                        try
                        {
                            $buyer = new User;

                            $buyer->uuid = Str::uuid();
                            $buyer->username = $data['username'];
                            if($request->exists('email')){
                                $buyer->email = $data['email'];
                            }
                            if($request->exists('phone')){
                                $buyer->phone = $data['phone'];
                            }
                            $buyer->full_name = $data['full_name'];
                            $buyer->password = $data['password'];
                            $buyer->is_active = true;
                            $buyer->gender_id = $data['gender_id'];
                            $buyer->user_verified_at = $tempBuyerExists->user_verified_at;
                            // $buyer->term_conditions_agreed = true;

                            $executeQuery = $buyer->save();

                            if ($executeQuery)
                            {
                                DB::commit();
                                return response(['message' => 'User registered successfully.']);
                            }
                            else
                            {
                                DB::rollback();
                                return response(['message' => 'Internal Server Error. User could not be registered. Please try again later.'], 500);
                            }
                        }

                        catch (Exception\Database\QueryException $e)
                        {
                            DB::rollback();
                            Log::info('There was an error while storing information of buyer in registration process with email: See the logs below.');
                            Log::info('Query: '.$e->getSql());
                            Log::info('Query: Bindings: '.$e->getBindings());
                            Log::info('Error: Message: '.$e->getMessage());
                            Log::info('Error: Code: '.$e->getCode());

                            return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
                        }
                        catch (Exception $e)
                        {
                            DB::rollback();
                            Log::info('There was an error while storing information of buyer in registration process with email: See the logs below.');
                            Log::info('Error: Message: '.$e->getMessage());
                            Log::info('Error: Code: '.$e->getCode());

                            return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
                        }

                    }
                    catch (Exception\Database\QueryException $e)
                    {
                        Log::info('There was an error while checking if the buyer already exists in buyers. See the logs below.');
                        Log::info('Query: '.$e->getSql());
                        Log::info('Query: Bindings: '.$e->getBindings());
                        Log::info('Error: Message: '.$e->getMessage());
                        Log::info('Error: Code: '.$e->getCode());

                        return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
                    }
                    catch (Exception $e)
                    {
                        Log::info('There was an error while checking if the buyer already exists in buyers table with email:. See the logs below.');
                        Log::info('Error: Message: '.$e->getMessage());
                        Log::info('Error: Code: '.$e->getCode());

                        return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
                    }
                }
            }
            catch (Exception\Database\QueryException $e)
            {
                Log::info('There was an error while checking if the verified buyer exists in temp_buyers table with the information of email:. See the logs below.');
                Log::info('Query: '.$e->getSql());
                Log::info('Query: Bindings: '.$e->getBindings());
                Log::info('Error: Message: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());

                return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
            }
            catch (Exception $e)
            {
                Log::info('There was an error while checking if the verified buyer exists in temp_buyers table with the information of email:. See the logs below.');
                Log::info('Error: Message: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());

                return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
            }
        }
    }

    public function resendOTP(Request $request)
    {
        $rules = [
            'user_identifier' => 'required'
        ];

        $messages = [
            'user_identifier.required' => 'User-Identifier is required.'
        ];

        $validator = Validator::make($request->json->all(), $rules, $messages);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }
        else{
            try{
                $data = $request->json()->all();

                if (filter_var($data['user_identifier'], FILTER_VALIDATE_EMAIL))
                {
                    $activeUserExists = User::where('email', $data['user_identifier'])->where('is_active',1)->first();
                    $key = 'email';
                }
                else if (preg_match('/^[0-9]*$/', $data['user_identifier']))
                {
                    $activeUserExists = User::where('phone', $data['user_identifier'])->where('is_active',1)->first();
                    $key = 'phone';
                }
                else if(preg_match('/^[a-zA-Z0-9_]{2,}[a-zA-Z]+[0-9]*$/', $data['user_identifier'])){
                    $activeUserExists = User::where('username', $data['user_identifier'])->where('is_active',1)->first();
                    $key = 'email';
                }
                else{
                    $activeUserExists = User::where('username', $data['user_identifier'])->where('is_active',1)->first();
                    $key = 'email';
                }

                if(is_null($activeUserExists))
                {
                    return response()->json(array('message'=> 'Invalid Input.'), 400);
                }
                else
                {
                    $user_otps = TempUser::where($key, $activeUserExists->$key)->where('is_otp_active',1)->get();

                    foreach($user_otps as $otp)
                    {
                        $otp->is_otp_active = 0;
                        $executeQuery = $otp->update();
                        if(! $executeQuery)
                        {
                            DB::rollback();
                            return response()->json(array('success' =>'false', 'message' => 'Internal server error.please try again later.'), 500);
                        }
                    }

                    $tempOtp = new TempUser;

                    $tempOtp->$key = $activeUserExists->$key;
                    $tempOtp->otp = rand(1000, 9999);
                    $executeQuery = $tempOtp->save();
                    if ($executeQuery)
                    {
                        DB::commit();
                        return response()->json([
                            'message' => ($key === 'email' ? 'OTP has been successfully to email - ' : 'OTP has been successfully to phone - ').$activeUserExists->$key,
                            'user_id' => $activeUserExists->$key,
                            'otp' => $tempOtp->otp,
                        ]);
                    }
                    else
                    {
                        DB::rollback();
                        return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
                    }
                }

            }
            catch (\Exception\Database\QueryException $e)
            {
                Log::info('Error: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());
                Log::info('Query: '.$e->getSql());
                Log::info('Query: Bindings: '.$e->getBindings());
                return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
            }
            catch (\Exception $e)
            {
                Log::info('Error: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());
                return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
            }
        }
    }

    public function changePassword(Request $request)
    {
        if(auth()->check())
        {
            $rules = array(
                'current_password' => 'required',
                'new_password' => 'required',
                'confirm_new_password' => 'required|same:new_password'
            );
            $messages = [
                'current_password.required' => 'Current Password is required.',
                'new_password.required' => ' New Password is required.',
                'confirm_new_password.required' => 'Confirm New Password is required.',
                'confirm_new_password.same' => 'This Password is same for current_password.'
            ];

            $validator = Validator::make($request->json()->all(), $rules, $messages);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }
            else {
                try {
                    $data = $request->json()->all();

                    $buyer = User::where('id', auth()->user()->id)->where('is_active', 1)->first();

                    if(Hash::check($data['current_password'], auth()->user()->password))
                    {
                        $buyer->password = $data['new_password'];
                        $buyer->update();

                        return response()->json(array(
                            'success' => 'true',
                            'message' => 'Password changed successfully.'),
                            200
                        );

                    }
                    else
                    {
                        return response()->json(['message' => 'Password is not correct.'], 400);
                    }
                }
                catch (\Exception\Database\QueryException $e)
                {

                    Log::info('Query: '.$e->getSql());
                    Log::info('Query: Bindings: '.$e->getBindings());
                    Log::info('Error: Code: '.$e->getCode());
                    Log::info('Error: Message: '.$e->getMessage());
                    return response()->json(array('success' =>'false', 'message' => 'Internal server error'), 500);

                }
                catch (\Exception $e)
                {

                    Log::info('Error: Code: '.$e->getCode());
                    Log::info('Error: Message: '.$e->getMessage());
                    return response()->json(array('success' =>'false', 'message' => 'Internal server error'), 500);

                }
                return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
            }
        }
        else
        {
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function forgotPasswordOtp(Request $request)
    {
        $rules = [
            'user_identifier' => 'required'
        ];
        $messages = [
            'user_identifier.required' => 'You must enter your identification.'
        ];

        $validator = Validator::make($request->json()->all(), $rules, $messages);

        if ($validator->fails())
        {
            return response()->json($validator->errors(), 400);
        }
        else
        {
            $data = $request->json()->all();

            if (filter_var($data['user_identifier'], FILTER_VALIDATE_EMAIL))
            {
                $activeUserExists = User::where('email', $data['user_identifier'])->where('is_active',1)->first();
                $key = 'email';
            }
            else if (preg_match('/^[0-9]*$/', $data['user_identifier']))
            {
                $activeUserExists = User::where('phone', $data['user_identifier'])->where('is_active',1)->first();
                $key = 'phone';
            }
            else if(preg_match('/^[a-zA-Z0-9_]{2,}[a-zA-Z]+[0-9]*$/', $data['user_identifier'])){
                $activeUserExists = User::where('username', $data['user_identifier'])->where('is_active',1)->first();
                $key = 'email';
            }
            else{
                $activeUserExists = User::where('username', $data['user_identifier'])->where('is_active',1)->first();
                $key = 'email';
            }

            try
            {
                if(is_null($activeUserExists))
                {
                    return response()->json(array('message'=> 'Invalid Input.'), 400);
                }
                else
                {
                    $user_otps = TempUser::where($key, $activeUserExists->$key)->where('is_otp_active', 1)->get();

                    foreach($user_otps as $otp)
                    {
                        $otp->is_otp_active = 0;
                        $executeQuery = $otp->update();
                        if(! $executeQuery)
                        {
                            DB::rollback();
                            return response()->json(array('success' =>'false', 'message' => 'Internal server error.please try again later.'), 500);
                        }
                    }

                    DB::beginTransaction();
                    try
                    {
                        $tempOtp = new TempUser;

                        $tempOtp->$key = $activeUserExists->$key;
                        $tempOtp->otp = rand(1000, 9999);
                        $tempBuyer->is_verified = true;
                        $executeQuery = $tempOtp->save();
                        if ($executeQuery)
                        {
                            DB::commit();
                            return response()->json([
                                'message' => ($key === 'email' ? 'OTP has been successfully to email - ' : 'OTP has been successfully to phone - ').$activeUserExists->$key,
                                'user_id' => $activeUserExists->$key,
                                'otp' => $tempOtp->otp,
                            ]);
                        }
                        else
                        {
                            DB::rollback();
                            return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
                        }
                    }
                    catch (\Exception\Database\QueryException $e)
                    {
                        DB::rollback();
                        Log::info('Error: '.$e->getMessage());
                        Log::info('Error: Code: '.$e->getCode());
                        Log::info('Query: '.$e->getSql());
                        Log::info('Query: Bindings: '.$e->getBindings());

                        return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
                    }
                    catch (\Exception $e)
                    {
                        DB::rollback();
                        Log::info('Error: '.$e->getMessage());
                        Log::info('Error: Code: '.$e->getCode());

                        return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
                    }
                }
            }
            catch (\Exception\Database\QueryException $e)
            {
                Log::info('Error: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());
                Log::info('Query: '.$e->getSql());
                Log::info('Query: Bindings: '.$e->getBindings());

                return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
            }
            catch (\Exception $e)
            {
                Log::info('Error: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());

                return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
            }
        }
    }

    public function forgotPassword(Request $request)
    {
        $rules = [
            'user_identifier' => 'required',
            'otp'=>'required',
            'password' => 'required|max:500',
            'confirm_password' => 'required|max:500|same:password',
        ];

        $messages = [
            'user_identifier.required' => 'Phone number is required.',
            'otp.required' => 'OTP is required.',
            'password.required' => 'Password is required.',
            'confirm_password.required' => 'confirm password is required.',
            'confirm_password.same'=> 'please enter the same password.'
        ];

        $validator = Validator::make($request->json()->all(), $rules, $messages);

        if ($validator->fails())
        {
            return response()->json($validator->errors(), 400);
        }
        else
        {
            try
            {
                $data = $request->json()->all();

                if (filter_var($data['user_identifier'], FILTER_VALIDATE_EMAIL))
                {
                    $forgotPassword = User::where('email', $data['user_identifier'])->where('is_active',1)->first();
                    $key = 'email';
                }
                else if (preg_match('/^[0-9]*$/', $data['user_identifier']))
                {
                    $forgotPassword = User::where('phone', $data['user_identifier'])->where('is_active',1)->first();
                    $key = 'phone';
                }
                else if(preg_match('/^[a-zA-Z0-9_]{2,}[a-zA-Z]+[0-9]*$/', $data['user_identifier']))
                {
                    $forgotPassword = User::where('username', $data['user_identifier'])->where('is_active',1)->first();
                    $key = 'email';
                }
                else{
                    $forgotPassword = User::where('username', $data['user_identifier'])->where('is_active',1)->first();
                    $key = 'email';
                }
                Log::info($forgotPassword->$key);

                if (is_null($forgotPassword))
                {
                    return response()->json(['message' => 'Verify your phone number before registration.'], 400);
                }
                else
                {
                    $checkpasswordUser = TempUser::where($key, $forgotPassword->$key)->where('otp',$data['otp'])->where('is_verified', 1)->first();

                    if(is_null($checkpasswordUser))
                    {
                        return response(['success'=> 'false','message' =>'Bad request'], 400);
                    }
                    else
                    {
                        DB::beginTransaction();
                        try
                        {
                            $forgotPassword->password = $data['password'];
                            $checkpasswordUser->is_otp_active = false;
                            $executeQueryOne = $forgotPassword->update();
                            $executeQueryTwo = $checkpasswordUser->update();

                            if ($executeQueryOne && $executeQueryTwo)
                            {
                                DB::commit();
                                return response(['message' => 'User Password change successfully.']);
                            }
                            else
                            {
                                DB::rollback();
                                return response(['message' => 'Internal Server Error. User could not be change password. Please try again later.'], 500);
                            }

                        }
                        catch (Exception\Database\QueryException $e)
                        {
                            DB::rollback();
                            Log::info('There was an error while storing information of user in registration process. See the logs below.');
                            Log::info('Query: '.$e->getSql());
                            Log::info('Query: Bindings: '.$e->getBindings());
                            Log::info('Error: Message: '.$e->getMessage());
                            Log::info('Error: Code: '.$e->getCode());

                            return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
                        }
                        catch (Exception $e)
                        {
                            DB::rollback();
                            Log::info('There was an error while storing information of user in registration process. See the logs below.');
                            Log::info('Error: Message: '.$e->getMessage());
                            Log::info('Error: Code: '.$e->getCode());

                            return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
                        }
                    }
                }
            }
            catch (\Exception\Database\QueryException $e)
            {
                Log::info('Error: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());
                Log::info('Query: '.$e->getSql());
                Log::info('Query: Bindings: '.$e->getBindings());

                return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
            }
            catch (\Exception $e)
            {
                Log::info('Error: '.$e->getMessage());
                Log::info('Error: Code: '.$e->getCode());

                return response()->json(['message' => 'Internal Server Error. OTP could not be sent. Please try again later.'], 500);
            }
        }
    }

    public function userProfile()
    {
        $user_info = auth()->user();
        Log::info($user_info);
        $user_info['avatar'] = is_null($user_info['avatar']) ? null : env('WEB_URL').$user_info['avatar'];
        unset($user_info['password']);
        // unset($user_info['uuid']);
        return response()->json($user_info);
    }

    public function getUserProfile(Request $request, $user_uuid){
        $user_info = User::where('uuid', $user_uuid)->first();
        $user_info['avatar'] = is_null($user_info['avatar']) ? null : env('WEB_URL').$user_info['avatar'];
        unset($user_info['password']);
        unset($user_info['uuid']);
        $data = MediaPlatform::with(['getUserPlatformRelation' => function($platform) use ($user_info){
            $platform
            ->where('user_id', $user_info['id'])
            ->where('is_active', true);
        }])->get();

        $platforms = array();
        foreach($data as $item){
            if(! is_null($item->getUserPlatformRelation)){
                $item['logo_url'] = is_null($item['logo_url']) ? null : env('WEB_URL').$item['logo_url'];
                $item['redirection_url'] = $item['base_url'].$item->getUserPlatformRelation->platform_username;
                $item['is_user_added_platform'] = false;
                array_push($platforms, $item);
            }
        }

        $user_added_platforms = UserAddedPlatform::where('user_id', $user_info['id'])->where('is_active', 1)->get();
        foreach($user_added_platforms as $item){
            $item['logo_url'] = is_null($item['logo_url']) ? null : env('WEB_URL').$item['logo_url'];
            $item['name'] = $item['platform_name'];
            $item['is_user_added_platform'] = true;
            unset($item['platform_name']);
            array_push($platforms, $item);
        }

        $user_info['platforms'] = $platforms;
        return response()->json($user_info);
    }

    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    public function updateProfile(Request $request)
    {
        if (auth()->check())
        {
            if (! auth()->user()->is_active)
            {
                return response()->json(['message' => 'You access is forbidden.'], 403);
            }

            // Validator::extend('key_value_pair', function($attribute, $value, $parameters) {
            //     foreach($value as $k => $v)
            //     {

            //         if (empty($k) || empty($v) || is_null($k) || is_null($v))
            //         {
            //             return false;
            //         }
            //     }
            //     return true;
            // });

            // validation rule for checking if the file is image and file size
            Validator::extend('image_type', function($attribute, $value, $parameters){
                $validMimeTypes = ['image/bmp', 'image/jpeg', 'image/jpg', 'image/png'];
                $image = base64_decode(explode(';base64,', $value)[1]);
                $f = finfo_open();
                $imageMimeType = finfo_buffer($f, $image, FILEINFO_MIME_TYPE);

                return in_array($imageMimeType, $validMimeTypes);
            });

            // validating image size
            Validator::extend('image_size', function($attribute, $value, $parameters) {
                $valid_size = 5120; // 5120 KB = 5 MB
                $image = base64_decode(explode(';base64,', $value)[1]);

                return $valid_size >= (strlen($image)/1024);
            });

            $rules = [
                // 'username' => ['regex:/^[a-zA-Z0-9_]{2,}[a-zA-Z]+[0-9]*$/', 'required', Rule::unique('users')->ignore(auth()->user()->username, 'username')],
                'username' => ['regex:/^[a-zA-Z0-9_]{2,}[a-zA-Z]+[0-9]*$/', 'required', 'unique:users,username,'.auth()->user()->id],
                'full_name' => 'required|max:60',
                'profile_picture' => 'nullable|image_type|image_size',
                'bio' => 'nullable',
                'web_url' => 'nullable'
            ];

            $messages = [
                'username.regex' => 'Username is in invalid format.',
                'username.required' => 'Username is required.',
                'username.unique' => 'Username is used.',
                'full_name.max' => 'Fullname is required.',
                'profile_picture.mimes' => 'Please select a file with jpg, jpeg, bmp or png extension.'
            ];

            $validator = Validator::make($request->json()->all(), $rules, $messages);

            if ($validator->fails())
            {
                return response()->json($validator->errors(), 400);
            }
            else
            {
                try
                {
                    $data = $request->json()->all();

                    if(isset($data['profile_picture'])){
                        $avatar_image = base64_decode(explode(';base64,', $data['profile_picture'])[1]);

                        $f = finfo_open();

                        $imageMimeType = finfo_buffer($f, $avatar_image, FILEINFO_MIME_TYPE);

                        $avatar_image_extension = explode('/', $imageMimeType)[1];

                        $image_path = '/images/avatars/'.time().Str::random(10).rand(9999, 99999).'.'.$avatar_image_extension;

                        $fileUploaded = file_put_contents(public_path().$image_path, $avatar_image);

                        if (! $fileUploaded)
                        {
                            DB::rollback();

                            Log::info('There was an error while uploading product image');

                            return response()->json(['message' => 'Internal Server Error. Product could not be saved. Please try again later.'], 500);
                        }
                    }

                    $user = User::where('id', auth()->user()->id)->first();
                    if($user){
                        if(isset($data['profile_picture'])){
                            // $user->avatar = NULL;
                            $user->avatar = $image_path;
                        }

                        $user->username = $data['username'];
                        $user->full_name = $data['full_name'];
                        $user->bio = $data['bio'];
                        $user->web_url = $data['web_url'];

                        $executeQuery = $user->update();
                        if(! $executeQuery)
                        {
                            DB::rollback();
                            return response()->json(array('success' =>'false', 'message' => 'Internal server error. Please try again later.'), 500);
                        }
                        else{
                            DB::commit();
                            return response()->json(array('success' =>'true', 'message' => 'Profile updated successfully.'), 200);
                        }
                    }
                    else{
                        return response()->json(['message' => 'User does not exist. Please Log out and try again.'], 500);
                    }
                }
                catch (Exception\Database\QueryException $e)
                {
                    Log::info('There was an error while checking if the verified buyer exists in temp_buyers table with the information of email:. See the logs below.');
                    Log::info('Query: '.$e->getSql());
                    Log::info('Query: Bindings: '.$e->getBindings());
                    Log::info('Error: Message: '.$e->getMessage());
                    Log::info('Error: Code: '.$e->getCode());

                    return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
                }
                catch (Exception $e)
                {
                    Log::info('There was an error while checking if the verified buyer exists in temp_buyers table with the information of email:. See the logs below.');
                    Log::info('Error: Message: '.$e->getMessage());
                    Log::info('Error: Code: '.$e->getCode());

                    return response()->json(['message' => 'Internal Server Error. Registration failed. Please try again later.'], 500);
                }
            }

        }
        else{
            return response()->json(['message' => 'You are Un-authorized.'], 401);
        }
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ], 200);
    }

    // public function sendMail(Request $request){
    //     return $this->sendVerificationMail($request->json()->all()['email'], 3298);
    // }

    protected function sendVerificationMail($email, $otp){
        return Mail::mailer('smtp')->to($email)->send(new UserVerification($otp));
    }
}
