<?php

namespace Tests\Feature;

use Tests\TestCase;

class SubProjectDependencies extends TestCase
{
    public function testDependenciesGraphAPI(): void
    {
        $api_route = '/api/v1/getSubProjectDependencies.php';

        // verify result for incorrect data
        $this->getJson($api_route)->assertJsonStructure([
            'error',
            'code',
        ]);
        $_GET['project'] = 'NoSuchProject'; // hack until we migrate to laravel
        $this->getJson($api_route)->assertJsonStructure([
            'error',
            'code',
        ]);

        // verify api response for non-empty project
        $_GET['project'] = 'SubProjectExample'; // hack until we migrate to laravel
        $_GET['date'] = '2009-08-06 12:19:56';
        $this->getJson($api_route)->assertJsonStructure([
            'dependencies' => [
                [
                    "name",
                    "id",
                    "depends",
                ],
            ],
        ]);
    }
}
