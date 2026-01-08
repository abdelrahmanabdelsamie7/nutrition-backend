<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\{LoginRequest, RegisterRequest};
use App\Http\Resources\UserResource;
use App\Interfaces\Repositories\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseController
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->userRepository->create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),

                'age' => $request->age,
                'gender' => $request->gender,
                'height' => $request->height,
                'weight' => $request->weight,
                'activity_level' => $request->activity_level,
                'goal' => $request->goal,
                'diet_type' => $request->diet_type,
                'budget_level' => $request->budget_level ?? 'medium',
            ]);

            if ($user->hasCompleteProfile()) {
                $user->calculateDailyTargets();
            }

            $token = JWTAuth::fromUser($user);

            return $this->createdResponse([
                'user' => new UserResource($user),
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60 * 60 * 60
            ], 'User registered successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return $this->unauthorizedResponse('Invalid credentials');
        }
        $user = Auth::guard('api')->user();
        return $this->successResponse([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60 * 60 * 60
        ], 'Login successful');
    }

    public function me(): JsonResponse
    {
        $user = Auth::guard('api')->user();
        return $this->successResponse(new UserResource($user), 'User retrieved');
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();
        return $this->successResponse(null, 'Successfully logged out');
    }

    public function refresh(): JsonResponse
    {
        return $this->successResponse([
            'access_token' => Auth::guard('api')->refresh(),
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60 * 60 * 60
        ], 'Token refreshed');
    }
}
