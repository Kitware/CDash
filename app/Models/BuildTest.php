<?php

namespace App\Models;

use CDash\Model\Label;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

class BuildTest extends Model
{
    public $timestamps = false;

    protected $table = 'build2test';
    protected $labels = null;

    protected $attributes = [
        'timemean' => 0.0,
        'timestd' => 0.0,
    ];

    /**
     * Get the test record for this buildtest.
     *
     * @return BelongsTo<Test, self>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo('App\Models\Test', 'testid');
    }

    /**
     * Add a label to this buildtest.
     **/
    public function addLabel(Label $label): void
    {
        if (is_null($this->labels)) {
            $this->labels = collect();
        }
        $this->labels->put($label->Text, $label);
    }

    /**
     * Get the collection of labels for this buildtest.
     **/
    public function getLabels()
    {
        if (is_null($this->labels)) {
            $this->labels = collect();
            $testlabel_models = TestLabel::where([
                ['buildid', '=', $this->buildid],
                ['outputid', '=', $this->outputid],
            ])->get();
            foreach ($testlabel_models as $testlabel_model) {
                $label = new Label();
                $label->Id = $testlabel_model->labelid;
                $text = $label->GetText();
                $this->labels->put($text, $label);
            }
        }
        return $this->labels;
    }

    /**
     * Returns a self referencing URI for the current BuildTest.
     */
    public function GetUrlForSelf(): string
    {
        $host_base = Config::get('app.url');
        return "{$host_base}/test/{$this->id}";
    }


    /**
     * Marshal functions moved here from the old BuildTest model class.
     */
    public static function marshalMissing($name, $buildid, $projectid, $projectshowtesttime, $testtimemaxstatus, $testdate): array
    {
        $data = [];
        $data['name'] = $name;
        $data['status'] = 'missing';
        $data['id'] = '';
        $data['buildtestid'] = '';
        $data['time'] = '';
        $data['details'] = '';
        $data["newstatus"] = false;

        $test = self::marshal($data, $buildid, $projectid, $projectshowtesttime, $testtimemaxstatus, $testdate);

        // Since these tests are missing they should
        // not behave like other tests
        $test['execTime'] = '';
        $test['summary'] = '';
        $test['detailsLink'] = '';
        $test['summaryLink'] = '';

        return $test;
    }

    public static function marshalStatus($status): array
    {
        $statuses = ['passed' => ['Passed', 'normal'],
                          'failed' => ['Failed', 'error'],
                          'notrun' => ['Not Run', 'warning'],
                          'missing' => ['Missing', 'missing']];

        return $statuses[$status];
    }

    // Only used in api/v1/viewTest.php
    public static function marshal($data, $buildid, $projectid, $projectshowtesttime, $testtimemaxstatus, $testdate): array
    {
        $marshaledStatus = self::marshalStatus($data['status']);
        if ($data['details'] === 'Disabled') {
            $marshaledStatus = ['Not Run', 'disabled-test'];
        }
        $marshaledData = [
            'id' => $data['id'],
            'buildid' => $buildid,
            'buildtestid' => $data['buildtestid'],
            'status' => $marshaledStatus[0],
            'statusclass' => $marshaledStatus[1],
            'name' => $data['name'],
            'execTime' => time_difference($data['time'], true, '', true),
            'execTimeFull' => floatval($data['time']),
            'details' => $data['details'],
            'summaryLink' => "testSummary.php?project=$projectid&name=" . urlencode($data['name']) . "&date=$testdate",
            'summary' => 'Summary', /* Default value later replaced by AJAX */
            'detailsLink' => "test/{$data['buildtestid']}",
        ];

        if ($data['newstatus']) {
            $marshaledData['new'] = '1';
        }

        if ($projectshowtesttime && array_key_exists('timestatus', $data)) {
            if ($data['timestatus'] == 0) {
                $marshaledData['timestatus'] = 'Passed';
                $marshaledData['timestatusclass'] = 'normal';
            } elseif ($data['timestatus'] < $testtimemaxstatus) {
                $marshaledData['timestatus'] = 'Warning';
                $marshaledData['timestatusclass'] = 'warning';
            } else {
                $marshaledData['timestatus'] = 'Failed';
                $marshaledData['timestatusclass'] = 'error';
            }
        }

        if (config('database.default') == 'pgsql' && $marshaledData['id']) {
            $buildtest = BuildTest::where('id', '=', $data['buildtestid'])->first();
            if ($buildtest) {
                $marshaledData['labels'] = $buildtest->getLabels()->keys()->all();
            }
        } else {
            if (!empty($data['labels'])) {
                $labels = explode(',', $data['labels']);
                $marshaledData['labels'] = $labels;
            }
        }

        if (isset($data['subprojectid'])) {
            $marshaledData['subprojectid'] = $data['subprojectid'];
        }

        if (isset($data['subprojectname'])) {
            $marshaledData['subprojectname'] = $data['subprojectname'];
        }

        return $marshaledData;
    }
}
