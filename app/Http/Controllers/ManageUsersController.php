<?php
namespace App\Http\Controllers;

use App\Models\User;
use CDash\Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ManageUsersController extends AbstractController
{
    /**
     * TODO: (williamjallen) this function contains legacy XSL templating and should be converted
     *       to a proper Blade template with Laravel-based DB queries eventually.  This contents
     *       this function are originally from manageUsers.php and have been copied (almost) as-is.
     */
    public function showPage(): View|RedirectResponse
    {
        $xml = begin_XML_for_XSLT();
        $xml .= '<backurl>user.php</backurl>';

        @$postuserid = $_POST['userid'];
        if ($postuserid != null && $postuserid > 0) {
            $post_user = User::find($postuserid);
        }

        if (isset($_POST['adduser'])) {
            // arrive from register form
            $email = $_POST['email'];
            $passwd = $_POST['passwd'];
            $passwd2 = $_POST['passwd2'];
            if (!($passwd == $passwd2)) {
                $xml .= add_XML_value('error', 'Passwords do not match!');
            } else {
                $fname = $_POST['fname'];
                $lname = $_POST['lname'];
                $institution = $_POST['institution'];
                if ($email && $passwd && $passwd2 && $fname && $lname && $institution) {
                    $new_user = User::where('email', $email)->first();
                    if (!is_null($new_user)) {
                        $xml .= add_XML_value('error', 'Email already registered!');
                    } else {
                        $new_user = new User();
                        $passwordHash = password_hash($passwd, PASSWORD_DEFAULT);

                        $new_user->email = $email;
                        $new_user->password = $passwordHash;
                        $new_user->firstname = $fname;
                        $new_user->lastname = $lname;
                        $new_user->institution = $institution;
                        if ($new_user->save()) {
                            $xml .= add_XML_value('warning', 'User ' . $email . ' added successfully with password:' . $passwd);
                        } else {
                            $xml .= add_XML_value('error', 'Cannot add user');
                        }
                    }
                } else {
                    $xml .= add_XML_value('error', 'Please fill in all of the required fields');
                }
            }
        } elseif (isset($_POST['makenormaluser'])) {
            if ($postuserid > 1) {
                $post_user->admin = 0;
                $post_user->save();
                $xml .= "<warning>$post_user->full_name is not administrator anymore.</warning>";
            } else {
                $xml .= '<error>Administrator should remain admin.</error>';
            }
        } elseif (isset($_POST['makeadmin'])) {
            $post_user->admin = 1;
            $post_user->save();
            $xml .= "<warning>$post_user->full_name is now an administrator.</warning>";
        } elseif (isset($_POST['removeuser'])) {
            $name = $post_user->full_name;
            $post_user->delete();
            $xml .= "<warning>$name has been removed.</warning>";
        }

        if (isset($_POST['search'])) {
            $xml .= '<search>' . $_POST['search'] . '</search>';
        }

        $config = Config::getInstance();
        if ($config->get('CDASH_FULL_EMAIL_WHEN_ADDING_USER') == 1) {
            $xml .= add_XML_value('fullemail', '1');
        }

        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/manageUsers', true),
            'title' => 'Manage Users'
        ]);
    }

    public function ajaxFindUsers(): View
    {
        $config = Config::getInstance();

        $search = $_GET['search'];
        if ($config->get('CDASH_FULL_EMAIL_WHEN_ADDING_USER') == 1) {
            $sql = "email=?";
            $params = [$search];
        } else {
            $sql = "email LIKE CONCAT('%', ?, '%') OR firstname LIKE CONCAT('%', ?, '%') OR lastname LIKE CONCAT('%', ?, '%')";
            $params = [$search, $search, $search];
        }
        $users = DB::select('SELECT * FROM ' . qid('user') . ' WHERE ' . $sql, $params);

        return view('admin.find-users')
            ->with('users', $users)
            ->with('search', $search);
    }
}
