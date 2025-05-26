<?php

namespace App\Repositories\Auth;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;

interface AuthRepositoryInterface
{
    public function signUpUser(Request $request);
    public function signInUser(Request $request);
    public function signOut(Request $request);
    public function restorePassword(Request $request);
    public function getRoles();
    public function assignRole(Request $request);
    public function unAssignRole(Request $request);
    public function getAllUsers();
    public function getAllUsersWithoutRole();
    public function deleteUser(Request $request);
    public function accountActivationCard(Request $request );
    public function searchUser(Request $request);
    public function updateProfile(UpdateProfileRequest $request);

    public function assignPermissionsToUser(Request $request);

    public function getUserPermissions($userId);
    public function transferUser(Request $request);
}
