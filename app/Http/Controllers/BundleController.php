<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserBundle;
use App\Models\PlatformBundle;

class BundleController extends Controller
{
    public function createBundle(Request $request){
        if(auth()->check())
        {
            $rules = [
                'bundle_name' => 'required|string'
            ];

            $messages = [
                'bundle_name.string' => 'Bundle name must be in a string format.',
                'bundle_name.required' => 'Bundle name is required.'
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

                    $userBundle = new UserBundle();
                    $userBundle->uuid = Str::uuid();
                    $userBundle->user_id = auth()->user()->id;
                    $userBundle->bundle_name = $data['bundle_name'];

                    $executeQuery = $userBundle->save();

                    if ($executeQuery){
                        return response()->json(['message' => 'Bundle created successfully.'], 200);
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

    public function addBundle(Request $request){
        if(auth()->check())
        {
            $rules = [
                'is_user_added_platform' => 'required|boolean',
                'bundle_id' => 'required|exists:user_bundles,id'
            ];

            if($request->json()->all()['is_user_added_platform']){
                $rules['platform_id'] = 'required|exists:user_added_platforms,id';
            }
            else{
                $rules['platform_id'] = 'required|exists:media_platforms,id';
            }

            $messages = [
                'platform_id.exists' => 'Platform does not exist.',
                'platform_id.required' => 'Platform ID is required.',
                'bundle_id.exists' => 'Bundle does not exist.',
                'bundle_id.required' => 'Bundle ID is required.',
                'is_user_added_platform.required' => 'User Added Platform flag is required.',
                'is_user_added_platform.boolean' => 'User Added Platform flag must be in the boolean format.'
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

                    $platformBundle = new PlatformBundle();
                    $platformBundle->uuid = Str::uuid();
                    $platformBundle->user_id = auth()->user()->id;
                    $platformBundle->platform_id = $data['platform_id'];
                    $platformBundle->is_user_added_platform = $data['is_user_added_platform'];

                    $executeQuery = $platformBundle->save();

                    if ($executeQuery){
                        return response()->json(['message' => 'Platform added successfully in the bundle'], 200);
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
}
