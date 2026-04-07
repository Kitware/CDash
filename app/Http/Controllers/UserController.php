<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Validators\Password;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class UserController extends AbstractController
{
    public function edit(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $error_msg = '';
        $other_msg = '';

        if (isset($_POST['updateprofile'])) {
            $email = $_POST['email'] ?? '';
            // TODO: (williamjallen) validate email addresses using proper email validation regex
            if (strlen($email) < 3 || !str_contains($email, '@')) {
                $error_msg = 'Email should be a valid address.';
            } else {
                $user->email = $email;
                $user->institution = $_POST['institution'] ?? '';
                $user->lastname = $_POST['lname'] ?? '';
                $user->firstname = $_POST['fname'] ?? '';
                if ($user->save()) {
                    $other_msg = 'Your profile has been updated.';
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

            if (!password_verify($oldpasswd, $user->password)) {
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

        if (request('password_expired')) {
            $error_msg = 'Password has expired';
        }

        if (($_GET['reason'] ?? '') === 'expired') {
            $error_msg = 'Your password has expired. Please set a new one.';
        }

        $durationConfig = (int) Config::get('cdash.token_duration');
        $maximumExpiration = $durationConfig === 0 ? Carbon::now()->endOfMillennium() : Carbon::now()->addSeconds($durationConfig);

        return $this->vue('profile-page', 'Profile', [
            'user' => $user,
            'error' => $error_msg,
            'message' => $other_msg,
            'max-token-expiration' => $maximumExpiration->subDay()->toDateString(),
        ]);
    }

    public function recoverPassword(): View
    {
        $message = '';
        $warning = '';
        if (isset($_POST['recover'])) {
            $email = $_POST['email'];
            $user = User::firstWhere('email', $email);
            if ($user !== null) {  // Don't reveal whether or not this is a valid account.
                // Create a new password
                $password = Str::password(10);

                $url = url('/user');

                $text = "Hello,\n\n You have asked to recover your password for CDash.\n\n";
                $text .= 'Your new password is: ' . $password . "\n";
                $text .= 'Please go to this page to login: ';
                $text .= "$url\n";
                $text .= "\n\nGenerated by CDash";

                Mail::raw($text, function ($message) use ($email): void {
                    $message->subject('CDash password recovery')
                        ->to($email);
                });

                $user->password = password_hash($password, PASSWORD_DEFAULT);
                $user->password_updated_at = Carbon::now();
                $user->save();
            }

            $message = 'A confirmation message has been sent to your inbox.';
        }

        return $this->view('user.recover-password', 'Password Reset')
            ->with('message', $message)
            ->with('warning', $warning);
    }
}
