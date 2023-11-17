<?php
 
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Http\Requests\RegisterAuthRequest;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Hash;

// 3rd part packages
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Validator;
use Twilio\Rest\Client;

// models
use App\Models\User;
use App\Models\Role;
// traits
use App\Traits\UserTrait;
use App\Traits\TwilioTrait;
// notication
use App\Notifications\OtpNotification;

class ApiAuthController extends Controller
{
    use UserTrait;
    use TwilioTrait;

    public $loginAfterSignUp = true;
 
    public function register(Request $request)
    {
        $response = $this->createOrUpdateUser($request);
        
        if ($response && $response->getStatusCode() == 400) {
            return $response;
        }

        if ($response && $response->getStatusCode() == 201 ) {
            return $response;
        }
        // dd(json_decode($response->getContent(), true));
        if ($this->loginAfterSignUp ) {
            return $this->login($request);
        }
        return $response;
    }
 
    public function login(Request $request)
    {
        $input = $request->only('email', 'password');
        $jwt_token = null;
        
        // dd($request->all());
        $user = User::with('user_detail')->where('email',$request->email)
            ->orWhereHas('user_detail',function($q) use($request){
                $q->where('phone',$request['phone']);
            })
            ->first();
        
        if (!@$user->is_verified) 
        {
            if (!$jwt_token = JWTAuth::attempt($input)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Email or Password',
                ], 401);
            }
            return response()->json([
                'success' => false,
                'data' => $user,
                'message' => 'Please verify your account first!',
                'action_require' => true
            ], 401);
        }
        if ($request['verified']) 
        {
            
            if (!$jwt_token=JWTAuth::fromUser($user)) 
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Credentials',
                ], 401);
            }
            Auth::login($user);
        }
        else
        {
            if (!$jwt_token = JWTAuth::attempt($input)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Email or Password',
                ], 401);
            }
        }
        
        // dd(Auth::user());
        $user = Auth::user();
        $data['name'] = $user->name;
        $data['email'] = $user->email;
        $data['role'] = $user->role->name;
        $data['is_shop'] = ($user->user_business && $user->user_business->id?true:false);
        
        return response()->json([
            'success' => true,
            'token' => $jwt_token,
            'data' => $data,
        ]);
    }
 
    public function logout(Request $request)
    {

        $token['token'] = $request->bearerToken();
        //valid credential
        $validator = Validator::make($token, [
            'token' => 'required'
        ]);

        //Send failed response if request is not valid
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->messages()
            ], 400);
        }
 
        try {
            JWTAuth::invalidate($request->token);
 
            return response()->json([
                'success' => true,
                'message' => 'User logged out successfully'
            ]);
        } catch (JWTException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, the user cannot be logged out'
            ], 500);
        }
    }
 
    public function getAuthUser(Request $request)
    {
        $token['token'] = $request->bearerToken();
        //valid credential
        $validator = Validator::make($token, [
            'token' => 'required'
        ]);

        //Send failed response if request is not valid
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->messages()
            ], 400);
        }
        
        $user = JWTAuth::authenticate($request->token);
        $decode_user = json_decode($user);

        $user = User::with('user_detail')->find($decode_user->id);

        $full_name = explode(' ',$user->name);
        $user->first_name = $full_name[0];
        // dd($user);
        // $user['role_name'] = $user->role->name;
        // $user->last_name = null;
        if ((array_key_exists(1, $full_name))) 
        {
            $user->last_name = $full_name[1].' '.(array_key_exists(2, $full_name)?$full_name[2]:'');
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function sendOtp(Request $request)
    {
        // dd($request->all());
        if($request['type'] == 'phone')
        {
            if (!$request['phone']) 
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number is not found, please try other option!',
                    'action_require' => true
                ], 400);
            
            }
            $data['phone'] = $request['phone'];
            $data['method'] = "sms";

            $twilio_response = self::sentTwilioOtp($data);
            if ($twilio_response['success'] != true) 
            {
                return response()->json($twilio_response, 400);
            }
            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to phone number',
                'action_require' => true
            ], 200);
        }

        if ($request['type'] == 'email')
        {
            $user = $this->generateOtp($request);
            $user->notify(new OtpNotification());
            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to email',
                'action_require' => true
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Please Select Verification Platform',
            'action_require' => true
        ], 400);
    }

    public function otpVerify(Request $request)
    {
        // dd($request->all());
        if ($request['type'] == 'phone')
        {
            
            $response = TwilioTrait::verifyOtp($request);
        }

        if ($request['type'] == 'email')
        {
            $response = $this->verifyEmailOtp($request);
        }

        if ($response['success'] != true) 
        {
            return response()->json($response, 400);
        }

        if ($request->method == 'forget-password') 
        {
            $user = $response['data'];
            if($user)
            {
                $user->two_factor_code = rand(000000000,999999999);
                // $user->password = Hash::make($request->password);
                $user->save(); 
                $response['token'] = $user->two_factor_code;
            }
            return response()->json($response, 200);
        }
        else
        {
            $request['verified'] = true;
            return $this->login($request);
        }

        

    }

    public function resetPassword(Request $request)
    {
        $user = User::where('two_factor_code',$request->code)->where('email',$request->email)
            ->orWhereHas('user_detail',function($q) use($request){
                $q->where('phone',$request['phone']);
            })
            ->first();
        if ($user) 
        {
            $user->password = Hash::make($request->password);
            $user->save(); 
            $request['verified'] = true;
            return $this->login($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid Credentials or token',
        ], 401);
    }
}