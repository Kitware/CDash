<?php

namespace App\Http\Controllers;

use App\Enums\BuildGroupType;
use App\Models\Build as EloquentBuild;
use App\Models\BuildGroup as EloquentBuildGroup;
use App\Models\Site;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;
use CDash\Model\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Fluent;

final class BuildGroupController extends AbstractController
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        // Accept JSON request bodies sent without a JSON Content-Type, like the legacy endpoint did.
        if (!$request->isJson()) {
            $body = json_decode($request->getContent(), true);
            if (is_array($body)) {
                $request->merge($body);
            }
        }

        $project = new Project();
        if ($request->has('projectid')) {
            $project->Id = $request->integer('projectid');
            if (!$project->Exists()) {
                abort(404, 'Valid project ID required');
            }
        } else {
            if (!$request->has('project')) {
                abort(400, 'Valid project required');
            }
            $project->Id = get_project_id($request->string('project')->toString());
            if (!$project->Exists()) {
                abort(404, 'Project does not exist');
            }
            Gate::authorize('view-project', $project);
        }

        if (!Gate::allows('edit-project', $project)) {
            abort(403, 'You do not have permission to access this page.');
        }
        $projectid = (int) $project->Id;

        // Route based on what type of request this is.
        return match ($request->method()) {
            'DELETE' => self::restDelete($request, $projectid),
            'POST' => self::restPost($request, $projectid),
            'PUT' => self::restPut($request, $projectid),
            default => abort(Response::HTTP_NOT_IMPLEMENTED),
        };
    }

    private static function restDelete(Request $request, int $projectid): Response
    {
        if ($request->has('buildgroupid')) {
            // Delete the specified BuildGroup.
            $Group = new BuildGroup();
            $Group->SetId(self::getProjectBuildGroup($projectid, $request->integer('buildgroupid'))->id);
            $Group->Delete();
            return response('');
        }

        if ($request->has('wildcard')) {
            // Soft delete a wildcard build group rule.
            $wildcard = new Fluent((array) json_decode($request->string('wildcard')->toString(), true));
            $buildgrouprule = new BuildGroupRule();
            $buildgrouprule->SiteId = -1;
            $buildgrouprule->BuildType = $wildcard->get('buildtype');
            $buildgrouprule->BuildName = self::convertWildcards($wildcard->string('match')->toString());
            $buildgrouprule->GroupId = self::getProjectBuildGroup($projectid, $wildcard->integer('buildgroupid'))->id;
            $buildgrouprule->Delete(true);
        }

        if ($request->has('dynamic')) {
            // Soft delete a dynamic build group rule.
            $dynamic = new Fluent((array) json_decode($request->string('dynamic')->toString(), true));
            $rule = new Fluent((array) json_decode($request->string('rule')->toString(), true));

            $buildgrouprule = new BuildGroupRule();
            $buildgrouprule->GroupId = self::getProjectBuildGroup($projectid, $dynamic->integer('id'))->id;
            $buildgrouprule->BuildName = $rule->get('match') ?? '';

            $siteid = $rule->integer('siteid');
            if ($siteid > 0) {
                $buildgrouprule->SiteId = $siteid;
            }

            $parentgroupid = $rule->integer('parentgroupid');
            if ($parentgroupid > 0) {
                $buildgrouprule->ParentGroupId = $parentgroupid;
            }

            $buildgrouprule->Delete(true);
        }

        return response('');
    }

    private static function restPost(Request $request, int $projectid): JsonResponse|Response
    {
        $now = gmdate(FMT_DATETIME);

        if ($request->has('newbuildgroup')) {
            // Create a new buildgroup or return an existing one.
            $BuildGroup = new BuildGroup();
            $BuildGroup->SetProjectId($projectid);
            $BuildGroup->SetName(htmlspecialchars(pdo_real_escape_string($request->input('newbuildgroup'))));

            if ($BuildGroup->Exists()) {
                $status_code = 200;
            } else {
                $status_code = 201;
                $BuildGroup->SetType(htmlspecialchars(pdo_real_escape_string($request->input('type'))));
                $BuildGroup->Save();
            }

            // Respond with a JSON representation of this buildgroup.
            return response()->json([
                'id' => $BuildGroup->GetId(),
                'name' => $BuildGroup->GetName(),
                'autoremovetimeframe' => $BuildGroup->GetAutoRemoveTimeFrame(),
            ], $status_code);
        }

        if ($request->has('newLayout')) {
            // Update the order of the buildgroups for this project.
            $layout = $request->array('newLayout');
            if (count($layout) > 0) {
                // Check every group before the old layout is removed.
                $positions = [];
                foreach (array_keys($layout) as $index) {
                    $positions[] = [
                        'buildgroupid' => self::getProjectBuildGroup($projectid, $request->integer("newLayout.{$index}.id"))->id,
                        'position' => $request->integer("newLayout.{$index}.position"),
                    ];
                }

                // Remove old build group layout for this project.
                DB::delete('
                    DELETE FROM buildgroupposition WHERE buildgroupid IN
                    (SELECT bgp.buildgroupid FROM buildgroupposition AS bgp
                    LEFT JOIN buildgroup AS bg ON (bgp.buildgroupid = bg.id)
                    WHERE bg.projectid = ?)
                ', [$projectid]);

                DB::table('buildgroupposition')->insert($positions);
            }
            return response('');
        }

        if ($request->has('builds')) {
            // Move builds to a new group.
            if ($request->integer('group.id') < 1) {
                abort(400, 'Please select a group for these builds');
            }
            $newgroup = self::getProjectBuildGroup($projectid, $request->integer('group.id'));
            if ($newgroup->type !== BuildGroupType::DAILY) {
                abort(400, 'Builds cannot be assigned to dynamic build groups.');
            }

            // Check every build before any of them are moved.
            $buildids = array_map(
                fn ($index): int => $request->integer("builds.{$index}.id"),
                array_keys($request->array('builds')),
            );
            if (EloquentBuild::whereIn('id', $buildids)->where('projectid', '!=', $projectid)->exists()) {
                abort(403, 'Requested builds are not associated with this project.');
            }
            $expected = $request->input('expected', 0);

            foreach ($buildids as $buildid) {
                $Build = new Build();
                $Build->Id = $buildid;
                $Build->FillFromId($Build->Id);
                $prevgroupid = $Build->GroupId;

                // Change the group for this build.
                DB::update('
                    UPDATE build2group
                    SET groupid = ?
                    WHERE groupid = ? AND buildid = ?
                ', [$newgroup->id, $prevgroupid, $Build->Id]);

                // Soft delete any previous rules.
                $buildgrouprule = new BuildGroupRule($Build);
                $buildgrouprule->Delete(true);

                // Add the new rule.
                $buildgrouprule->GroupId = $newgroup->id;
                $buildgrouprule->Expected = $expected;
                $buildgrouprule->StartTime = $now;
                if (!$buildgrouprule->Save()) {
                    abort(500, 'Error saving rule');
                }
            }
        }

        if ($request->has('nameMatch')) {
            // Define a BuildGroup by Build name.
            if ($request->integer('group.id') < 1) {
                abort(400, 'Please select a BuildGroup to define.');
            }
            $rulegroup = self::getProjectBuildGroup($projectid, $request->integer('group.id'));
            if ($rulegroup->type !== BuildGroupType::DAILY) {
                abort(400, 'Builds cannot be assigned to dynamic build groups.');
            }

            $buildgrouprule = new BuildGroupRule();
            $buildgrouprule->GroupId = $rulegroup->id;
            $buildgrouprule->BuildType = $request->input('type');
            $buildgrouprule->BuildName = self::convertWildcards($request->string('nameMatch')->toString());
            $buildgrouprule->SiteId = -1;
            $buildgrouprule->StartTime = $now;
            if (!$buildgrouprule->Save()) {
                abort(500, 'Error saving rule');
            }
        }

        if ($request->array('dynamic') !== []) {
            // Add a build row to a dynamic group
            $groupid = self::getProjectBuildGroup($projectid, $request->integer('dynamic.id'))->id;
            $parentgroupid = $request->integer('buildgroup.id');
            $siteid = $request->integer('site.id');
            $match = $request->input('match') ?? '';

            $buildgrouprule = new BuildGroupRule();
            $buildgrouprule->GroupId = $groupid;
            $buildgrouprule->BuildName = $match;
            $buildgrouprule->SiteId = $siteid;
            $buildgrouprule->ParentGroupId = $parentgroupid;
            $buildgrouprule->StartTime = $now;
            if (!$buildgrouprule->Save()) {
                abort(500, 'Error saving rule');
            }

            // Respond with a JSON representation of this new rule.
            return response()->json(cast_data_for_JSON([
                'match' => $match,
                'siteid' => $siteid,
                'sitename' => $siteid > 0 ? $request->input('site.name') : 'Any',
                'parentgroupid' => $parentgroupid,
                'parentgroupname' => $parentgroupid > 0 ? $request->input('buildgroup.name') : 'Any',
            ]));
        }

        return response('');
    }

    private static function restPut(Request $request, int $projectid): Response
    {
        if ($request->has('buildgroup')) {
            // Modify an existing buildgroup.  Unchecked checkboxes are not included in the request.
            $buildgroup = new Fluent((array) json_decode($request->string('buildgroup')->toString(), true));

            $BuildGroup = new BuildGroup();
            $BuildGroup->SetId(self::getProjectBuildGroup($projectid, $buildgroup->integer('id'))->id);
            $BuildGroup->SetName(pdo_real_escape_string($buildgroup->get('name')));
            $BuildGroup->SetDescription(pdo_real_escape_string($buildgroup->get('description')));
            $BuildGroup->SetSummaryEmail($buildgroup->integer('summaryemail'));
            $BuildGroup->SetEmailCommitters($buildgroup->integer('emailcommitters'));
            $BuildGroup->SetIncludeSubProjectTotal($buildgroup->integer('includesubprojecttotal'));
            $BuildGroup->SetAutoRemoveTimeFrame($buildgroup->integer('autoremovetimeframe'));

            if (!$BuildGroup->Save()) {
                abort(500, 'Failed to save BuildGroup');
            }
        }

        $dynamiclist = $request->array('dynamiclist');
        if ($dynamiclist !== []) {
            // Update a list of dynamic builds.
            $buildgroupid = self::getProjectBuildGroup($projectid, $request->integer('buildgroupid'))->id;
            $build_group = new BuildGroup();
            $build_group->SetId($buildgroupid);
            $old_rules = $build_group->GetRules();

            $now = gmdate(FMT_DATETIME);
            foreach (array_keys($dynamiclist) as $index) {
                // Populate a model of the requested rule.
                $new_rule = new BuildGroupRule();
                $new_rule->GroupId = $buildgroupid;
                $new_rule->ProjectId = $projectid;
                $new_rule->BuildName = $request->string("dynamiclist.{$index}.match")->toString();
                $parentgroupid = $request->integer("dynamiclist.{$index}.parentgroupid");
                if ($parentgroupid > 0) {
                    $new_rule->ParentGroupId = $parentgroupid;
                }
                $sitename = $request->input("dynamiclist.{$index}.site");
                if ($sitename === 'Any') {
                    $siteid = 0;
                } else {
                    $siteid = Site::where(['name' => $sitename])->first()->id ?? 0;
                }
                if ($siteid > 0) {
                    $new_rule->SiteId = $siteid;
                }

                if ($new_rule->Exists()) {
                    // We already have this rule, no need to add it again.
                    // Remove it from our list of old rules to soft-delete.
                    $idx_to_unset = -1;
                    foreach ($old_rules as $idx => $old_rule) {
                        if ($old_rule->BuildName === $new_rule->BuildName
                                && $old_rule->ParentGroupId === $new_rule->ParentGroupId
                                && $old_rule->SiteId === $new_rule->SiteId) {
                            $idx_to_unset = $idx;
                            break;
                        }
                    }
                    if ($idx_to_unset > -1) {
                        unset($old_rules[$idx_to_unset]);
                    }
                } else {
                    // Save this new rule.
                    $new_rule->StartTime = $now;
                    $new_rule->Save();
                }
            }

            foreach ($old_rules as $old_rule) {
                // Soft-delete any old rules that weren't included in the new set.
                $old_rule->Delete(true);
            }
        }

        return response('');
    }

    /**
     * Get the requested build group, making sure it belongs to the project the user was authorized for.
     */
    private static function getProjectBuildGroup(int $projectid, int $groupid): EloquentBuildGroup
    {
        $group = EloquentBuildGroup::findOrFail($groupid);
        if ($group->projectid !== $projectid) {
            abort(403, 'Requested build group is not associated with this project.');
        }
        return $group;
    }

    /** Convert wildcard characters to SQL format */
    private static function convertWildcards(string $match): string
    {
        if ($match === '') {
            return $match;
        }
        if (str_contains($match, '*')) {
            $match = str_replace('*', '%', $match);
        }
        return '%' . trim($match, '%') . '%';
    }
}
