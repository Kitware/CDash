<?php
namespace App\Http\Controllers;

use App\Models\User;
use CDash\Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ManageUsersController extends AbstractController
{
    public function showPage(): View|RedirectResponse
    {
        $postuserid = (int) ($_POST['userid'] ?? -1);
        if ($postuserid > 0) {
            $post_user = User::find($postuserid);
        }

        $warning = '';
        $error = '';

        if (isset($_POST['adduser'])) {
            // arrive from register form
            $email = $_POST['email'] ?? '';
            $passwd = $_POST['passwd'] ?? '';
            $passwd2 = $_POST['passwd2'] ?? '';
            if ($passwd !== $passwd2) {
                $error = 'Passwords do not match!';
            } else {
                $fname = $_POST['fname'] ?? '';
                $lname = $_POST['lname'] ?? '';
                $institution = $_POST['institution'] ?? '';
                if ($email !== '' && $passwd !== '' && $passwd2 !== '' && $fname !== '' && $lname !== '' && $institution !== '') {
                    $new_user = User::where('email', $email)->first();
                    if ($new_user !== null) {
                        $error = 'Email already registered!';
                    } else {
                        $new_user = new User();
                        $passwordHash = password_hash($passwd, PASSWORD_DEFAULT);

                        $new_user->email = $email;
                        $new_user->password = $passwordHash;
                        $new_user->firstname = $fname;
                        $new_user->lastname = $lname;
                        $new_user->institution = $institution;
                        if ($new_user->save()) {
                            $warning = "User $email added successfully with password: $passwd";
                        } else {
                            $error = 'Cannot add user';
                        }
                    }
                } else {
                    $error = 'Please fill in all of the required fields';
                }
            }
        } elseif (isset($_POST['makenormaluser'])) {
            if ($postuserid > 1) {
                $post_user->admin = 0;
                $post_user->save();
                $warning = "$post_user->full_name is not administrator anymore.";
            } else {
                $error = 'Administrator should remain admin.';
            }
        } elseif (isset($_POST['makeadmin'])) {
            $post_user->admin = 1;
            $post_user->save();
            $warning = "$post_user->full_name is now an administrator.";
        } elseif (isset($_POST['removeuser'])) {
            $name = $post_user->full_name;
            $post_user->delete();
            $warning = "$name has been removed.";
        }


        return view('admin.manage-users')
            ->with('warning', $warning)
            ->with('error', $error)
            ->with('search', $_POST['search'] ?? '')
            ->with('fullemail', Config::getInstance()->get('CDASH_FULL_EMAIL_WHEN_ADDING_USER'));
    }

    public function ajaxFindUsers(): View
    {
        $config = Config::getInstance();

        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            if ($config->get('CDASH_FULL_EMAIL_WHEN_ADDING_USER') == 1) {
                $sql = "email=?";
                $params = [$search];
            } else {
                $search = '%' . $search . '%';
                $sql = "email LIKE ? OR firstname LIKE ? OR lastname LIKE ?";
                $params = [$search, $search, $search];
            }
            $users = DB::select('SELECT * FROM ' . qid('user') . ' WHERE ' . $sql, $params);
        } else {
            $users = [];
        }

        return view('admin.find-users')
            ->with('users', $users)
            ->with('search', $search);
    }
}
