<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmEmailChange;
use App\Mail\EmailChanged;
use App\Models\User;
use App\Rules\UniqueEmailRule;
use App\Validators\Password;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class UserController extends AbstractController
{
    public function edit(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $error_msg = '';
        $status = session('status');
        $other_msg = is_string($status) ? $status : '';
        $can_change_email = $user->can('changeEmail', $user);
        $can_change_password = $user->can('changePassword', $user);

        if (isset($_POST['updateprofile'])) {
            // The email field is disabled for users who can't change their email address.
            $posted_email = $_POST['email'] ?? '';
            $email = $can_change_email && is_string($posted_email) ? trim($posted_email) : $user->email;
            $email_changed = $email !== $user->email;
            $password = $_POST['password'] ?? '';
            $email_error = $email_changed ? self::emailChangeError($user, $email, is_string($password) ? $password : '') : null;
            if ($email_error !== null) {
                $error_msg = $email_error;
            } else {
                $user->institution = $_POST['institution'] ?? '';
                $user->lastname = $_POST['lname'] ?? '';
                $user->firstname = $_POST['fname'] ?? '';
                if ($user->save()) {
                    $other_msg = 'Your profile has been updated.';
                    // The address only changes once the user clicks the link sent to it.
                    if ($email_changed) {
                        Mail::to($email)->send(new ConfirmEmailChange($user, $email));
                        $other_msg .= " To finish changing your email address, click the link sent to {$email}.";
                    }
                } else {
                    $error_msg = 'Cannot update profile.';
                }
            }
        }

        // Update the password
        if (isset($_POST['updatepassword'])) {
            $oldpasswd = $_POST['oldpasswd'] ?? '';
            $passwd = $_POST['passwd'] ?? '';
            $passwd2 = $_POST['passwd2'] ?? '';

            $password_is_good = true;

            if (!$can_change_password) {
                $password_is_good = false;
                $error_msg = 'Password changes are disabled.';
            }

            if ($password_is_good && !password_verify($oldpasswd, $user->password)) {
                $password_is_good = false;
                $error_msg = 'Your old password is incorrect.';
            }

            if ($password_is_good && $passwd !== $passwd2) {
                $password_is_good = false;
                $error_msg = 'Passwords do not match.';
            }

            $minimum_length = config('cdash.password.min');
            if ($password_is_good && strlen($passwd) < $minimum_length) {
                $password_is_good = false;
                $error_msg = "Password must be at least $minimum_length characters.";
            }

            if ($password_is_good) {
                $password_validator = new Password();
                $complexity_count = config('cdash.password.count');
                $complexity = $password_validator->computeComplexity($passwd, $complexity_count);
                $minimum_complexity = config('cdash.password.complexity');
                if ($complexity < $minimum_complexity) {
                    $password_is_good = false;
                    if ($complexity_count > 1) {
                        $error_msg = "Your password must contain at least $complexity_count characters from $minimum_complexity of the following types: uppercase, lowercase, numbers, and symbols.";
                    } else {
                        $error_msg = "Your password must contain at least $minimum_complexity of the following: uppercase, lowercase, numbers, and symbols.";
                    }
                }
            }

            if ($password_is_good && $passwd === $oldpasswd) {
                $error_msg = 'New password matches old password.';
                $password_is_good = false;
            }

            if ($password_is_good) {
                $user->password = password_hash($passwd, PASSWORD_DEFAULT);
                $user->password_updated_at = Carbon::now();
                if ($user->save()) {
                    $other_msg = 'Your password has been updated.';
                    if (isset($_SESSION['cdash']['redirect'])) {
                        unset($_SESSION['cdash']['redirect']);
                        request()->session()->remove('cdash.redirect');
                    }
                } else {
                    $error_msg = 'Cannot update password.';
                }
            }
        }

        if ($can_change_password && request()->boolean('password_expired')) {
            $error_msg = 'Password has expired';
        }

        if ($can_change_password && ($_GET['reason'] ?? '') === 'expired') {
            $error_msg = 'Your password has expired. Please set a new one.';
        }

        $durationConfig = (int) Config::get('cdash.token_duration');
        $maximumExpiration = $durationConfig === 0 ? Carbon::now()->endOfMillennium() : Carbon::now()->addSeconds($durationConfig);

        return $this->vue('profile-page', 'Profile', [
            'user' => $user,
            'error' => $error_msg,
            'message' => $other_msg,
            'can-change-email' => $can_change_email,
            'can-change-password' => $can_change_password,
            'max-token-expiration' => $maximumExpiration->subDay()->toDateString(),
        ]);
    }

    /**
     * Changes the user's email address to the one in a link sent by ConfirmEmailChange.
     */
    public function confirmEmailChange(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->integer('id') !== $user->id
            || !hash_equals(sha1($user->email), $request->string('hash')->toString())
            || $user->cannot('changeEmail', $user)
        ) {
            abort(403, 'This link is not valid.');
        }

        // Someone else may have taken the address since the link was sent.
        $email = $request->string('email')->toString();
        $validator = Validator::make(['email' => $email], ['email' => self::emailRules($user)]);
        if ($validator->fails()) {
            abort(409, $validator->errors()->first('email'));
        }

        $old_email = $user->email;
        $user->email = $email;
        // Also saves the new address.
        $user->markEmailAsVerified();

        Mail::to($old_email)->send(new EmailChanged($old_email, $email));

        return redirect('/profile')->with('status', 'Your email address has been changed.');
    }

    /**
     * @return array<mixed>
     */
    private static function emailRules(User $user): array
    {
        return ['required', 'string', 'email', 'max:255', new UniqueEmailRule($user)];
    }

    /**
     * Returns the reason the user can't change their email address to $email, if any.
     */
    private static function emailChangeError(User $user, string $email, string $password): ?string
    {
        $validator = Validator::make(['email' => $email], ['email' => self::emailRules($user)]);
        if ($validator->fails()) {
            return $validator->errors()->first('email');
        }

        if (!password_verify($password, $user->password)) {
            return 'Your password is incorrect.';
        }

        return null;
    }
}
