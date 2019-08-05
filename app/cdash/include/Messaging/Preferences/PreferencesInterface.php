<?php
namespace CDash\Messaging\Preferences;

interface PreferencesInterface
{
    public function get($name);
    public function set($name, $val);
    public function has($name);
    public function getPropertyNames();
}
