<?php

namespace App\Models;

use CDash\Model\Label;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

/**
 * NOTE: This model represents an individual test run.
 *
 * @property int $id
 * @property int $buildid
 * @property int $outputid
 * @property string $status
 * @property float $time
 * @property float $timemean
 * @property float $timestd
 * @property int $timestatus
 * @property int $newstatus
 * @property string $details
 * @property string $testname
 *
 * @mixin Builder<Test>
 */
class Test extends Model
{
    public $timestamps = false;

    protected $table = 'build2test';

    /**
     * @deprecated 08/24/2024  This member variable is deprecated.  Use the labels() Eloquent relationship instead.
     */
    protected $labels = null;

    // TODO: Put these in an enum somewhere
    public const FAILED = 'failed';
    public const PASSED = 'passed';
    public const TIMEOUT = 'Timeout';
    public const NOTRUN = 'notrun';
    public const DISABLED = 'Disabled';

    protected $attributes = [
        'timemean' => 0.0,
        'timestd' => 0.0,
    ];

    protected $fillable = [
        'buildid',
        'outputid',
        'status',
        'time',
        'timemean',
        'timestd',
        'timestatus',
        'newstatus',
        'details',
        'testname',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildid' => 'integer',
        'outputid' => 'integer',
        'time' => 'float',
        'timemean' => 'float',
        'timestd' => 'float',
        'timestatus' => 'integer',
        'newstatus' => 'integer',
    ];

    /**
     * @return BelongsTo<Build, self>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo('App\Models\Build', 'buildid');
    }

    /**
     * @return BelongsTo<TestOutput, self>
     */
    public function testOutput(): BelongsTo
    {
        return $this->belongsTo('App\Models\TestOutput', 'outputid');
    }

    /**
     * @return HasMany<TestMeasurement>
     */
    public function testMeasurements(): HasMany
    {
        return $this->hasMany(TestMeasurement::class, 'testid');
    }

    /**
     * @return BelongsToMany<\App\Models\Label>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Label::class, 'label2test', 'testid', 'labelid');
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
     *
     * @deprecated 08/24/2024  The legacy Label class is deprecated.  Use the labels() Eloquent relationship instead.
     **/
    public function getLabels()
    {
        if (is_null($this->labels)) {
            $this->labels = collect();
            foreach ($this->labels()->get() as $eloquent_label) {
                $label = new Label();
                $label->Id = $eloquent_label->id;
                $this->labels->put($eloquent_label->text, $label);
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
        $data['testname'] = $name;
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
            'buildid' => $buildid,
            'buildtestid' => $data['buildtestid'],
            'status' => $marshaledStatus[0],
            'statusclass' => $marshaledStatus[1],
            'name' => $data['testname'],
            'execTime' => time_difference($data['time'], true, '', true),
            'execTimeFull' => floatval($data['time']),
            'details' => $data['details'],
            'summaryLink' => "testSummary.php?project=$projectid&name=" . urlencode($data['testname']) . "&date=$testdate",
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

        if ($marshaledData['buildtestid'] ?? false) {
            $test = Test::find((int) $data['buildtestid']);
            if ($test !== null) {
                $marshaledData['labels'] = $test->labels()->get(['text']);
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
