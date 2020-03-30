<?php

namespace App\Models;

use CDash\Model\Label;

use Illuminate\Database\Eloquent\Model;

class BuildTest extends Model
{
    public $timestamps = false;

    protected $table = 'build2test';
    protected $labels = null;

    protected $attributes = [
        'timemean' => 0.0,
        'timestd' => 0.0
    ];

    /**
     * Get the test record for this buildtest.
     */
    public function test()
    {
        return $this->belongsTo('App\Models\Test', 'testid');
    }

    /**
     * Add a label to this buildtest.
     **/
    public function addLabel(Label $label)
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
            return [];
        }
        return $this->labels;
    }

    /**
     * Returns a self referencing URI for the current BuildTest.
     *
     * @return string
     */
    public function GetUrlForSelf()
    {
        $host_base = \Config::get('app.url');
        return "{$host_base}/testDetails.php?test={$this->outputid}&build={$this->buildid}";
    }
}
