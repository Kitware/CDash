<?php
namespace CDash\Messaging\Preferences;

abstract class NotificationPreferences implements
    PreferencesInterface,
    NotificationPreferencesInterface
{
    protected $properties = [
        'onFiltered',
        'onUpdateError',
        'onConfigureError',
        'onBuildWarning',
        'onBuildError',
        'onTestFailure',
        'onDynamicAnalysis',
        'onFixed',
        'onExpectedSiteSubmitMissing',
        'onMyCheckinIssue',
        'onCheckinIssueNightlyOnly',
        'onAnyCheckinIssue',
        '',
        '',
        'onLabel',
    ];

    protected $settings = [];

    public function get($name)
    {
        if ($this->has($name) && array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        }
        return false;
    }

    public function set($name, $val)
    {
        if ($this->has($name)) {
            $this->settings[$name] = (bool) $val;
        }
    }

    public function has($name)
    {
        return in_array($name, $this->properties);
    }

    public function notifyOn($name)
    {
        return $this->get("on{$name}");
    }

    public function __call($name, $arguments)
    {
        if (strpos($name, 'get') === 0) {
            $property = lcfirst(substr($name, 3));
            return $this->get($property);
        }
    }

    public function getPropertyNames()
    {
        return array_map(function ($p) {
            return substr($p, 2);
        }, $this->properties);
    }
}
