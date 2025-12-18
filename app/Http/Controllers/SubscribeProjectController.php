<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use CDash\Database;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class SubscribeProjectController extends AbstractProjectController
{
    /**
     * TODO: (williamjallen) this function contains legacy XSL templating and should be converted
     *       to a proper Blade template with Laravel-based DB queries eventually.  This contents
     *       this function are originally from subscribeProject.php and have been copied (almost) as-is.
     */
    public function subscribeProject(): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $xml = begin_XML_for_XSLT();

        if (!isset($_GET['projectid']) || !is_numeric($_GET['projectid'])) {
            abort(400, 'Not a valid projectid!');
        }
        $this->setProjectById((int) $_GET['projectid']);

        $db = Database::getInstance();

        $user2project = $db->executePreparedSingleRow('
                        SELECT role, emailtype, emailcategory, emailmissingsites, emailsuccess
                        FROM user2project
                        WHERE userid=? AND projectid=?
                    ', [$user->id, $this->project->Id]);
        if (!empty($user2project)) {
            $xml .= add_XML_value('role', $user2project['role']);
            $xml .= add_XML_value('emailtype', $user2project['emailtype']);
            $xml .= add_XML_value('emailmissingsites', $user2project['emailmissingsites']);
            $xml .= add_XML_value('emailsuccess', $user2project['emailsuccess']);
            $emailcategory = $user2project['emailcategory'];
            $xml .= add_XML_value('emailcategory_update', self::check_email_category('update', $emailcategory));
            $xml .= add_XML_value('emailcategory_configure', self::check_email_category('configure', $emailcategory));
            $xml .= add_XML_value('emailcategory_warning', self::check_email_category('warning', $emailcategory));
            $xml .= add_XML_value('emailcategory_error', self::check_email_category('error', $emailcategory));
            $xml .= add_XML_value('emailcategory_test', self::check_email_category('test', $emailcategory));
            $xml .= add_XML_value('emailcategory_dynamicanalysis', self::check_email_category('dynamicanalysis', $emailcategory));
        } else {
            // we set the default categories
            $xml .= add_XML_value('emailcategory_update', 1);
            $xml .= add_XML_value('emailcategory_configure', 1);
            $xml .= add_XML_value('emailcategory_warning', 1);
            $xml .= add_XML_value('emailcategory_error', 1);
            $xml .= add_XML_value('emailcategory_test', 1);
            $xml .= add_XML_value('emailcategory_dynamicanalysis', 1);
        }

        // If we ask to subscribe
        @$UpdateSubscription = $_POST['updatesubscription'];
        @$EmailType = $_POST['emailtype'];
        if (!isset($_POST['emailmissingsites'])) {
            $EmailMissingSites = 0;
        } else {
            $EmailMissingSites = $_POST['emailmissingsites'];
        }
        if (!isset($_POST['emailsuccess'])) {
            $EmailSuccess = 0;
        } else {
            $EmailSuccess = $_POST['emailsuccess'];
        }

        if ($UpdateSubscription) {
            $emailcategory_update = (int) ($_POST['emailcategory_update'] ?? 0);
            $emailcategory_configure = (int) ($_POST['emailcategory_configure'] ?? 0);
            $emailcategory_warning = (int) ($_POST['emailcategory_warning'] ?? 0);
            $emailcategory_error = (int) ($_POST['emailcategory_error'] ?? 0);
            $emailcategory_test = (int) ($_POST['emailcategory_test'] ?? 0);
            $emailcategory_dynamicanalysis = (int) ($_POST['emailcategory_dynamicanalysis'] ?? 0);

            $EmailCategory = $emailcategory_update + $emailcategory_configure + $emailcategory_warning + $emailcategory_error + $emailcategory_test + $emailcategory_dynamicanalysis;
            if (!empty($user2project)) {
                $db->executePrepared('
                UPDATE user2project
                SET
                    emailtype=?,
                    emailcategory=?,
                    emailmissingsites=?,
                    emailsuccess=?
                WHERE
                    userid=?
                    AND projectid=?
            ', [
                    $EmailType,
                    $EmailCategory,
                    $EmailMissingSites,
                    $EmailSuccess,
                    $user->id,
                    $this->project->Id,
                ]);
            }

            // Redirect
            return redirect('/user');
        }

        $xml .= '<project>';
        $xml .= add_XML_value('id', $this->project->Id);
        $xml .= add_XML_value('name', $this->project->Name);
        $xml .= add_XML_value('emailbrokensubmission', $this->project->EmailBrokenSubmission);

        $xml .= '</project>';

        foreach (Project::whereRelation('users', 'id', $user->id)->orderBy('name')->get() as $project) {
            $xml .= '<availableproject>';
            $xml .= add_XML_value('id', $project->id);
            $xml .= add_XML_value('name', $project->name);
            if ((int) $project->id === $this->project->Id) {
                $xml .= add_XML_value('selected', '1');
            }
            $xml .= '</availableproject>';
        }

        $xml .= '</cdash>';

        return $this->view('cdash', 'Subscription Settings')
            ->with('xsl', true)
            ->with('xsl_content', generate_XSLT($xml, base_path() . '/app/cdash/public/subscribeProject', true));
    }

    /**
     * Check the email category
     */
    private static function check_email_category(string $name, int $emailcategory): bool
    {
        if ($emailcategory >= 64) {
            if ($name === 'dynamicanalysis') {
                return true;
            }
            $emailcategory -= 64;
        }

        if ($emailcategory >= 32) {
            if ($name === 'test') {
                return true;
            }
            $emailcategory -= 32;
        }

        if ($emailcategory >= 16) {
            if ($name === 'error') {
                return true;
            }
            $emailcategory -= 16;
        }

        if ($emailcategory >= 8) {
            if ($name === 'warning') {
                return true;
            }
            $emailcategory -= 8;
        }

        if ($emailcategory >= 4) {
            if ($name === 'configure') {
                return true;
            }
            $emailcategory -= 4;
        }

        if ($emailcategory >= 2) {
            if ($name === 'update') {
                return true;
            }
        }
        return false;
    }
}
