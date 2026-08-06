<?php

namespace App\GraphQL\Validators;

use App\Models\Project;
use App\Rules\ProjectAuthenticateSubmissionsRule;
use App\Rules\ProjectNameRule;
use App\Rules\ProjectVisibilityRule;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

final class UpdateProjectInputValidator extends Validator
{
    public function rules(): array
    {
        return [
            'name' => [
                Rule::unique(Project::class, 'name')
                    ->ignore($this->arg('id')),
                new ProjectNameRule(),
            ],
            'visibility' => [
                new ProjectVisibilityRule(),
            ],
            'authenticateSubmissions' => [
                new ProjectAuthenticateSubmissionsRule(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A project with this name already exists.',
        ];
    }
}
