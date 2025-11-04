<?php

namespace App\Services\User;

use App\Enums\PaginationType;
use App\Enums\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Traits\Services\CanBuildPagination;
use App\Traits\Services\CanResolveModelFromId;
use Carbon\Carbon;
use Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

class UserManager implements UserAccountManager, UserCredentialManager
{
    use CanBuildPagination;
    use CanResolveModelFromId;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    /** {@inheritDoc} */
    public function all(): LengthAwarePaginator
    {
        /** @var Builder $users */
        $query = User::filtered();

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * {@inheritDoc}
     * test
     *
     * @throws Throwable
     */
    public function create(array $userInfo): User
    {
        // Check for existing email before insertion
        if (User::where('email', $userInfo['email'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        // Check if the individual_basic_detail_id is already linked
        if (! empty($userInfo['individual_basic_detail_id'])) {
            $alreadyLinked = UserProfile::where('individual_basic_detail_id', $userInfo['individual_basic_detail_id'])->exists();

            if ($alreadyLinked) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'individual_basic_detail_id' => ['This employee is already linked to another user.'],
                ]);
            }
        }

        return DB::transaction(function () use ($userInfo) {
            $userCredentials = [
                'email' => $userInfo['email'],
                'password' => $userInfo['password'],
            ];

            if (isset($userInfo['active'])) {
                $userCredentials['active'] = $userInfo['active'];
            }

            if (isset($userInfo['email_verified'])) {
                $userCredentials['email_verified_at'] = $userInfo['email_verified'] ? Carbon::now() : null;
            }

            $user = User::create($userCredentials);

            $userRoles = empty($userInfo['roles']) ? [Role::STANDARD_USER->value] : $userInfo['roles'];

            $user->syncRoles($userRoles);
            $user = $user->fresh();

            // Set the user profile fields
            $exemptedAttributes = [
                'email',
                'password',
                'active',
                'email_verified_at',
                'home_address',
                'barangay_id',
                'city_id',
                'province_id',
                'region_id',
                'postal_code',
            ];
            $profileFields = Arr::except($userInfo, $exemptedAttributes);
            $profileFields['individual_basic_detail_id'] = $userInfo['individual_basic_detail_id'] ?? null;

            $user->userProfile()->create($profileFields);
            // Set the Address fields
            $user->userProfile->address()->create(Arr::only(
                $userInfo,
                ['home_address', 'barangay_id', 'city_id', 'province_id', 'region_id', 'postal_code']
            ));

            return $user->load('userProfile');
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /** {@inheritDoc} */
    public function read(int|string $id, array $relationships = ['userProfile',
        'userProfile.individualBasicDetail',
        'userProfile.individualBasicDetail.individualContactInfo',
        'userProfile.individualBasicDetail.individualAddress',
        'userProfile.individualBasicDetail.individualFamily',
        'userProfile.individualBasicDetail.individualEducationalBackground',
        'userProfile.individualBasicDetail.individualEligibility',
        'userProfile.individualBasicDetail.individualWorkExperience',
        'userProfile.individualBasicDetail.individualVoluntaryWork',
        'userProfile.individualBasicDetail.individualLnd',
        'userProfile.individualBasicDetail.individualSkillsHobby',
        'userProfile.individualBasicDetail.individualRecognition',
        'userProfile.individualBasicDetail.individualMembership',
        'userProfile.individualBasicDetail.individualQuestion',
        'userProfile.individualBasicDetail.individualReference',
        'userProfile.individualBasicDetail.individualGovernmentId',
    ]): User
    {
        /** @var User $user */
        $user = User::with($relationships)->findOrFail($id);

        return $user;
    }

    /**
     * {@inheritDoc}
     *
     * @throws Throwable
     */
    public function update(User|int|string $modelOrId, array $newUserInfo): User
    {

        return DB::transaction(function () use ($modelOrId, $newUserInfo) {
            /** @var User $user */
            $user = $this->retrieveModel($modelOrId, User::query());

            unset($newUserInfo['password_confirmation']);

            if (isset($newUserInfo['email_verified'])) {
                $newUserInfo['email_verified_at'] = $newUserInfo['email_verified']
                    ? Carbon::now('utc')
                    : null;
                unset($newUserInfo['email_verified']);
            }

            // Update the basic user fields
            $user->update(
                Arr::only($newUserInfo, ['email', 'password', 'active', 'email_verified_at'])
            );

            // Check for duplicate individual_basic_detail_id if it's being changed
            if (! empty($newUserInfo['individual_basic_detail_id'])) {
                $existingUserId = UserProfile::where('individual_basic_detail_id', $newUserInfo['individual_basic_detail_id'])
                    ->where('user_id', '!=', $user->id)
                    ->value('user_id');

                if ($existingUserId) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'individual_basic_detail_id' => ['This employee is already linked to another user.'],
                    ]);
                }
            }

            $profileFields = Arr::only($newUserInfo, [
                'first_name',
                'last_name',
                'middle_name',
                'ext_name',
                'sex',
                'birthday',
                'mobile_number',
                'profile_picture_path',
                'telephone_number',
                'individual_basic_detail_id',
            ]);

            $addressFields = Arr::only($newUserInfo, [
                'home_address',
                'barangay_id',
                'city_id',
                'province_id',
                'region_id',
                'postal_code',
            ]);

            $userProfile = $user->userProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileFields
            );

            $userProfile->address()->updateOrCreate(
                ['user_profile_id' => $userProfile->id],
                $addressFields
            );

            if (isset($newUserInfo['roles'])) {
                $user->syncRoles($newUserInfo['roles']);
            }

            return $user->fresh('userProfile');
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /**
     * {@inheritDoc}
     *
     * @throws Throwable
     */
    public function search(
        string $term, ?PaginationType $pagination = null): LengthAwarePaginator
    {
        $userQuery = User::on('one_account')->where('email', 'like', "$term%")->orWhere('username', 'like', "%$term%");
        $userIdsFromEmail = $userQuery->pluck('id')->toArray();
        $profileMatches = UserProfile::on('mysql')
            ->where(function ($query) use ($term) {
                $query->where('first_name', 'like', "%$term%")
                    ->orWhere('last_name', 'like', "%$term%")
                    ->orWhere('middle_name', 'like', "%$term%")
                    ->orWhere('ext_name', 'like', "%$term%");
            })
            ->pluck('user_id')
            ->toArray();
        $matchingUserIds = array_unique(array_merge($userIdsFromEmail, $profileMatches));

        $finalQuery = User::on('one_account')
            ->with('userProfile')
            ->whereIn('id', $matchingUserIds);

        return $finalQuery->paginate($pagination?->perPage ?? 15);
    }

    /** {@inheritDoc} */
    public function destroy(User|int|string $modelOrId): User
    {
        /** @var User $user */
        $user = $this->retrieveModel($modelOrId, User::query());

        $user->delete();

        return $user;
    }

    public function updatePassword(User|int|string $modelOrId, string $newPassword, string $oldPassword): ?User
    {
        /** @var User $user */
        $user = $this->retrieveModel($modelOrId, User::query());

        if (! Hash::check($oldPassword, $user->password)) {
            return null;
        }

        $user->password = $newPassword;
        $user->save();

        return $user;
    }

    /** {@inheritDoc} */
    public function getUserViaEmailAndPassword(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();
        $hasCorrectCreds = $user && \Illuminate\Support\Facades\Hash::check($password, $user->password);
        if (! $hasCorrectCreds) {
            return null;
        }

        return $user;
    }

    /** {@inheritDoc} */
    public function getUserViaUsernameAndPassword(string $username, string $password): ?User
    {
        $user = User::where('username', $username)->first();
        $hasCorrectCreds = $user && \Illuminate\Support\Facades\Hash::check($password, $user->password);
        if (! $hasCorrectCreds) {
            return null;
        }

        return $user;
    }

    /** {@inheritDoc} */
    public function getUserViaMobileNumberAndPassword(string $mobileNumber, string $password): ?User
    {
        /**
         * Since we save the mobile (and phone) numbers in international format,
         * we will mutate it if clients send in national format
         * ex: 09064647295 -> +639064647295
         *
         * @Note
         * We ignore the country format if we're running tests, since seeding can produce some malformed numbers
         */
        $mobileNumber = ! app()->runningUnitTests()
            ? (new PhoneNumber($mobileNumber, 'PH'))->formatE164()
            : (new PhoneNumber($mobileNumber))->formatE164();

        $user = User::query()
            ->join('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->where('mobile_number', $mobileNumber)
            ->with('userProfile')
            ->first();

        $hasCorrectCreds = $user && Hash::check($password, $user->password);
        if (! $hasCorrectCreds) {
            return null;
        }

        return $user;
    }
}
