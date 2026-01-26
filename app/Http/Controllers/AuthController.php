<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\DeactivateAccountRequest;
use App\Http\Requests\RestorePassword;
use App\Http\Requests\SignInRequest;
use App\Http\Requests\SignUp;
use App\Http\Requests\UnAssignRoleRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Repositories\Auth\AuthRepositoryInterface;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private AuthRepositoryInterface $authRepository;

    public function __construct(AuthRepositoryInterface $authRepository)
    {
        $this->authRepository = $authRepository;

        $this->middleware(['auth:api', 'permission:manage_users'])->only('assignPermissionsToUser');
        $this->middleware(['auth:api', 'permission:users_roles'])->only([
            'assignRole',
            'accountActivationCard',
            'unAssignRole',
        ]);
    }

    public function signup(SignUp $request)
    {
        return $this->authRepository->signUpUser($request);
    }

    public function getAllUserReferred(Request $request, $userId)
    {
        return $this->authRepository->getAllUserReferred($request, $userId);
    }

    public function signOut(Request $request)
    {
        return $this->authRepository->signOut($request);
    }

    public function signin(SignInRequest $request)
    {
        return $this->authRepository->signInUser($request);
    }

    public function restorePassword(RestorePassword $request)
    {
        return $this->authRepository->restorePassword($request);
    }

    public function getRoles()
    {
        return $this->authRepository->getRoles();
    }

    public function getAllUsers()
    {
        return $this->authRepository->getAllUsers();
    }

    public function getAllUsersWithoutRole()
    {
        return $this->authRepository->getAllUsersWithoutRole();
    }

    public function assignRole(AssignRoleRequest $request)
    {
        return $this->authRepository->assignRole($request);
    }

    public function unAssignRole(UnAssignRoleRequest $request)
    {
        return $this->authRepository->unAssignRole($request);
    }

    public function deleteUser(Request $request)
    {
        return $this->authRepository->deleteUser($request);
    }

    public function AccountActivationCard(DeactivateAccountRequest $request)
    {
        return $this->authRepository->AccountActivationCard($request);
    }

    public function getUserProfileById(Request $request, $userId)
    {
        return $this->authRepository->getUserProfileById($request, $userId);
    }

    public function searchUser(Request $request)
    {
        return $this->authRepository->searchUser($request);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        return $this->authRepository->updateProfile($request);
    }

    // ✅ Middleware is now applied only in the constructor for this method
    public function assignPermissionsToUser(Request $request)
    {
        return $this->authRepository->assignPermissionsToUser($request);
    }

    public function getUserPermissions($userId)
    {
        return $this->authRepository->getUserPermissions($userId);
    }

    public function transferUser(Request $request)
    {
        return $this->authRepository->transferUser($request);
    }

    public function getCountiesWithSubCounties(Request $request)
    {
        return $this->authRepository->getCountiesWithSubCounties($request);
    }

    public function activateAllInactiveAcounts(Request $request)
    {
        return $this->authRepository->activateAllInactiveAcounts($request);
    }
}
