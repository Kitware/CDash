<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ProjectNotificationsController extends AbstractProjectController
{
    /**
     * The email categories a user can choose from, mapped to their bit in user2project.emailcategory.
     */
    private const EMAIL_CATEGORIES = [
        'update' => BitmaskNotificationPreferences::EMAIL_UPDATE,
        'configure' => BitmaskNotificationPreferences::EMAIL_CONFIGURE,
        'warning' => BitmaskNotificationPreferences::EMAIL_WARNING,
        'error' => BitmaskNotificationPreferences::EMAIL_ERROR,
        'test' => BitmaskNotificationPreferences::EMAIL_TEST,
        'dynamicanalysis' => BitmaskNotificationPreferences::EMAIL_DYNAMIC_ANALYSIS,
    ];

    public function show(int $project_id): View
    {
        $this->setProjectById($project_id);

        /** @var User $user */
        $user = auth()->user();

        $preferences = self::getPreferences($user, $project_id);
        $eloquentProject = Project::findOrFail($project_id);

        $emailCategories = [];
        foreach (self::EMAIL_CATEGORIES as $category => $bit) {
            if (($preferences->emailcategory & $bit) !== 0) {
                $emailCategories[] = $category;
            }
        }

        return $this->vue('project-notifications-page', 'Notifications', [
            'project-id' => $eloquentProject->id,
            'project-emails-enabled' => $eloquentProject->emailbrokensubmission,
            'can-edit-project' => $user->can('update', $eloquentProject),
            'email-type' => $preferences->emailtype,
            'email-success' => (bool) $preferences->emailsuccess,
            'email-missing-sites' => (bool) $preferences->emailmissingsites,
            'email-categories' => $emailCategories,
            'message' => session('message', ''),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request, int $project_id): RedirectResponse
    {
        $this->setProjectById($project_id);

        /** @var User $user */
        $user = $request->user();

        // Aborts if the user is not a member of the project.
        self::getPreferences($user, $project_id);

        $validated = $request->validate([
            'emailtype' => ['required', 'integer', Rule::in([0, 1, 2, 3])],
            'emailsuccess' => ['boolean'],
            'emailmissingsites' => ['boolean'],
            'emailcategories' => ['array'],
            'emailcategories.*' => ['string', Rule::in(array_keys(self::EMAIL_CATEGORIES))],
        ]);

        $emailCategory = 0;
        foreach ($validated['emailcategories'] ?? [] as $category) {
            $emailCategory |= self::EMAIL_CATEGORIES[$category];
        }

        $user->projects()->updateExistingPivot($project_id, [
            'emailtype' => (int) $validated['emailtype'],
            'emailcategory' => $emailCategory,
            'emailsuccess' => $request->boolean('emailsuccess'),
            'emailmissingsites' => $request->boolean('emailmissingsites'),
        ]);

        return redirect("/projects/{$project_id}/notifications")
            ->with('message', 'Your notification preferences have been updated.');
    }

    /**
     * Notification preferences are stored on the user's project membership, so only members have any.
     *
     * @return Pivot&object{emailtype: int, emailcategory: int, emailsuccess: int, emailmissingsites: int}
     */
    private static function getPreferences(User $user, int $project_id): Pivot
    {
        /** @var (Pivot&object{emailtype: int, emailcategory: int, emailsuccess: int, emailmissingsites: int})|null $preferences */
        $preferences = $user->projects()
            ->withPivot(['emailtype', 'emailcategory', 'emailsuccess', 'emailmissingsites'])
            ->find($project_id)
            ?->pivot;

        if ($preferences === null) {
            abort(403, 'You must be a member of this project to manage its notifications.');
        }

        return $preferences;
    }
}
