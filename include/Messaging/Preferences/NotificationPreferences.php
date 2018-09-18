<?php
namespace CDash\Messaging\Preferences;

use CDash\Messaging\Notification\NotifyOn;

abstract class NotificationPreferences implements
    PreferencesInterface,
    NotificationPreferencesInterface
{
    protected $properties = [
        NotifyOn::FILTERED,
        NotifyOn::UPDATE_ERROR,
        NotifyOn::CONFIGURE,
        NotifyOn::BUILD_WARNING,
        NotifyOn::BUILD_ERROR,
        NotifyOn::TEST_FAILURE,
        NotifyOn::DYNAMIC_ANALYSIS,
        NotifyOn::FIXED,
        NotifyOn::SITE_MISSING,
        NotifyOn::AUTHORED,
        NotifyOn::GROUP_NIGHTLY,
        NotifyOn::ANY,
        NotifyOn::LABELED,
        NotifyOn::NEVER,
        NotifyOn::REDUNDANT,
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
        return $this->get($name);
    }

    public function getPropertyNames()
    {
        return $this->properties;
    }

    public function setPreferencesFromEmailTypeProperty($emailType)
    {
        $type = (int) $emailType;
        if ($type === 1) {
            $this->set(NotifyOn::AUTHORED, true);
            $this->set(NotifyOn::ANY, false);
        } elseif ($type === 2) {
            $this->set(NotifyOn::GROUP_NIGHTLY, true);
            $this->set(NotifyOn::ANY, false);
        } elseif ($type === 3) {
            $this->set(NotifyOn::ANY, true);
            $this->set(NotifyOn::AUTHORED, false);
        }
    }
}
