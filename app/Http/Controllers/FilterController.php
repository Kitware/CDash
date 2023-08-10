<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

require_once 'include/filterdataFunctions.php';

final class FilterController extends AbstractController
{
    public function getFilterDataArray(): JsonResponse
    {
        $page_id = $_GET['page_id'] ?? '';
        $filterdata = get_filterdata_from_request($page_id);
        $fields_to_preserve = [
            'filters',
            'filtercombine',
            'limit',
            'othercombine',
            'showfilters',
            'showlimit'
        ];
        foreach ($filterdata as $key => $value) {
            if (!in_array($key, $fields_to_preserve)) {
                unset($filterdata[$key]);
            }
        }
        $filterdata['availablefilters'] = self::getFiltersForPage($page_id);
        $filterdata['showdaterange'] = self::isDatePage($page_id);

        return response()->json(cast_data_for_JSON($filterdata));
    }

    /**
     * Similar to createPageSpecificFilters, but it just returns a list of filter
     * names which is handled in javascript.
     *
     * @return array<string>
     */
    private static function getFiltersForPage(string $page_id): array
    {
        return match ($page_id) {
            'index.php', 'project.php', 'viewBuildGroup.php' => [
                'buildduration',
                'builderrors',
                'buildwarnings',
                'buildname',
                'buildstamp',
                'buildstarttime',
                'buildtype',
                'configureduration',
                'configureerrors',
                'configurewarnings',
                'expected',
                'groupname',
                'hascoverage',
                'hasctestnotes',
                'hasdynamicanalysis',
                'hasusernotes',
                'label',
                'revision',
                'site',
                'buildgenerator',
                'subprojects',
                'testsduration',
                'testsfailed',
                'testsnotrun',
                'testspassed',
                'testtimestatus',
                'updateduration',
                'updatedfiles',
            ],
            'indexchildren.php' => [
                'buildduration',
                'builderrors',
                'buildwarnings',
                'buildstarttime',
                'buildtype',
                'configureduration',
                'configureerrors',
                'configurewarnings',
                'groupname',
                'hascoverage',
                'hasctestnotes',
                'hasdynamicanalysis',
                'hasusernotes',
                'label',
                'buildgenerator',
                'subprojects',
                'testsduration',
                'testsfailed',
                'testsnotrun',
                'testspassed',
                'testtimestatus',
                'updateduration',
                'updatedfiles',
            ],
            'queryTests.php' => [
                'buildname',
                'buildstarttime',
                'details',
                'groupname',
                'label',
                'revision',
                'site',
                'status',
                'testname',
                'testoutput',
                'time',
            ],
            'viewCoverage.php', 'getviewcoverage.php' => [
                'coveredlines',
                'filename',
                'labels',
                'priority',
                'totallines',
                'uncoveredlines',
            ],
            'viewTest.php' => [
                'details',
                'label',
                'status',
                'subproject',
                'testname',
                'timestatus',
                'time',
            ],
            'testOverview.php' => [
                'buildname',
                'subproject',
                'testname',
            ],
            'compareCoverage.php' => ['subproject'],
            default => [],
        };
    }

    private static function isDatePage(string $page_id): bool
    {
        switch ($page_id) {
            case 'compareCoverage.php':
            case 'index.php':
            case 'indexchildren.php':
            case 'project.php':
            case 'queryTests.php':
            case 'testOverview.php':
            case 'viewBuildGroup.php':
                return true;

            case 'getviewcoverage.php':
            case 'viewCoverage.php':
            case 'viewTest.php':
            default:
                return false;
        }
    }
}
