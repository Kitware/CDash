<?php
namespace App\Http\Controllers;

use App\Models\User;
use CDash\Database;
use App\Models\Banner;
use CDash\Model\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ManageBannerController extends AbstractController
{
    /**
     * TODO: (williamjallen) this function contains legacy XSL templating and should be converted
     *       to a proper Blade template with Laravel-based DB queries eventually.  This contents
     *       this function are originally from manageBanner.php and have been copied (almost) as-is.
     */
    public function manageBanner(): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $xml = begin_XML_for_XSLT();
        $xml .= '<backurl>user.php</backurl>';
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Banner</menusubtitle>';

        $project = new Project;
        if (isset($_GET['projectid']) && (int) $_GET['projectid'] > 0) {
            $project->Id = (int) $_GET['projectid'];
            Gate::authorize('edit-project', $project);
        } elseif ($user->IsAdmin()) {
            // We are able to set the global banner
            $project->Id = 0;
        } else {
            // Deny access
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "You do not have permission to access this page"
            ]);
        }

        // If user is admin then we can add a banner for all projects
        if ($user->IsAdmin()) {
            $xml .= '<availableproject>';
            $xml .= add_XML_value('id', '0');
            $xml .= add_XML_value('name', 'All');
            if ($project->Id === 0) {
                $xml .= add_XML_value('selected', '1');
            }
            $xml .= '</availableproject>';
        }

        $sql = 'SELECT id, name FROM project';
        $params = [];
        if (!$user->IsAdmin()) {
            $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid=? AND role>0)";
            $params[] = intval(Auth::id());
        }

        $db = Database::getInstance();
        $projects = $db->executePrepared($sql, $params);
        foreach ($projects as $project_array) {
            $xml .= '<availableproject>';
            $xml .= add_XML_value('id', $project_array['id']);
            $xml .= add_XML_value('name', $project_array['name']);
            if ($project_array['id'] == $project->Id) {
                $xml .= add_XML_value('selected', '1');
            }
            $xml .= '</availableproject>';
        }

        $Banner = new Banner();
        $Banner->projectid = $project->Id;

        // If submit has been pressed
        @$updateMessage = $_POST['updateMessage'];
        if (isset($updateMessage)) {
            $Banner = Banner::updateOrCreate(['projectid' => $project->Id], ['text' => $_POST['message']]);
        } else {
            $Banner = Banner::findOrNew($project->Id);
        }

        /* We start generating the XML here */
        // List the available projects
        $xml .= '<project>';
        $xml .= add_XML_value('id', $project->Id);
        $xml .= add_XML_value('text', $Banner->text);

        if ($project->Id > 0) {
            $xml .= add_XML_value('name', $project->GetName());
            $xml .= add_XML_value('name_encoded', urlencode($project->GetName()));
        }
        $xml .= add_XML_value('id', $project->Id);
        $xml .= '</project>';

        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/manageBanner', true),
            'project' => $project,
            'title' => 'Manage Banner'
        ]);
    }
}
