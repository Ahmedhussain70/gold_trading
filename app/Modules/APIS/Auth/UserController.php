<?php

namespace App\Modules\APIS\Auth;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Http\Resources\UserResource;
use App\Http\Trait\UploadImage;
use App\Models\User;
use Carbon\Carbon;
use Clients\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Otp;
use App\Models\UserAddress;
use Illuminate\Support\Facades\Validator;
use Wallet\Models\Wallet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{

    use UploadImage;

    public function login(Request $request){
        $customMessages = [
            'email.required' => transWord('email is required'),
            'password.required' => transWord('password number is required'),
        ];

        $validateData = Validator::make($request->all(), [
            "email" => "required",
            "password" => "required",
        ],$customMessages);

        if($validateData->fails()){
            $errors = $validateData->errors();
            return response()->json([
                'success' => false,
                'errors' => $errors,
                'results' => null
            ],401);
        }

        $email = $request->email;
        $password = $request->password;
        $device_token = $request->device_token;


        $checkCredentials =
                Auth::attempt([
                    'email' => $email,
                    'password' => $password
                ]);
                $user = Auth::user();

            if($checkCredentials){  // check if credentials (mobile & password) is right
                $data = new UserResource($user);
                return response()->json([
                    'success' => true,
                    'message' => transWord("Login Successfully"),
                    'token' => $user->createToken("API TOKEN")->plainTextToken,
                    'result' => $data,
                ],200);

            }else{ // credentials are wrong
                return response()->json([
                    'success' => false,
                    'message' => transWord("Mobile Or Password is wrong"),
                    'result' => null,
                ],401);
            }
    }

    // public function logout(){
    //     auth()->user()->logout();
    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("Logout is done successfully"),
    //         'result' => null,
    //     ],200);
    // }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => transWord("Logout is done successfully"),
            'result'  => null,
        ], 200);
    }
    
    public function profile(){
        $user = auth()->user();
        $data = new UserResource($user);
        return response()->json([
            'success' => true,
            'message' => transWord("User Profile"),
            'result' => $data,
        ],200);
    }

    // public function updateProfile(Request $request){
    //     $user = auth()->user();
    //     $user_data = User::findOrfail($user->id);
    //     $customMessages = [
    //         'name.required' => transWord('name is required'),
    //         'mobile_number.required' => transWord('mobile is required'),
    //         'email.required' => transWord('email is required'),
    //         'password.required' => transWord('password number is required'),
    //     ];

    //     $validateData = Validator::make($request->all(), [
    //         "name" => "required",
    //         "mobile_number" => 'required|unique:users,id,'.$user->id,
    //         'email' => 'required|email|unique:users,id,'.$user->id,
    //         "password" => "required",
    //     ],$customMessages);

    //     if($validateData->fails()){
    //         $errors = $validateData->errors();
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $errors,
    //             'results' => null
    //         ],401);
    //     }

    //     $user_data->update([
    //         'name' => $request->name,
    //         'mobile_number' => $request->mobile_number,
    //         'type' => 'client',
    //         'email' => $request->email,
    //         'password' => \Hash::make($request->password),
    //     ]);

        
    //     $data = new UserResource($user_data);
    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("User Profile is Updated Successfully !"),
    //         'result' => $data,
    //     ],200);
    // }

    // public function updateProfile(Request $request)
    // {
    //     $user = auth()->user();
    //     $user_data = User::findOrFail($user->id);

    //     $customMessages = [
    //         'name.required' => transWord('name is required'),
    //         'mobile_number.required' => transWord('mobile is required'),
    //         'email.required' => transWord('email is required'),
    //         'password.required' => transWord('password number is required'),
    //         'national_id_front.image' => transWord('Front side must be an image'),
    //         'national_id_front.mimes' => transWord('Front side must be a jpg, jpeg, or png file'),
    //         'national_id_front.max' => transWord('Front side image must be less than 5MB'),
    //         'national_id_back.image' => transWord('Back side must be an image'),
    //         'national_id_back.mimes' => transWord('Back side must be a jpg, jpeg, or png file'),
    //         'national_id_back.max' => transWord('Back side image must be less than 5MB'),
    //     ];

    //     $validateData = Validator::make($request->all(), [
    //         "name" => "nullable|string|max:255",
    //         "mobile_number" => 'nullable|unique:users,mobile_number,' . $user->id,
    //         "email" => 'nullable|email|unique:users,email,' . $user->id,
    //         "password" => "nullable|string|min:6",
    //         "national_id_front" => "nullable|image|mimes:jpg,jpeg,png|max:5120",
    //         "national_id_back" => "nullable|image|mimes:jpg,jpeg,png|max:5120",
    //     ], $customMessages);

    //     if ($validateData->fails()) {
    //         $errors = $validateData->errors();
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $errors,
    //             'results' => null
    //         ], 401);
    //     }

    //     // جمع كل البيانات في مصفوفة واحدة
    //     $dataToUpdate = [
    //         'name' => $request->name,
    //         'mobile_number' => $request->mobile_number,
    //         'type' => 'client',
    //         'email' => $request->email,
    //         'password' => \Hash::make($request->password),
    //     ];

    //     // رفع صورة البطاقة - الوجه
    //     if ($request->hasFile('national_id_front')) {
    //         $frontPath = $request->file('national_id_front')->store('uploads/national_ids', 'public');
    //         $dataToUpdate['national_id_front'] = $frontPath;
    //         $dataToUpdate['national_id_front_approved'] = 0;
    //     }

    //     // رفع صورة البطاقة - الخلف
    //     if ($request->hasFile('national_id_back')) {
    //         $backPath = $request->file('national_id_back')->store('uploads/national_ids', 'public');
    //         $dataToUpdate['national_id_back'] = $backPath;
    //         $dataToUpdate['national_id_back_approved'] = 0;
    //     }


    //     // تحديث البيانات دفعة واحدة
    //     $user_data->update($dataToUpdate);

    //     $data = new UserResource($user_data->fresh());
    //     return response()->json([
    //         'success' => true,
    //         'message' => transWord("User Profile is Updated Successfully !"),
    //         'result' => $data,
    //     ], 200);
    // }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $user_data = User::findOrFail($user->id);

        $customMessages = [
            'mobile_number.unique' => transWord('mobile is already taken'),
            'email.unique' => transWord('email is already taken'),
            'national_id_front.image' => transWord('Front side must be an image'),
            'national_id_front.mimes' => transWord('Front side must be a jpg, jpeg, or png file'),
            'national_id_front.max' => transWord('Front side image must be less than 5MB'),
            'national_id_back.image' => transWord('Back side must be an image'),
            'national_id_back.mimes' => transWord('Back side must be a jpg, jpeg, or png file'),
            'national_id_back.max' => transWord('Back side image must be less than 5MB'),
        ];

        $validateData = Validator::make($request->all(), [
            "name" => "nullable|string|max:255",
            "mobile_number" => 'nullable|unique:users,mobile_number,' . $user->id,
            "email" => 'nullable|email|unique:users,email,' . $user->id,
            "password" => "nullable|string|min:6",
            "national_id_front" => "nullable|image|mimes:jpg,jpeg,png|max:5120",
            "national_id_back" => "nullable|image|mimes:jpg,jpeg,png|max:5120",
        ], $customMessages);

        if ($validateData->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validateData->errors(),
                'results' => null
            ], 401);
        }

        $dataToUpdate = [];

        if ($request->filled('name')) {
            $dataToUpdate['name'] = $request->name;
        }

        if ($request->filled('mobile_number')) {
            $dataToUpdate['mobile_number'] = $request->mobile_number;
        }

        if ($request->filled('email')) {
            $dataToUpdate['email'] = $request->email;
        }

        if ($request->filled('password')) {
            $dataToUpdate['password'] = \Hash::make($request->password);
        }

        $dataToUpdate['type'] = 'client';

        if ($request->hasFile('national_id_front')) {
            $frontPath = $request->file('national_id_front')->store('uploads/national_ids', 'public');
            $dataToUpdate['national_id_front'] = $frontPath;
            $dataToUpdate['national_id_front_approved'] = 0;
        }

        if ($request->hasFile('national_id_back')) {
            $backPath = $request->file('national_id_back')->store('uploads/national_ids', 'public');
            $dataToUpdate['national_id_back'] = $backPath;
            $dataToUpdate['national_id_back_approved'] = 0;
        }

        if (!empty($dataToUpdate)) {
            $user_data->update($dataToUpdate);
        }

        $data = new UserResource($user_data->fresh());
        return response()->json([
            'success' => true,
            'message' => transWord("User Profile is Updated Successfully !"),
            'result' => $data,
        ], 200);
    }

    
    public function createNewAccount(Request $request){
        $customMessages = [
            'name.required' => transWord('name is required'),
            'mobile_number.required' => transWord('mobile is required'),
            'email.required' => transWord('email is required'),
            'password.required' => transWord('password number is required'),
        ];

        $validateData = Validator::make($request->all(), [
            "name" => "required",
            "mobile_number" => "required",
            "email" => "required|unique:users,email",
            "password" => "required",
        ],$customMessages);

        if($validateData->fails()){
            $errors = $validateData->errors();
            return response()->json([
                'success' => false,
                'errors' => $errors,
                'results' => null
            ],401);
        }

        $client = User::create([
            'name' => $request->name,
            'mobile_number' => $request->mobile_number,
            'type' => 'client',
            'email' => $request->email,
            'password' => \Hash::make($request->password),
            'type' => 'client',
            'publish' => 1, 
            'is_verified' => 1 // default:0 => change after check otp code
        ]);

        $checkWallet = Wallet::where('user_id',$client->id)->first();
        $demo_wallet_amount = get_setting('initial_demo_balance');
        if($checkWallet == null){
            $wallet = Wallet::create([
                'user_id' => $client->id,
                'amount' => 0,
                'amount_demo' => $demo_wallet_amount,
            ]);
        }
        
        $data = new UserResource($client);
        return response()->json([
            'success' => true,
            'message' => transWord("Register new user successfully!"),
            'token' => $client->createToken("API TOKEN")->plainTextToken,
            'result' => $data,
        ],200);
    }

    public function changeMode(Request $request)
    {
        $customMessages = [
            'mode.required' => transWord('mode is required'),
            'mode.in' => transWord('mode must be either live or demo'),
        ];

        $validateData = Validator::make($request->all(), [
            "mode" => "required|in:live,demo",
        ], $customMessages);

        if ($validateData->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validateData->errors(),
                'results' => null
            ], 422);
        }

        $user = auth()->user();
        $user->update([
            'mode' => $request->mode
        ]);

        return response()->json([
            'success' => true,
            'message' => transWord("Mode changed successfully"),
            'result'  => new UserResource($user->fresh()),
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $customMessages = [
            'email.required' => transWord('email is required'),
            'email.email'    => transWord('email is invalid'),
        ];

        $data = Validator::make($request->all(), [
            'email' => 'required|email',
        ], $customMessages)->validate();

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            // لتجنُّب كشف وجود الإيميل
            return response()->json([
                'success' => true,
                'message' => transWord('If the email exists, an OTP has been sent'),
                'result'  => null,
            ], 200);
        }

        // Rate-limit بسيط: بحد أقصى 5 أكواد خلال الساعة
        $countLastHour = Otp::where('email', $user->email)
            ->where('type', 'password_reset')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($countLastHour >= 5) {
            return response()->json([
                'success' => false,
                'message' => transWord('Too many requests, please try again later'),
                'result'  => null,
            ], 429);
        }

        $code = random_int(100000, 999999);

        // نلغي أي أكواد قديمة غير مستخدمة لهذا الإيميل
        Otp::where('email', $user->email)
            ->where('type', 'password_reset')
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = Otp::create([
            'user_id'   => $user->id,
            'email'     => $user->email,
            'code'      => (string)$code,
            'type'      => 'password_reset',
            'expire_at' => now()->addMinutes(10),
            'attempts'  => 0,
            'meta'      => null,
        ]);

        // إرسال الإيميل (تقدر تبدّلها برسالة SMS لو عندك Gateway)
        // try {
            Mail::to($user->email)->send(new \App\Mail\PasswordResetOtpMail($user->name, $code));
        // } catch (\Throwable $e) {
        //     // في حالة فشل الإرسال، بس نكتب Log
        //     \Log::warning('Failed to send reset OTP email', ['email' => $user->email, 'err' => $e->getMessage()]);
        // }

        // في بيئة التطوير فقط ممكن ترجّع الكود (اختياري)
        $devPayload = app()->environment('local') ? ['debug_otp' => $code] : [];

        return response()->json(array_merge([
            'success' => true,
            'message' => transWord('OTP sent if the email exists'),
            'result'  => null,
        ], $devPayload), 200);
    }

    /**
     * 2) إعادة التعيين باستخدام (email + code + password)
     */
    public function resetPasswordWithOtp(Request $request)
    {
        $customMessages = [
            'email.required'                 => transWord('email is required'),
            'email.email'                    => transWord('email is invalid'),
            'code.required'                  => transWord('OTP code is required'),
            'password.required'              => transWord('password number is required'),
            'password.confirmed'             => transWord('password confirmation does not match'),
            'password.min'                   => transWord('password must be at least 6 characters'),
        ];

        $data = Validator::make($request->all(), [
            'email'                 => 'required|email',
            'code'                  => 'required|string',
            'password'              => 'required|string|min:6|confirmed',
            // لازم تبعت password_confirmation
        ], $customMessages)->validate();

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            // الرد موحّد عشان ما نكشف وجود الإيميل
            return response()->json([
                'success' => false,
                'message' => transWord('Invalid OTP or expired'),
                'result'  => null,
            ], 400);
        }

        $otp = Otp::where('email', $user->email)
            ->where('type', 'password_reset')
            ->where('code', $data['code'])
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => transWord('Invalid OTP or expired'),
                'result'  => null,
            ], 400);
        }

        // زيادة العدّاد ومنع brute-force
        $otp->increment('attempts');
        if ($otp->attempts > 5) {
            return response()->json([
                'success' => false,
                'message' => transWord('Too many attempts, request a new OTP'),
                'result'  => null,
            ], 429);
        }

        if ($otp->used_at || now()->greaterThan($otp->expire_at)) {
            return response()->json([
                'success' => false,
                'message' => transWord('Invalid OTP or expired'),
                'result'  => null,
            ], 400);
        }

        // كل شيء تمام: حدّث الباسورد وعلِّم الـ OTP كمستخدم
        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        $otp->update(['used_at' => now()]);

        // أمان: احذف/أبطل أي توكنات Sanctum نشطة (اختياري لكن مفضل)
        try {
            $user->tokens()->delete();
        } catch (\Throwable $e) {
            \Log::warning('Sanctum tokens delete failed after password reset', ['user_id' => $user->id]);
        }

        return response()->json([
            'success' => true,
            'message' => transWord('Password has been reset successfully'),
            'result'  => null,
        ], 200);
    }

    /**
     * (اختياري) التحقق من الـ OTP فقط بدون إعادة تعيين
     */
    public function verifyResetOtp(Request $request)
    {
        $data = Validator::make($request->all(), [
            'email' => 'required|email',
            'code'  => 'required|string',
        ])->validate();

        $otp = Otp::where('email', $data['email'])
            ->where('type', 'password_reset')
            ->where('code', $data['code'])
            ->latest()
            ->first();

        if (!$otp || $otp->used_at || now()->greaterThan($otp->expire_at)) {
            return response()->json([
                'success' => false,
                'message' => transWord('Invalid OTP or expired'),
                'result'  => null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => transWord('OTP is valid'),
            'result'  => null,
        ], 200);
    }

}
