<?php

/**
 *  Base include file for SimpleTest.
 *
 * @version    $Id$
 */

/**#@+
 * include SimpleTest files
 */
require_once dirname(__FILE__) . '/page.php';
require_once dirname(__FILE__) . '/encoding.php';
/**#@-*/

/**
 *    Creates tags and widgets given HTML tag
 *    attributes.
 */
class SimpleTagBuilder
{
    /**
     *    Factory for the tag objects. Creates the
     *    appropriate tag object for the incoming tag name
     *    and attributes.
     *
     * @param string $name HTML tag name
     * @param hash $attributes element attributes
     *
     * @return SimpleTag tag object
     */
    public function createTag($name, $attributes)
    {
        static $map = [
            'a' => 'SimpleAnchorTag',
            'title' => 'SimpleTitleTag',
            'base' => 'SimpleBaseTag',
            'button' => 'SimpleButtonTag',
            'textarea' => 'SimpleTextAreaTag',
            'option' => 'SimpleOptionTag',
            'label' => 'SimpleLabelTag',
            'form' => 'SimpleFormTag',
            'frame' => 'SimpleFrameTag'];
        $attributes = $this->keysToLowerCase($attributes);
        if (array_key_exists($name, $map)) {
            $tag_class = $map[$name];
            return new $tag_class($attributes);
        } elseif ($name == 'select') {
            return $this->createSelectionTag($attributes);
        } elseif ($name == 'input') {
            return $this->createInputTag($attributes);
        }
        return new SimpleTag($name, $attributes);
    }

    /**
     *    Factory for selection fields.
     *
     * @param hash $attributes element attributes
     *
     * @return SimpleTag tag object
     */
    protected function createSelectionTag($attributes)
    {
        if (isset($attributes['multiple'])) {
            return new MultipleSelectionTag($attributes);
        }
        return new SimpleSelectionTag($attributes);
    }

    /**
     *    Factory for input tags.
     *
     * @param hash $attributes element attributes
     *
     * @return SimpleTag tag object
     */
    protected function createInputTag($attributes)
    {
        if (!isset($attributes['type'])) {
            return new SimpleTextTag($attributes);
        }
        $type = strtolower(trim($attributes['type']));
        $map = [
            'submit' => 'SimpleSubmitTag',
            'image' => 'SimpleImageSubmitTag',
            'checkbox' => 'SimpleCheckboxTag',
            'radio' => 'SimpleRadioButtonTag',
            'text' => 'SimpleTextTag',
            'hidden' => 'SimpleTextTag',
            'password' => 'SimpleTextTag',
            'file' => 'SimpleUploadTag'];
        if (array_key_exists($type, $map)) {
            $tag_class = $map[$type];
            return new $tag_class($attributes);
        }
        return false;
    }

    /**
     *    Make the keys lower case for case insensitive look-ups.
     *
     * @param hash $map hash to convert
     *
     * @return hash unchanged values, but keys lower case
     */
    protected function keysToLowerCase($map)
    {
        $lower = [];
        foreach ($map as $key => $value) {
            $lower[strtolower($key)] = $value;
        }
        return $lower;
    }
}

/**
 *    HTML or XML tag.
 */
class SimpleTag
{
    private $name;
    private $attributes;
    private $content;

    /**
     *    Starts with a named tag with attributes only.
     *
     * @param string $name tag name
     * @param hash $attributes Attribute names and
     *                         string values. Note that
     *                         the keys must have been
     *                         converted to lower case.
     */
    public function __construct($name, $attributes)
    {
        $this->name = strtolower(trim($name));
        $this->attributes = $attributes;
        $this->content = '';
    }

    /**
     *    Check to see if the tag can have both start and
     *    end tags with content in between.
     *
     * @return bool true if content allowed
     */
    public function expectEndTag()
    {
        return true;
    }

    /**
     *    The current tag should not swallow all content for
     *    itself as it's searchable page content. Private
     *    content tags are usually widgets that contain default
     *    values.
     *
     * @return bool false as content is available
     *              to other tags by default
     */
    public function isPrivateContent()
    {
        return false;
    }

    /**
     *    Appends string content to the current content.
     *
     * @param string $content additional text
     */
    public function addContent($content)
    {
        $this->content .= (string) $content;
        return $this;
    }

    /**
     *    Adds an enclosed tag to the content.
     *
     * @param SimpleTag $tag new tag
     */
    public function addTag($tag)
    {
    }

    /**
     *    Adds multiple enclosed tags to the content.
     *
     * @param array            list of SimpleTag objects to be added
     */
    public function addTags($tags)
    {
        foreach ($tags as $tag) {
            $this->addTag($tag);
        }
    }

    /**
     *    Accessor for tag name.
     *
     * @return string name of tag
     */
    public function getTagName()
    {
        return $this->name;
    }

    /**
     *    List of legal child elements.
     *
     * @return array list of element names
     */
    public function getChildElements()
    {
        return [];
    }

    /**
     *    Accessor for an attribute.
     *
     * @param string $label attribute name
     *
     * @return string attribute value
     */
    public function getAttribute($label)
    {
        $label = strtolower($label);
        if (!isset($this->attributes[$label])) {
            return false;
        }
        return (string) $this->attributes[$label];
    }

    /**
     *    Sets an attribute.
     *
     * @param string $label attribute name
     *
     * @return string $value   new attribute value
     */
    protected function setAttribute($label, $value)
    {
        $this->attributes[strtolower($label)] = $value;
    }

    /**
     *    Accessor for the whole content so far.
     *
     * @return string content as big raw string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     *    Accessor for content reduced to visible text. Acts
     *    like a text mode browser, normalising space and
     *    reducing images to their alt text.
     *
     * @return string content as plain text
     */
    public function getText()
    {
        return SimplePage::normalise($this->content);
    }

    /**
     *    Test to see if id attribute matches.
     *
     * @param string $id ID to test against
     *
     * @return bool true on match
     */
    public function isId($id)
    {
        return $this->getAttribute('id') == $id;
    }
}

/**
 *    Base url.
 */
class SimpleBaseTag extends SimpleTag
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('base', $attributes);
    }

    /**
     *    Base tag is not a block tag.
     *
     * @return bool false
     */
    public function expectEndTag()
    {
        return false;
    }
}

/**
 *    Page title.
 */
class SimpleTitleTag extends SimpleTag
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('title', $attributes);
    }
}

/**
 *    Link.
 */
class SimpleAnchorTag extends SimpleTag
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('a', $attributes);
    }

    /**
     *    Accessor for URL as string.
     *
     * @return string coerced as string
     */
    public function getHref()
    {
        $url = $this->getAttribute('href');
        if (is_bool($url)) {
            $url = '';
        }
        return $url;
    }
}

/**
 *    Form element.
 */
class SimpleWidget extends SimpleTag
{
    private $value;
    private $label;
    private $is_set;

    /**
     *    Starts with a named tag with attributes only.
     *
     * @param string $name tag name
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($name, $attributes)
    {
        parent::__construct($name, $attributes);
        $this->value = false;
        $this->label = false;
        $this->is_set = false;
    }

    /**
     *    Accessor for name submitted as the key in
     *    GET/POST privateiables hash.
     *
     * @return string parsed value
     */
    public function getName()
    {
        return $this->getAttribute('name');
    }

    /**
     *    Accessor for default value parsed with the tag.
     *
     * @return string parsed value
     */
    public function getDefault()
    {
        return $this->getAttribute('value');
    }

    /**
     *    Accessor for currently set value or default if
     *    none.
     *
     * @return string value set by form or default
     *                if none
     */
    public function getValue()
    {
        if (!$this->is_set) {
            return $this->getDefault();
        }
        return $this->value;
    }

    /**
     *    Sets the current form element value.
     *
     * @param string $value new value
     *
     * @return bool true if allowed
     */
    public function setValue($value)
    {
        $this->value = $value;
        $this->is_set = true;
        return true;
    }

    /**
     *    Resets the form element value back to the
     *    default.
     */
    public function resetValue()
    {
        $this->is_set = false;
    }

    /**
     *    Allows setting of a label externally, say by a
     *    label tag.
     *
     * @param string $label label to attach
     */
    public function setLabel($label)
    {
        $this->label = trim($label);
        return $this;
    }

    /**
     *    Reads external or internal label.
     *
     * @param string $label label to test
     *
     * @return bool true is match
     */
    public function isLabel($label)
    {
        return $this->label == trim($label);
    }

    /**
     *    Dispatches the value into the form encoded packet.
     *
     * @param SimpleEncoding $encoding form packet
     */
    public function write($encoding)
    {
        if ($this->getName()) {
            $encoding->add($this->getName(), $this->getValue());
        }
    }
}

/**
 *    Text, password and hidden field.
 */
class SimpleTextTag extends SimpleWidget
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('input', $attributes);
        if ($this->getAttribute('value') === false) {
            $this->setAttribute('value', '');
        }
    }

    /**
     *    Tag contains no content.
     *
     * @return bool false
     */
    public function expectEndTag()
    {
        return false;
    }

    /**
     *    Sets the current form element value. Cannot
     *    change the value of a hidden field.
     *
     * @param string $value new value
     *
     * @return bool true if allowed
     */
    public function setValue($value)
    {
        if ($this->getAttribute('type') == 'hidden') {
            return false;
        }
        return parent::setValue($value);
    }
}

/**
 *    Submit button as input tag.
 */
class SimpleSubmitTag extends SimpleWidget
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('input', $attributes);
        if ($this->getAttribute('value') === false) {
            $this->setAttribute('value', 'Submit');
        }
    }

    /**
     *    Tag contains no end element.
     *
     * @return bool false
     */
    public function expectEndTag()
    {
        return false;
    }

    /**
     *    Disables the setting of the button value.
     *
     * @param string $value ignored
     *
     * @return bool true if allowed
     */
    public function setValue($value)
    {
        return false;
    }

    /**
     *    Value of browser visible text.
     *
     * @return string visible label
     */
    public function getLabel()
    {
        return $this->getValue();
    }

    /**
     *    Test for a label match when searching.
     *
     * @param string $label label to test
     *
     * @return bool true on match
     */
    public function isLabel($label)
    {
        return trim($label) == trim($this->getLabel());
    }
}

/**
 *    Image button as input tag.
 */
class SimpleImageSubmitTag extends SimpleWidget
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('input', $attributes);
    }

    /**
     *    Tag contains no end element.
     *
     * @return bool false
     */
    public function expectEndTag()
    {
        return false;
    }

    /**
     *    Disables the setting of the button value.
     *
     * @param string $value ignored
     *
     * @return bool true if allowed
     */
    public function setValue($value)
    {
        return false;
    }

    /**
     *    Value of browser visible text.
     *
     * @return string visible label
     */
    public function getLabel()
    {
        if ($this->getAttribute('title')) {
            return $this->getAttribute('title');
        }
        return $this->getAttribute('alt');
    }

    /**
     *    Test for a label match when searching.
     *
     * @param string $label label to test
     *
     * @return bool true on match
     */
    public function isLabel($label)
    {
        return trim($label) == trim($this->getLabel());
    }

    /**
     *    Dispatches the value into the form encoded packet.
     *
     * @param SimpleEncoding $encoding form packet
     * @param int $x x coordinate of click
     * @param int $y y coordinate of click
     */
    public function write($encoding, $x = 1, $y = 1)
    {
        if ($this->getName()) {
            $encoding->add($this->getName() . '.x', $x);
            $encoding->add($this->getName() . '.y', $y);
        } else {
            $encoding->add('x', $x);
            $encoding->add('y', $y);
        }
    }
}

/**
 *    Submit button as button tag.
 */
class SimpleButtonTag extends SimpleWidget
{
    /**
     *    Starts with a named tag with attributes only.
     *    Defaults are very browser dependent.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('button', $attributes);
    }

    /**
     *    Check to see if the tag can have both start and
     *    end tags with content in between.
     *
     * @return bool true if content allowed
     */
    public function expectEndTag()
    {
        return true;
    }

    /**
     *    Disables the setting of the button value.
     *
     * @param string $value ignored
     *
     * @return bool true if allowed
     */
    public function setValue($value)
    {
        return false;
    }

    /**
     *    Value of browser visible text.
     *
     * @return string visible label
     */
    public function getLabel()
    {
        return $this->getContent();
    }

    /**
     *    Test for a label match when searching.
     *
     * @param string $label label to test
     *
     * @return bool true on match
     */
    public function isLabel($label)
    {
        return trim($label) == trim($this->getLabel());
    }
}

/**
 *    Content tag for text area.
 */
class SimpleTextAreaTag extends SimpleWidget
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('textarea', $attributes);
    }

    /**
     *    Accessor for starting value.
     *
     * @return string parsed value
     */
    public function getDefault()
    {
        return $this->wrap(html_entity_decode($this->getContent(), ENT_QUOTES));
    }

    /**
     *    Applies word wrapping if needed.
     *
     * @param string $value new value
     *
     * @return bool true if allowed
     */
    public function setValue($value)
    {
        return parent::setValue($this->wrap($value));
    }

    /**
     *    Test to see if text should be wrapped.
     *
     * @return bool true if wrapping on
     */
    public function wrapIsEnabled()
    {
        if ($this->getAttribute('cols')) {
            $wrap = $this->getAttribute('wrap');
            if (($wrap == 'physical') || ($wrap == 'hard')) {
                return true;
            }
        }
        return false;
    }

    /**
     *    Performs the formatting that is peculiar to
     *    this tag. There is strange behaviour in this
     *    one, including stripping a leading new line.
     *    Go figure. I am using Firefox as a guide.
     *
     * @param string $text text to wrap
     *
     * @return string Text wrapped with carriage
     *                returns and line feeds
     */
    protected function wrap($text)
    {
        $text = str_replace("\r\r\n", "\r\n", str_replace("\n", "\r\n", $text));
        $text = str_replace("\r\n\n", "\r\n", str_replace("\r", "\r\n", $text));
        if (strncmp($text, "\r\n", strlen("\r\n")) == 0) {
            $text = substr($text, strlen("\r\n"));
        }
        if ($this->wrapIsEnabled()) {
            return wordwrap(
                $text,
                (int) $this->getAttribute('cols'),
                "\r\n");
        }
        return $text;
    }

    /**
     *    The content of textarea is not part of the page.
     *
     * @return bool true
     */
    public function isPrivateContent()
    {
        return true;
    }
}

/**
 *    File upload widget.
 */
class SimpleUploadTag extends SimpleWidget
{
    /**
     *    Starts with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('input', $attributes);
    }

    /**
     *    Tag contains no content.
     *
     * @return bool false
     */
    public function expectEndTag()
    {
        return false;
    }

    /**
     *    Dispatches the value into the form encoded packet.
     *
     * @param SimpleEncoding $encoding form packet
     */
    public function write($encoding)
    {
        if (!file_exists($this->getValue())) {
            return;
        }
        $encoding->attach(
            $this->getName(),
            implode('', file($this->getValue())),
            basename($this->getValue()));
    }
}

/**
 *    Drop down widget.
 */
class SimpleSelectionTag extends SimpleWidget
{
    private $options;
    private $choice;

    /**
     *    Starts with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('select', $attributes);
        $this->options = [];
        $this->choice = false;
    }

    /**
     *    Adds an option tag to a selection field.
     *
     * @param SimpleOptionTag $tag new option
     */
    public function addTag($tag)
    {
        if ($tag->getTagName() == 'option') {
            $this->options[] = $tag;
        }
    }

    /**
     *    Text within the selection element is ignored.
     *
     * @param string $content ignored
     */
    public function addContent($content)
    {
        return $this;
    }

    /**
     *    Scans options for defaults. If none, then
     *    the first option is selected.
     *
     * @return string selected field
     */
    public function getDefault()
    {
        for ($i = 0, $count = count($this->options); $i < $count; $i++) {
            if ($this->options[$i]->getAttribute('selected') !== false) {
                return $this->options[$i]->getDefault();
            }
        }
        if ($count > 0) {
            return $this->options[0]->getDefault();
        }
        return '';
    }

    /**
     *    Can only set allowed values.
     *
     * @param string $value new choice
     *
     * @return bool true if allowed
     */
    public function setValue($value)
    {
        for ($i = 0, $count = count($this->options); $i < $count; $i++) {
            if ($this->options[$i]->isValue($value)) {
                $this->choice = $i;
                return true;
            }
        }
        return false;
    }

    /**
     *    Accessor for current selection value.
     *
     * @return string value attribute or
     *                content of opton
     */
    public function getValue()
    {
        if ($this->choice === false) {
            return $this->getDefault();
        }
        return $this->options[$this->choice]->getValue();
    }
}

/**
 *    Drop down widget.
 */
class MultipleSelectionTag extends SimpleWidget
{
    private $options;
    private $values;

    /**
     *    Starts with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('select', $attributes);
        $this->options = [];
        $this->values = false;
    }

    /**
     *    Adds an option tag to a selection field.
     *
     * @param SimpleOptionTag $tag new option
     */
    public function addTag($tag)
    {
        if ($tag->getTagName() == 'option') {
            $this->options[] = &$tag;
        }
    }

    /**
     *    Text within the selection element is ignored.
     *
     * @param string $content ignored
     */
    public function addContent($content)
    {
        return $this;
    }

    /**
     *    Scans options for defaults to populate the
     *    value array().
     *
     * @return array selected fields
     */
    public function getDefault()
    {
        $default = [];
        for ($i = 0, $count = count($this->options); $i < $count; $i++) {
            if ($this->options[$i]->getAttribute('selected') !== false) {
                $default[] = $this->options[$i]->getDefault();
            }
        }
        return $default;
    }

    /**
     *    Can only set allowed values. Any illegal value
     *    will result in a failure, but all correct values
     *    will be set.
     *
     * @param array $desired new choices
     *
     * @return bool true if all allowed
     */
    public function setValue($desired)
    {
        $achieved = [];
        foreach ($desired as $value) {
            $success = false;
            for ($i = 0, $count = count($this->options); $i < $count; $i++) {
                if ($this->options[$i]->isValue($value)) {
                    $achieved[] = $this->options[$i]->getValue();
                    $success = true;
                    break;
                }
            }
            if (!$success) {
                return false;
            }
        }
        $this->values = $achieved;
        return true;
    }

    /**
     *    Accessor for current selection value.
     *
     * @return array list of currently set options
     */
    public function getValue()
    {
        if ($this->values === false) {
            return $this->getDefault();
        }
        return $this->values;
    }
}

/**
 *    Option for selection field.
 */
class SimpleOptionTag extends SimpleWidget
{
    /**
     *    Stashes the attributes.
     */
    public function __construct($attributes)
    {
        parent::__construct('option', $attributes);
    }

    /**
     *    Does nothing.
     *
     * @param string $value ignored
     *
     * @return bool not allowed
     */
    public function setValue($value)
    {
        return false;
    }

    /**
     *    Test to see if a value matches the option.
     *
     * @param string $compare value to compare with
     *
     * @return bool true if possible match
     */
    public function isValue($compare)
    {
        $compare = trim($compare);
        if (trim($this->getValue()) == $compare) {
            return true;
        }
        return trim(strip_tags($this->getContent())) == $compare;
    }

    /**
     *    Accessor for starting value. Will be set to
     *    the option label if no value exists.
     *
     * @return string parsed value
     */
    public function getDefault()
    {
        if ($this->getAttribute('value') === false) {
            return strip_tags($this->getContent());
        }
        return $this->getAttribute('value');
    }

    /**
     *    The content of options is not part of the page.
     *
     * @return bool true
     */
    public function isPrivateContent()
    {
        return true;
    }
}

/**
 *    Radio button.
 */
class SimpleRadioButtonTag extends SimpleWidget
{
    /**
     *    Stashes the attributes.
     *
     * @param array $attributes hash of attributes
     */
    public function __construct($attributes)
    {
        parent::__construct('input', $attributes);
        if ($this->getAttribute('value') === false) {
            $this->setAttribute('value', 'on');
        }
    }

    /**
     *    Tag contains no content.
     *
     * @return bool false
     */
    public function expectEndTag()
    {
        return false;
    }

    /**
     *    The only allowed value sn the one in the
     *    "value" attribute.
     *
     * @param string $value new value
     *
     * @return bool true if allowed
     */
    public function setValue($value)
    {
        if ($value === false) {
            return parent::setValue($value);
        }
        if ($value != $this->getAttribute('value')) {
            return false;
        }
        return parent::setValue($value);
    }

    /**
     *    Accessor for starting value.
     *
     * @return string parsed value
     */
    public function getDefault()
    {
        if ($this->getAttribute('checked') !== false) {
            return $this->getAttribute('value');
        }
        return false;
    }
}

/**
 *    Checkbox widget.
 */
class SimpleCheckboxTag extends SimpleWidget
{
    /**
     *    Starts with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('input', $attributes);
        if ($this->getAttribute('value') === false) {
            $this->setAttribute('value', 'on');
        }
    }

    /**
     *    Tag contains no content.
     *
     * @return bool false
     */
    public function expectEndTag()
    {
        return false;
    }

    /**
     *    The only allowed value in the one in the
     *    "value" attribute. The default for this
     *    attribute is "on". If this widget is set to
     *    true, then the usual value will be taken.
     *
     * @param string $value new value
     *
     * @return bool true if allowed
     */
    public function setValue($value)
    {
        if ($value === false) {
            return parent::setValue($value);
        }
        if ($value === true) {
            return parent::setValue($this->getAttribute('value'));
        }
        if ($value != $this->getAttribute('value')) {
            return false;
        }
        return parent::setValue($value);
    }

    /**
     *    Accessor for starting value. The default
     *    value is "on".
     *
     * @return string parsed value
     */
    public function getDefault()
    {
        if ($this->getAttribute('checked') !== false) {
            return $this->getAttribute('value');
        }
        return false;
    }
}

/**
 *    A group of multiple widgets with some shared behaviour.
 */
class SimpleTagGroup
{
    private $widgets = [];

    /**
     *    Adds a tag to the group.
     *
     * @param SimpleWidget $widget
     */
    public function addWidget($widget)
    {
        $this->widgets[] = $widget;
    }

    /**
     *    Accessor to widget set.
     *
     * @return array all widgets
     */
    protected function &getWidgets()
    {
        return $this->widgets;
    }

    /**
     *    Accessor for an attribute.
     *
     * @param string $label attribute name
     *
     * @return bool always false
     */
    public function getAttribute($label)
    {
        return false;
    }

    /**
     *    Fetches the name for the widget from the first
     *    member.
     *
     * @return string name of widget
     */
    public function getName()
    {
        if (count($this->widgets) > 0) {
            return $this->widgets[0]->getName();
        }
    }

    /**
     *    Scans the widgets for one with the appropriate
     *    ID field.
     *
     * @param string $id ID value to try
     *
     * @return bool true if matched
     */
    public function isId($id)
    {
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            if ($this->widgets[$i]->isId($id)) {
                return true;
            }
        }
        return false;
    }

    /**
     *    Scans the widgets for one with the appropriate
     *    attached label.
     *
     * @param string $label attached label to try
     *
     * @return bool true if matched
     */
    public function isLabel($label)
    {
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            if ($this->widgets[$i]->isLabel($label)) {
                return true;
            }
        }
        return false;
    }

    /**
     *    Dispatches the value into the form encoded packet.
     *
     * @param SimpleEncoding $encoding form packet
     */
    public function write($encoding)
    {
        $encoding->add($this->getName(), $this->getValue());
    }
}

/**
 *    A group of tags with the same name within a form.
 */
class SimpleCheckboxGroup extends SimpleTagGroup
{
    /**
     *    Accessor for current selected widget or false
     *    if none.
     *
     * @return string/array     Widget values or false if none
     */
    public function getValue()
    {
        $values = [];
        $widgets = $this->getWidgets();
        for ($i = 0, $count = count($widgets); $i < $count; $i++) {
            if ($widgets[$i]->getValue() !== false) {
                $values[] = $widgets[$i]->getValue();
            }
        }
        return $this->coerceValues($values);
    }

    /**
     *    Accessor for starting value that is active.
     *
     * @return string/array      Widget values or false if none
     */
    public function getDefault()
    {
        $values = [];
        $widgets = $this->getWidgets();
        for ($i = 0, $count = count($widgets); $i < $count; $i++) {
            if ($widgets[$i]->getDefault() !== false) {
                $values[] = $widgets[$i]->getDefault();
            }
        }
        return $this->coerceValues($values);
    }

    /**
     *    Accessor for current set values.
     *
     * @param string /array/boolean $values   Either a single string, a
     *                                          hash or false for nothing set
     *
     * @return bool true if all values can be set
     */
    public function setValue($values)
    {
        $values = $this->makeArray($values);
        if (!$this->valuesArePossible($values)) {
            return false;
        }
        $widgets = $this->getWidgets();
        for ($i = 0, $count = count($widgets); $i < $count; $i++) {
            $possible = $widgets[$i]->getAttribute('value');
            if (in_array($widgets[$i]->getAttribute('value'), $values)) {
                $widgets[$i]->setValue($possible);
            } else {
                $widgets[$i]->setValue(false);
            }
        }
        return true;
    }

    /**
     *    Tests to see if a possible value set is legal.
     *
     * @param string /array/boolean $values   Either a single string, a
     *                                          hash or false for nothing set
     *
     * @return bool false if trying to set a
     *              missing value
     */
    protected function valuesArePossible($values)
    {
        $matches = [];
        $widgets = &$this->getWidgets();
        for ($i = 0, $count = count($widgets); $i < $count; $i++) {
            $possible = $widgets[$i]->getAttribute('value');
            if (in_array($possible, $values)) {
                $matches[] = $possible;
            }
        }
        return $values == $matches;
    }

    /**
     *    Converts the output to an appropriate format. This means
     *    that no values is false, a single value is just that
     *    value and only two or more are contained in an array.
     *
     * @param array $values list of values of widgets
     *
     * @return string/array/boolean   Expected format for a tag
     */
    protected function coerceValues($values)
    {
        if (count($values) == 0) {
            return false;
        } elseif (count($values) == 1) {
            return $values[0];
        } else {
            return $values;
        }
    }

    /**
     *    Converts false or string into array. The opposite of
     *    the coercian method.
     *
     * @param string /array/boolean $value  A single item is converted
     *                                        to a one item list. False
     *                                        gives an empty list.
     *
     * @return array list of values, possibly empty
     */
    protected function makeArray($value)
    {
        if ($value === false) {
            return [];
        }
        if (is_string($value)) {
            return [$value];
        }
        return $value;
    }
}

/**
 *    A group of tags with the same name within a form.
 *    Used for radio buttons.
 */
class SimpleRadioGroup extends SimpleTagGroup
{
    /**
     *    Each tag is tried in turn until one is
     *    successfully set. The others will be
     *    unchecked if successful.
     *
     * @param string $value new value
     *
     * @return bool true if any allowed
     */
    public function setValue($value)
    {
        if (!$this->valueIsPossible($value)) {
            return false;
        }
        $index = false;
        $widgets = $this->getWidgets();
        for ($i = 0, $count = count($widgets); $i < $count; $i++) {
            if (!$widgets[$i]->setValue($value)) {
                $widgets[$i]->setValue(false);
            }
        }
        return true;
    }

    /**
     *    Tests to see if a value is allowed.
     *
     * @param string    attempted value
     *
     * @return bool true if a valid value
     */
    protected function valueIsPossible($value)
    {
        $widgets = $this->getWidgets();
        for ($i = 0, $count = count($widgets); $i < $count; $i++) {
            if ($widgets[$i]->getAttribute('value') == $value) {
                return true;
            }
        }
        return false;
    }

    /**
     *    Accessor for current selected widget or false
     *    if none.
     *
     * @return string/boolean   Value attribute or
     *                             content of opton
     */
    public function getValue()
    {
        $widgets = $this->getWidgets();
        for ($i = 0, $count = count($widgets); $i < $count; $i++) {
            if ($widgets[$i]->getValue() !== false) {
                return $widgets[$i]->getValue();
            }
        }
        return false;
    }

    /**
     *    Accessor for starting value that is active.
     *
     * @return string/boolean      Value of first checked
     *                                widget or false if none
     */
    public function getDefault()
    {
        $widgets = $this->getWidgets();
        for ($i = 0, $count = count($widgets); $i < $count; $i++) {
            if ($widgets[$i]->getDefault() !== false) {
                return $widgets[$i]->getDefault();
            }
        }
        return false;
    }
}

/**
 *    Tag to keep track of labels.
 */
class SimpleLabelTag extends SimpleTag
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('label', $attributes);
    }

    /**
     *    Access for the ID to attach the label to.
     *
     * @return string for attribute
     */
    public function getFor()
    {
        return $this->getAttribute('for');
    }
}

/**
 *    Tag to aid parsing the form.
 */
class SimpleFormTag extends SimpleTag
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('form', $attributes);
    }
}

/**
 *    Tag to aid parsing the frames in a page.
 */
class SimpleFrameTag extends SimpleTag
{
    /**
     *    Starts with a named tag with attributes only.
     *
     * @param hash $attributes attribute names and
     *                         string values
     */
    public function __construct($attributes)
    {
        parent::__construct('frame', $attributes);
    }

    /**
     *    Tag contains no content.
     *
     * @return bool false
     */
    public function expectEndTag()
    {
        return false;
    }
}
