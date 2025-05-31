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
    protected $labels;

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
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo('App\Models\Build', 'buildid');
    }

    /**
     * @return BelongsTo<TestOutput, $this>
     */
    public function testOutput(): BelongsTo
    {
        return $this->belongsTo('App\Models\TestOutput', 'outputid');
    }

    /**
     * @return HasMany<TestMeasurement, $this>
     */
    public function testMeasurements(): HasMany
    {
        return $this->hasMany(TestMeasurement::class, 'testid');
    }

    /**
     * @return BelongsToMany<\App\Models\Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Label::class, 'label2test', 'testid', 'labelid');
    }

    /**
     * Add a label to this buildtest.
     *
     * @deprecated 10/26/2024  The legacy Label class is deprecated.  Use the labels() Eloquent relationship instead.
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
}
