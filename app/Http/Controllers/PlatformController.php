<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MediaPlatform;
use App\Models\UserPlatform;
use App\Models\MediaType;
use App\Models\UserAddedPlatform;
use Validator;
use Log;
use Str;

class PlatformController extends Controller
{
    public function addPlatforms(Request $request){
        if(auth()->check())
        {
            $rules = [
                'platform_id'=> 'required|numeric|exists:media_platforms,id',
                'is_active' => 'required|boolean'
            ];

            if($request->json()->all()['is_url']){
                // To push into associative array
                $rules['platform_username'] = ['regex: /^(http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$/', 'required'];
            }
            else{
                // To push into associative array
                $rules['platform_username'] = ['regex: /^[a-zA-Z0-9_.]{3,}[a-zA-Z]+[0-9]*$/','required'];
            }

            $messages = [
                'platform_id.exists' => 'Platform does not exist.',
                'platform_id.required' => 'Platform ID is required.',
                'platform_id.numeric' => 'Invalid Platform ID.',
                'platform_username.regex' => 'Username or URL is not valid.',
                'platform_username.required' => 'Username or URL is required.',
                'is_active.required' => 'Is Active flag is required.',
                'is_active.boolean' => 'Is Active flag is not in the right format.'
            ];

            $validator = Validator::make($request->json()->all(), $rules, $messages);

            if ($validator->fails())
            {
                Log::info($request->json()->all());
                return response()->json($validator->errors(), 400);
            }
            else{
                try{
                    $data = $request->json()->all();

                    $userPlatform = new UserPlatform();
                    $userPlatform->uuid = Str::uuid();
                    $userPlatform->user_id = auth()->user()->id;
                    $userPlatform->platform_username = $data['platform_username'];
                    $userPlatform->media_platform_id = $data['platform_id'];
                    $userPlatform->is_active = $data['is_active'];

                    $executeQuery = $userPlatform->save();

                    if ($executeQuery){
                        return response()->json(['message' => 'Platform added successfully'], 200);
                    }
                    else{
                        return response()->json(['message' => 'There was an error while adding this Platform'], 200);
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
            }
        }
        else
        {
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function editPlatform(Request $request){
        if(auth()->check())
        {
            $rules = [
                'platform_id'=> 'required|numeric|exists:media_platforms,id',
                'is_active' => 'required|boolean'
            ];

            if($request->json()->all()['is_url']){
                // To push into associative array
                $rules['platform_username'] = ['regex: /^(http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$/', 'required'];
            }
            else{
                // To push into associative array
                $rules['platform_username'] = ['regex: /^[a-zA-Z0-9_]{2,}[a-zA-Z]+[0-9]*$/','required'];
            }

            $messages = [
                'platform_id.exists' => 'Platform does not exist.',
                'platform_id.required' => 'Platform ID is required.',
                'platform_id.numeric' => 'Invalid Platform ID.',
                'platform_username.regex' => 'Username is not valid.',
                'platform_username.required' => 'Username is required.',
                'is_active.required' => 'Is Active flag is required.',
                'is_active.boolean' => 'Is Active flag is not in the right format.'
            ];

            $validator = Validator::make($request->json()->all(), $rules, $messages);

            if ($validator->fails())
            {
                Log::info($request->json()->all());
                return response()->json($validator->errors(), 400);
            }
            else{
                try{
                    $data = $request->json()->all();

                    $userPlatform = UserPlatform::where('user_id', auth()->user()->id)->where('media_platform_id', $data['platform_id'])->first();
                    $userPlatform->platform_username = $data['platform_username'];
                    $userPlatform->is_active = $data['is_active'];

                    $executeQuery = $userPlatform->update();

                    if ($executeQuery){
                        return response()->json(['message' => 'Platform updated successfully'], 200);
                    }
                    else{
                        return response()->json(['message' => 'There was an error while adding this Platform'], 200);
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
            }
        }
        else
        {
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function removePlatform(Request $request){
        if(auth()->check())
        {
            $rules = [
                'platform_uuid'=> 'required|exists:user_platforms,uuid',
            ];

            $messages = [
                'platform_uuid.exists' => 'Platform does not exist.',
                'platform_uuid.required' => 'Platform ID is required.',
            ];

            $validator = Validator::make($request->json()->all(), $rules, $messages);

            if ($validator->fails())
            {
                Log::info($request->json()->all());
                return response()->json($validator->errors(), 400);
            }
            else{
                try{
                    $data = $request->json()->all();

                    $userPlatform = UserPlatform::where('uuid', $data['platform_uuid'])->first();
                    $executeQuery = $userPlatform->delete();

                    if ($executeQuery){
                        return response()->json(['message' => 'Platform deleted successfully'], 200);
                    }
                    else{
                        return response()->json(['message' => 'There was an error while adding this Platform'], 200);
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
            }
        }
        else
        {
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function removeUserAddedPlatform(Request $request){
        if(auth()->check())
        {
            $rules = [
                'platform_uuid'=> 'required|exists:user_added_platforms,uuid',
            ];

            $messages = [
                'platform_uuid.exists' => 'Platform does not exist.',
                'platform_uuid.required' => 'Platform ID is required.',
            ];

            $validator = Validator::make($request->json()->all(), $rules, $messages);

            if ($validator->fails())
            {
                Log::info($request->json()->all());
                return response()->json($validator->errors(), 400);
            }
            else{
                try{
                    $data = $request->json()->all();

                    $userPlatform = UserAddedPlatform::where('uuid', $data['platform_uuid'])->first();
                    $executeQuery = $userPlatform->delete();

                    if ($executeQuery){
                        return response()->json(['message' => 'Platform deleted successfully'], 200);
                    }
                    else{
                        return response()->json(['message' => 'There was an error while deleting this Platform'], 200);
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
            }
        }
        else
        {
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function addCustomisedPlatforms(Request $request){
        if(auth()->check())
        {
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
                'is_active' => 'required|boolean',
                'platform_type_id'=> 'required|numeric|exists:media_types,id',
                'platform_name'=> 'required',
                'redirection_url'=> 'required',
                'is_file_to_upload' => 'required|boolean',
                'logo_url' => 'nullable|image_type|image_size'
            ];

            $messages = [
                'is_active.required' => 'Is Active flag is required.',
                'is_active.boolean' => 'Invalid type of is_active flag',
                'platform_type_id.exists' => 'Platform does not exist.',
                'platform_type_id.required' => 'Platform ID is required.',
                'platform_type_id.numeric' => 'Invalid Platform ID.',
                'platform_name.required'=> 'Platform name is required.',
                'redirection_url.required'=> 'Redirection url is required.',
                'is_file_to_upload.required'=> 'is_file_to_upload is required.',
                'is_file_to_upload.boolean'=> 'is_file_to_upload must be boolean.',
                'logo_url.mimes' => 'Please select a logo with jpg, jpeg, bmp or png extension.'
            ];

            $validator = Validator::make($request->json()->all(), $rules, $messages);

            if ($validator->fails())
            {
                Log::info($request->json()->all());
                return response()->json($validator->errors(), 400);
            }
            else{
                try{
                    $data = $request->json()->all();

                    $userAddedPlatform = new UserAddedPlatform();
                    $userAddedPlatform->uuid = Str::uuid();
                    $userAddedPlatform->user_id = auth()->user()->id;
                    $userAddedPlatform->platform_name = $data['platform_name'];

                    if(! $data['is_file_to_upload']){
                        $userAddedPlatform->redirection_url = $data['redirection_url'];
                    }
                    else{
                        $file = base64_decode(explode(';base64,', $data['redirection_url'])[1]);

                        $f = finfo_open();

                        $fileMimeType = finfo_buffer($f, $file, FILEINFO_MIME_TYPE);

                        $file_extension = explode('/', $fileMimeType)[1];

                        $file_path = '/files/platform_added_files/'.time().Str::random(10).rand(9999, 99999).'.'.$file_extension;

                        $fileUploaded = file_put_contents(public_path().$file_path, $file);

                        if (! $fileUploaded)
                        {
                            DB::rollback();

                            Log::info('There was an error while uploading file');

                            return response()->json(['message' => 'Internal Server Error. File could not be saved. Please try again later.'], 500);
                        }

                        $userAddedPlatform->redirection_url = env('WEB_URL').$file_path;
                    }

                    $userAddedPlatform->media_type_id = $data['platform_type_id'];

                    if(isset($data['logo_url'])){
                        $logo_image = base64_decode(explode(';base64,', $data['logo_url'])[1]);

                        $f = finfo_open();

                        $imageMimeType = finfo_buffer($f, $logo_image, FILEINFO_MIME_TYPE);

                        $logo_image_extension = explode('/', $imageMimeType)[1];

                        $image_path = '/images/user_added_platform_logo/'.time().Str::random(10).rand(9999, 99999).'.'.$logo_image_extension;

                        $fileUploaded = file_put_contents(public_path().$image_path, $logo_image);

                        if (! $fileUploaded)
                        {
                            DB::rollback();

                            Log::info('There was an error while uploading Platform logo');

                            return response()->json(['message' => 'Internal Server Error. Platform logo could not be saved. Please try again later.'], 500);
                        }

                        $userAddedPlatform->logo_url = $image_path;
                    }

                    $userAddedPlatform->is_active = $data['is_active'];

                    $executeQuery = $userAddedPlatform->save();

                    if ($executeQuery){
                        return response()->json(['message' => 'Platform added successfully'], 200);
                    }
                    else{
                        return response()->json(['message' => 'There was an error while adding this Platform'], 200);
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
            }
        }
        else
        {
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function editCustomisedPlatform(Request $request){
        if(auth()->check())
        {
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
                'is_active' => 'required|boolean',
                'platform_id' => 'required|numeric|exists:user_added_platforms,id',
                'platform_type_id'=> 'required|numeric|exists:media_types,id',
                'platform_name'=> 'required',
                // 'redirection_url'=> 'required',
                // 'is_file_to_upload' => 'required|boolean',
                'logo_url' => 'nullable|image_type|image_size'
            ];

            $messages = [
                'is_active.required' => 'Is Active flag is required.',
                'is_active.boolean' => 'Invalid type of is_active flag',
                'platform_id.exists' => 'Platform does not exist.',
                'platform_id.required' => 'Platform ID is required.',
                'platform_id.numeric' => 'Invalid Platform ID.',
                'platform_type_id.exists' => 'Platform type does not exist.',
                'platform_type_id.required' => 'Platform type ID is required.',
                'platform_type_id.numeric' => 'Invalid Platform type ID.',
                'platform_name.required'=> 'Platform name is required.',
                // 'redirection_url.required'=> 'Redirection url is required.',
                // 'is_file_to_upload.required'=> 'is_file_to_upload is required.',
                // 'is_file_to_upload.boolean'=> 'is_file_to_upload must be boolean.',
                'logo_url.mimes' => 'Please select a logo with jpg, jpeg, bmp or png extension.'
            ];

            $validator = Validator::make($request->json()->all(), $rules, $messages);

            if ($validator->fails())
            {
                Log::info($request->json()->all());
                return response()->json($validator->errors(), 400);
            }
            else{
                try{
                    $data = $request->json()->all();

                    $userAddedPlatform = UserAddedPlatform::where('id', $data['platform_id'])->where('user_id', auth()->user()->id)->first();
                    $userAddedPlatform->platform_name = $data['platform_name'];

                    // if(! $data['is_file_to_upload']){
                    //     $userAddedPlatform->redirection_url = $data['redirection_url'];
                    // }
                    // else{
                    //     $file = base64_decode(explode(';base64,', $data['redirection_url'])[1]);

                    //     $f = finfo_open();

                    //     $fileMimeType = finfo_buffer($f, $file, FILEINFO_MIME_TYPE);

                    //     $file_extension = explode('/', $fileMimeType)[1];

                    //     $file_path = '/files/platform_added_files/'.time().Str::random(10).rand(9999, 99999).'.'.$file_extension;

                    //     $fileUploaded = file_put_contents(public_path().$file_path, $file);

                    //     if (! $fileUploaded)
                    //     {
                    //         DB::rollback();

                    //         Log::info('There was an error while uploading file');

                    //         return response()->json(['message' => 'Internal Server Error. File could not be saved. Please try again later.'], 500);
                    //     }

                    //     $userAddedPlatform->redirection_url = env('WEB_URL').$file_path;
                    // }

                    $userAddedPlatform->media_type_id = $data['platform_type_id'];

                    if(isset($data['logo_url'])){
                        $logo_image = base64_decode(explode(';base64,', $data['logo_url'])[1]);

                        $f = finfo_open();

                        $imageMimeType = finfo_buffer($f, $logo_image, FILEINFO_MIME_TYPE);

                        $logo_image_extension = explode('/', $imageMimeType)[1];

                        $image_path = '/images/user_added_platform_logo/'.time().Str::random(10).rand(9999, 99999).'.'.$logo_image_extension;

                        $fileUploaded = file_put_contents(public_path().$image_path, $logo_image);

                        if (! $fileUploaded)
                        {
                            DB::rollback();

                            Log::info('There was an error while uploading Platform logo');

                            return response()->json(['message' => 'Internal Server Error. Platform logo could not be saved. Please try again later.'], 500);
                        }

                        $userAddedPlatform->logo_url = $image_path;
                    }

                    $userAddedPlatform->is_active = $data['is_active'];

                    $executeQuery = $userAddedPlatform->update();

                    if ($executeQuery){
                        return response()->json(['message' => 'Platform updated successfully'], 200);
                    }
                    else{
                        return response()->json(['message' => 'There was an error while updating this Platform'], 200);
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
            }
        }
        else
        {
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function getPlatforms(Request $request){
        if(auth()->check())
        {
            $data = MediaPlatform::with(['getUserPlatformRelation' => function($platform){
                $platform->where('user_id', auth()->user()->id)->where('is_active', true);
            }])->get();

            $platforms = array();

            // foreach($data as $key => $item){
            //     $item['logo_url'] = env('WEB_URL').$item['logo_url'];
            //     if(is_null($item->getUserPlatformRelation)){
            //         unset($data[$key]);
            //     }
            // }

            foreach($data as $item){
                $item['logo_url'] = env('WEB_URL').$item['logo_url'];
                if(! is_null($item->getUserPlatformRelation)){
                    array_push($platforms, $item);
                }
            }

            return response()->json(['platforms' => $platforms]);
        }
        else
        {
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function postTapPlatform(Request $request){
        $rules = [
            'is_user_added_platform'=> 'required|boolean',
            'user_id' => 'required|numeric|exists:users,id',
            'platform_id' => $request->is_user_added_platform ? 'required|numeric|exists:user_added_platforms,id' : 'required|numeric|exists:media_platforms,id'
        ];

        $messages = [
            'platform_id.exists' => 'Platform does not exist.',
            'platform_id.required' => 'Platform ID is required.',
            'platform_id.numeric' => 'Invalid Platform ID.',
            'user_id.exists' => 'User ID does not exist.',
            'user_id.required' => 'User ID is required.',
            'user_id.numeric' => 'Invalid User ID.',
            'is_user_added_platform.required' => 'Is User Added flag is required.',
            'is_user_added_platform.boolean' => 'Is User Added flag is not in the right format.'
        ];

        $validator = Validator::make($request->json()->all(), $rules, $messages);

        if ($validator->fails())
        {
            Log::info($request->json()->all());
            return response()->json($validator->errors(), 400);
        }
        else{
            try{
                $data = $request->json()->all();
                if($data['is_user_added_platform']){
                    $platform = UserAddedPlatform::where('id', $data['platform_id'])->where('user_id', $data['user_id'])->first();
                }
                else{
                    $platform = UserPlatform::where('media_platform_id', $data['platform_id'])->where('user_id', $data['user_id'])->first();
                }
                $platform->taps = $platform->taps += 1;

                $executeQuery = $platform->update();

                if ($executeQuery){
                    return response()->json(['message' => 'Tap counter increased successfully'], 200);
                }
                else{
                    return response()->json(['message' => 'There was an error!!'], 200);
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
        }
    }

    public function getUserAddedPlatforms(Request $request){
        if(auth()->check())
        {
            $data = UserAddedPlatform::where('user_id', auth()->user()->id)->get();
            foreach($data as $item){
                if(!is_null($item['logo_url'])){
                    $item['logo_url'] = env('WEB_URL').$item['logo_url'];
                }
                $item['name'] = $item['platform_name'];
                unset($item['platform_name']);
            }

            return response()->json(['user_added_platforms' => $data]);
        }
        else
        {
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function getAllPlatforms(Request $request){
        if(auth()->check()){
            $data = MediaPlatform::with(['getUserPlatformRelation' => function($platform){
                $platform->where('user_id', auth()->user()->id);
            }])->get();
            foreach($data as $item){
                $item['logo_url'] = env('WEB_URL').$item['logo_url'];
                if(! is_null($item->getUserPlatformRelation)){
                    $item['redirection_url'] = $item['base_url'].$item->getUserPlatformRelation->platform_username;
                }
                else{
                    $item['redirection_url'] = $item['base_url'];
                }
            }

            return response()->json(['platforms' => $data]);
        }
        else{
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }

    public function getMediaTypes(Request $request){
        if(auth()->check()){
            $data = MediaType::get();

            return response()->json(['media_types' => $data]);
        }
        else{
            return response()->json(array('success' =>'false', 'message' => 'Unauthorized'), 401);
        }
    }
}
