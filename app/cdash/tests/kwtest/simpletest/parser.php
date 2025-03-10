<?php

/**
 *  base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 * Lexer mode stack constants
 */
foreach (['LEXER_ENTER', 'LEXER_MATCHED',
    'LEXER_UNMATCHED', 'LEXER_EXIT',
    'LEXER_SPECIAL'] as $i => $constant) {
    if (!defined($constant)) {
        define($constant, $i + 1);
    }
}
/**#@-*/

/**
 *    Compounded regular expression. Any of
 *    the contained patterns could match and
 *    when one does, it's label is returned.
 */
class ParallelRegex
{
    public $_patterns;
    public $_labels;
    public $_regex;
    public $_case;

    /**
     *    Constructor. Starts with no patterns.
     *
     * @param bool $case true for case sensitive, false
     *                   for insensitive
     */
    public function ParallelRegex($case)
    {
        $this->_case = $case;
        $this->_patterns = [];
        $this->_labels = [];
        $this->_regex = null;
    }

    /**
     *    Adds a pattern with an optional label.
     *
     * @param string $pattern perl style regex, but ( and )
     *                        lose the usual meaning
     * @param string $label label of regex to be returned
     *                      on a match
     */
    public function addPattern($pattern, $label = true)
    {
        $count = count($this->_patterns);
        $this->_patterns[$count] = $pattern;
        $this->_labels[$count] = $label;
        $this->_regex = null;
    }

    /**
     *    Attempts to match all patterns at once against
     *    a string.
     *
     * @param string $subject string to match against
     * @param string $match first matched portion of
     *                      subject
     *
     * @return bool true on success
     */
    public function match($subject, &$match)
    {
        if (count($this->_patterns) == 0) {
            return false;
        }
        if (!preg_match($this->_getCompoundedRegex(), $subject, $matches)) {
            $match = '';
            return false;
        }
        $match = $matches[0];
        for ($i = 1; $i < count($matches); $i++) {
            if ($matches[$i]) {
                return $this->_labels[$i - 1];
            }
        }
        return true;
    }

    /**
     *    Compounds the patterns into a single
     *    regular expression separated with the
     *    "or" operator. Caches the regex.
     *    Will automatically escape (, ) and / tokens.
     */
    public function _getCompoundedRegex()
    {
        if ($this->_regex == null) {
            for ($i = 0, $count = count($this->_patterns); $i < $count; $i++) {
                $this->_patterns[$i] = '(' . str_replace(
                    ['/', '(', ')'],
                    ['\/', '\(', '\)'],
                    $this->_patterns[$i]) . ')';
            }
            $this->_regex = '/' . implode('|', $this->_patterns) . '/' . $this->_getPerlMatchingFlags();
        }
        return $this->_regex;
    }

    /**
     *    Accessor for perl regex mode flags to use.
     *
     * @return string perl regex flags
     */
    public function _getPerlMatchingFlags()
    {
        return $this->_case ? 'msS' : 'msSi';
    }
}

/**
 *    States for a stack machine.
 */
class SimpleStateStack
{
    public $_stack;

    /**
     *    Constructor. Starts in named state.
     *
     * @param string $start starting state name
     */
    public function SimpleStateStack($start)
    {
        $this->_stack = [$start];
    }

    /**
     *    Accessor for current state.
     *
     * @return string state
     */
    public function getCurrent()
    {
        return $this->_stack[count($this->_stack) - 1];
    }

    /**
     *    Adds a state to the stack and sets it
     *    to be the current state.
     *
     * @param string $state new state
     */
    public function enter($state)
    {
        array_push($this->_stack, $state);
    }

    /**
     *    Leaves the current state and reverts
     *    to the previous one.
     *
     * @return bool false if we drop off
     *              the bottom of the list
     */
    public function leave()
    {
        if (count($this->_stack) == 1) {
            return false;
        }
        array_pop($this->_stack);
        return true;
    }
}

/**
 *    Accepts text and breaks it into tokens.
 *    Some optimisation to make the sure the
 *    content is only scanned by the PHP regex
 *    parser once. Lexer modes must not start
 *    with leading underscores.
 */
class SimpleLexer
{
    public $_regexes;
    public $_parser;
    public $_mode;
    public $_mode_handlers;
    public $_case;

    /**
     *    Sets up the lexer in case insensitive matching
     *    by default.
     *
     * @param SimpleSaxParser $parser handling strategy by
     *                                reference
     * @param string $start starting handler
     * @param bool $case true for case sensitive
     */
    public function SimpleLexer(&$parser, $start = 'accept', $case = false)
    {
        $this->_case = $case;
        $this->_regexes = [];
        $this->_parser = &$parser;
        $this->_mode = new SimpleStateStack($start);
        $this->_mode_handlers = [$start => $start];
    }

    /**
     *    Adds a token search pattern for a particular
     *    parsing mode. The pattern does not change the
     *    current mode.
     *
     * @param string $pattern perl style regex, but ( and )
     *                        lose the usual meaning
     * @param string $mode should only apply this
     *                     pattern when dealing with
     *                     this type of input
     */
    public function addPattern($pattern, $mode = 'accept')
    {
        if (!isset($this->_regexes[$mode])) {
            $this->_regexes[$mode] = new ParallelRegex($this->_case);
        }
        $this->_regexes[$mode]->addPattern($pattern);
        if (!isset($this->_mode_handlers[$mode])) {
            $this->_mode_handlers[$mode] = $mode;
        }
    }

    /**
     *    Adds a pattern that will enter a new parsing
     *    mode. Useful for entering parenthesis, strings,
     *    tags, etc.
     *
     * @param string $pattern perl style regex, but ( and )
     *                        lose the usual meaning
     * @param string $mode should only apply this
     *                     pattern when dealing with
     *                     this type of input
     * @param string $new_mode change parsing to this new
     *                         nested mode
     */
    public function addEntryPattern($pattern, $mode, $new_mode)
    {
        if (!isset($this->_regexes[$mode])) {
            $this->_regexes[$mode] = new ParallelRegex($this->_case);
        }
        $this->_regexes[$mode]->addPattern($pattern, $new_mode);
        if (!isset($this->_mode_handlers[$new_mode])) {
            $this->_mode_handlers[$new_mode] = $new_mode;
        }
    }

    /**
     *    Adds a pattern that will exit the current mode
     *    and re-enter the previous one.
     *
     * @param string $pattern perl style regex, but ( and )
     *                        lose the usual meaning
     * @param string $mode mode to leave
     */
    public function addExitPattern($pattern, $mode)
    {
        if (!isset($this->_regexes[$mode])) {
            $this->_regexes[$mode] = new ParallelRegex($this->_case);
        }
        $this->_regexes[$mode]->addPattern($pattern, '__exit');
        if (!isset($this->_mode_handlers[$mode])) {
            $this->_mode_handlers[$mode] = $mode;
        }
    }

    /**
     *    Adds a pattern that has a special mode. Acts as an entry
     *    and exit pattern in one go, effectively calling a special
     *    parser handler for this token only.
     *
     * @param string $pattern perl style regex, but ( and )
     *                        lose the usual meaning
     * @param string $mode should only apply this
     *                     pattern when dealing with
     *                     this type of input
     * @param string $special use this mode for this one token
     */
    public function addSpecialPattern($pattern, $mode, $special)
    {
        if (!isset($this->_regexes[$mode])) {
            $this->_regexes[$mode] = new ParallelRegex($this->_case);
        }
        $this->_regexes[$mode]->addPattern($pattern, "_$special");
        if (!isset($this->_mode_handlers[$special])) {
            $this->_mode_handlers[$special] = $special;
        }
    }

    /**
     *    Adds a mapping from a mode to another handler.
     *
     * @param string $mode mode to be remapped
     * @param string $handler new target handler
     */
    public function mapHandler($mode, $handler)
    {
        $this->_mode_handlers[$mode] = $handler;
    }

    /**
     *    Splits the page text into tokens. Will fail
     *    if the handlers report an error or if no
     *    content is consumed. If successful then each
     *    unparsed and parsed token invokes a call to the
     *    held listener.
     *
     * @param string $raw raw HTML text
     *
     * @return bool true on success, else false
     */
    public function parse($raw)
    {
        if (!isset($this->_parser)) {
            return false;
        }
        $length = strlen($raw);
        while (is_array($parsed = $this->_reduce($raw))) {
            [$raw, $unmatched, $matched, $mode] = $parsed;
            if (!$this->_dispatchTokens($unmatched, $matched, $mode)) {
                return false;
            }
            if ($raw === '') {
                return true;
            }
            if (strlen($raw) == $length) {
                return false;
            }
            $length = strlen($raw);
        }
        if (!$parsed) {
            return false;
        }
        return $this->_invokeParser($raw, LEXER_UNMATCHED);
    }

    /**
     *    Sends the matched token and any leading unmatched
     *    text to the parser changing the lexer to a new
     *    mode if one is listed.
     *
     * @param string $unmatched unmatched leading portion
     * @param string $matched actual token match
     * @param string $mode Mode after match. A boolean
     *                     false mode causes no change.
     *
     * @return bool false if there was any error
     *              from the parser
     */
    public function _dispatchTokens($unmatched, $matched, $mode = false)
    {
        if (!$this->_invokeParser($unmatched, LEXER_UNMATCHED)) {
            return false;
        }
        if (is_bool($mode)) {
            return $this->_invokeParser($matched, LEXER_MATCHED);
        }
        if ($this->_isModeEnd($mode)) {
            if (!$this->_invokeParser($matched, LEXER_EXIT)) {
                return false;
            }
            return $this->_mode->leave();
        }
        if ($this->_isSpecialMode($mode)) {
            $this->_mode->enter($this->_decodeSpecial($mode));
            if (!$this->_invokeParser($matched, LEXER_SPECIAL)) {
                return false;
            }
            return $this->_mode->leave();
        }
        $this->_mode->enter($mode);
        return $this->_invokeParser($matched, LEXER_ENTER);
    }

    /**
     *    Tests to see if the new mode is actually to leave
     *    the current mode and pop an item from the matching
     *    mode stack.
     *
     * @param string $mode mode to test
     *
     * @return bool true if this is the exit mode
     */
    public function _isModeEnd($mode)
    {
        return $mode === '__exit';
    }

    /**
     *    Test to see if the mode is one where this mode
     *    is entered for this token only and automatically
     *    leaves immediately afterwoods.
     *
     * @param string $mode mode to test
     *
     * @return bool true if this is the exit mode
     */
    public function _isSpecialMode($mode)
    {
        return strncmp($mode, '_', 1) == 0;
    }

    /**
     *    Strips the magic underscore marking single token
     *    modes.
     *
     * @param string $mode mode to decode
     *
     * @return string underlying mode name
     */
    public function _decodeSpecial($mode)
    {
        return substr($mode, 1);
    }

    /**
     *    Calls the parser method named after the current
     *    mode. Empty content will be ignored. The lexer
     *    has a parser handler for each mode in the lexer.
     *
     * @param string $content text parsed
     * @param bool $is_match token is recognised rather
     *                       than unparsed data
     */
    public function _invokeParser($content, $is_match)
    {
        if (($content === '') || ($content === false)) {
            return true;
        }
        $handler = $this->_mode_handlers[$this->_mode->getCurrent()];
        return $this->_parser->$handler($content, $is_match);
    }

    /**
     *    Tries to match a chunk of text and if successful
     *    removes the recognised chunk and any leading
     *    unparsed data. Empty strings will not be matched.
     *
     * @param string $raw The subject to parse. This is the
     *                    content that will be eaten.
     *
     * @return array/boolean      Three item list of unparsed
     *                               content followed by the
     *                               recognised token and finally the
     *                               action the parser is to take.
     *                               True if no match, false if there
     *                               is a parsing error.
     */
    public function _reduce($raw)
    {
        if ($action = $this->_regexes[$this->_mode->getCurrent()]->match($raw, $match)) {
            $unparsed_character_count = strpos($raw, $match);
            $unparsed = substr($raw, 0, $unparsed_character_count);
            $raw = substr($raw, $unparsed_character_count + strlen($match));
            return [$raw, $unparsed, $match, $action];
        }
        return true;
    }
}

/**
 *    Breaks HTML into SAX events.
 */
class SimpleHtmlLexer extends SimpleLexer
{
    /**
     *    Sets up the lexer with case insensitive matching
     *    and adds the HTML handlers.
     *
     * @param SimpleSaxParser $parser handling strategy by
     *                                reference
     */
    public function SimpleHtmlLexer(&$parser)
    {
        $this->SimpleLexer($parser, 'text');
        $this->mapHandler('text', 'acceptTextToken');
        $this->_addSkipping();
        foreach ($this->_getParsedTags() as $tag) {
            $this->_addTag($tag);
        }
        $this->_addInTagTokens();
    }

    /**
     *    List of parsed tags. Others are ignored.
     *
     * @return array list of searched for tags
     */
    public function _getParsedTags()
    {
        return ['a', 'base', 'title', 'form', 'input', 'button', 'textarea', 'select',
            'option', 'frameset', 'frame', 'label'];
    }

    /**
     *    The lexer has to skip certain sections such
     *    as server code, client code and styles.
     */
    public function _addSkipping()
    {
        $this->mapHandler('css', 'ignore');
        $this->addEntryPattern('<style', 'text', 'css');
        $this->addExitPattern('</style>', 'css');
        $this->mapHandler('js', 'ignore');
        $this->addEntryPattern('<script', 'text', 'js');
        $this->addExitPattern('</script>', 'js');
        $this->mapHandler('comment', 'ignore');
        $this->addEntryPattern('<!--', 'text', 'comment');
        $this->addExitPattern('-->', 'comment');
    }

    /**
     *    Pattern matches to start and end a tag.
     *
     * @param string $tag name of tag to scan for
     */
    public function _addTag($tag)
    {
        $this->addSpecialPattern("</$tag>", 'text', 'acceptEndToken');
        $this->addEntryPattern("<$tag", 'text', 'tag');
    }

    /**
     *    Pattern matches to parse the inside of a tag
     *    including the attributes and their quoting.
     */
    public function _addInTagTokens()
    {
        $this->mapHandler('tag', 'acceptStartToken');
        $this->addSpecialPattern('\s+', 'tag', 'ignore');
        $this->_addAttributeTokens();
        $this->addExitPattern('/>', 'tag');
        $this->addExitPattern('>', 'tag');
    }

    /**
     *    Matches attributes that are either single quoted,
     *    double quoted or unquoted.
     */
    public function _addAttributeTokens()
    {
        $this->mapHandler('dq_attribute', 'acceptAttributeToken');
        $this->addEntryPattern('=\s*"', 'tag', 'dq_attribute');
        $this->addPattern('\\\\"', 'dq_attribute');
        $this->addExitPattern('"', 'dq_attribute');
        $this->mapHandler('sq_attribute', 'acceptAttributeToken');
        $this->addEntryPattern("=\s*'", 'tag', 'sq_attribute');
        $this->addPattern("\\\\'", 'sq_attribute');
        $this->addExitPattern("'", 'sq_attribute');
        $this->mapHandler('uq_attribute', 'acceptAttributeToken');
        $this->addSpecialPattern('=\s*[^>\s]*', 'tag', 'uq_attribute');
    }
}

/**
 *    Converts HTML tokens into selected SAX events.
 */
class SimpleHtmlSaxParser
{
    public $_lexer;
    public $_listener;
    public $_tag;
    public $_attributes;
    public $_current_attribute;

    /**
     *    Sets the listener.
     *
     * @param simpleSaxListener $listener SAX event handler
     */
    public function SimpleHtmlSaxParser(&$listener)
    {
        $this->_listener = &$listener;
        $this->_lexer = &$this->createLexer($this);
        $this->_tag = '';
        $this->_attributes = [];
        $this->_current_attribute = '';
    }

    /**
     *    Runs the content through the lexer which
     *    should call back to the acceptors.
     *
     * @param string $raw page text to parse
     *
     * @return bool false if parse error
     */
    public function parse($raw)
    {
        return $this->_lexer->parse($raw);
    }

    /**
     *    Sets up the matching lexer. Starts in 'text' mode.
     *
     * @param SimpleSaxParser $parser event generator, usually $self
     *
     * @return SimpleLexer lexer suitable for this parser
     *
     * @static
     */
    public function &createLexer(&$parser)
    {
        $lexer = new SimpleHtmlLexer($parser);
        return $lexer;
    }

    /**
     *    Accepts a token from the tag mode. If the
     *    starting element completes then the element
     *    is dispatched and the current attributes
     *    set back to empty. The element or attribute
     *    name is converted to lower case.
     *
     * @param string $token incoming characters
     * @param int $event lexer event type
     *
     * @return bool false if parse error
     */
    public function acceptStartToken($token, $event)
    {
        if ($event == LEXER_ENTER) {
            $this->_tag = strtolower(substr($token, 1));
            return true;
        }
        if ($event == LEXER_EXIT) {
            $success = $this->_listener->startElement(
                $this->_tag,
                $this->_attributes);
            $this->_tag = '';
            $this->_attributes = [];
            return $success;
        }
        if ($token != '=') {
            $this->_current_attribute = strtolower(SimpleHtmlSaxParser::decodeHtml($token));
            $this->_attributes[$this->_current_attribute] = '';
        }
        return true;
    }

    /**
     *    Accepts a token from the end tag mode.
     *    The element name is converted to lower case.
     *
     * @param string $token incoming characters
     * @param int $event lexer event type
     *
     * @return bool false if parse error
     */
    public function acceptEndToken($token, $event)
    {
        if (!preg_match('/<\/(.*)>/', $token, $matches)) {
            return false;
        }
        return $this->_listener->endElement(strtolower($matches[1]));
    }

    /**
     *    Part of the tag data.
     *
     * @param string $token incoming characters
     * @param int $event lexer event type
     *
     * @return bool false if parse error
     */
    public function acceptAttributeToken($token, $event)
    {
        if ($this->_current_attribute) {
            if ($event == LEXER_UNMATCHED) {
                $this->_attributes[$this->_current_attribute] .=
                    SimpleHtmlSaxParser::decodeHtml($token);
            }
            if ($event == LEXER_SPECIAL) {
                $this->_attributes[$this->_current_attribute] .=
                    preg_replace('/^=\s*/', '', SimpleHtmlSaxParser::decodeHtml($token));
            }
        }
        return true;
    }

    /**
     *    A character entity.
     *
     * @param string $token incoming characters
     * @param int $event lexer event type
     *
     * @return bool false if parse error
     */
    public function acceptEntityToken($token, $event)
    {
    }

    /**
     *    Character data between tags regarded as
     *    important.
     *
     * @param string $token incoming characters
     * @param int $event lexer event type
     *
     * @return bool false if parse error
     */
    public function acceptTextToken($token, $event)
    {
        return $this->_listener->addContent($token);
    }

    /**
     *    Incoming data to be ignored.
     *
     * @param string $token incoming characters
     * @param int $event lexer event type
     *
     * @return bool false if parse error
     */
    public function ignore($token, $event)
    {
        return true;
    }

    /**
     *    Decodes any HTML entities.
     *
     * @param string $html incoming HTML
     *
     * @return string outgoing plain text
     *
     * @static
     */
    public function decodeHtml($html)
    {
        return html_entity_decode($html, ENT_QUOTES);
    }

    /**
     *    Turns HTML into text browser visible text. Images
     *    are converted to their alt text and tags are supressed.
     *    Entities are converted to their visible representation.
     *
     * @param string $html HTML to convert
     *
     * @return string plain text
     *
     * @static
     */
    public function normalise($html)
    {
        $text = preg_replace('|<!--.*?-->|', '', $html);
        $text = preg_replace('|<script[^>]*>.*?</script>|', '', $text);
        $text = preg_replace('|<img[^>]*alt\s*=\s*"([^"]*)"[^>]*>|', ' \1 ', $text);
        $text = preg_replace('|<img[^>]*alt\s*=\s*\'([^\']*)\'[^>]*>|', ' \1 ', $text);
        $text = preg_replace('|<img[^>]*alt\s*=\s*([a-zA-Z_]+)[^>]*>|', ' \1 ', $text);
        $text = preg_replace('|<[^>]*>|', '', $text);
        $text = SimpleHtmlSaxParser::decodeHtml($text);
        $text = preg_replace('|\s+|', ' ', $text);
        return trim(trim($text), "\xA0");        // TODO: The \xAO is a &nbsp;. Add a test for this.
    }
}

/**
 *    SAX event handler.
 *
 * @abstract
 */
class SimpleSaxListener
{
    /**
     *    Sets the document to write to.
     */
    public function SimpleSaxListener()
    {
    }

    /**
     *    Start of element event.
     *
     * @param string $name element name
     * @param hash $attributes Name value pairs.
     *                         Attributes without content
     *                         are marked as true.
     *
     * @return bool false on parse error
     */
    public function startElement($name, $attributes)
    {
    }

    /**
     *    End of element event.
     *
     * @param string $name element name
     *
     * @return bool false on parse error
     */
    public function endElement($name)
    {
    }

    /**
     *    Unparsed, but relevant data.
     *
     * @param string $text may include unparsed tags
     *
     * @return bool false on parse error
     */
    public function addContent($text)
    {
    }
}
