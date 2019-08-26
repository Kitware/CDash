<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$
  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.
  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

namespace App\Validators;

use Illuminate\Validation\Validator;

/**
 * Class Password
 * @package App\Validators
 */
class Password
{
    /**
     * @param $attribute
     * @param $value
     * @param $parameters
     * @param Validator $validator
     * @return bool
     */
    public function complexity($attribute, $value, $parameters, $validator)
    {
        if (empty($value)) {
            return false;
        }

        // $parameters are passed in by index in the order they were
        // defined, therefore parameter[0] is our complexity and
        // parameter[1] is our count
        $args = [];
        if (isset($parameters[0]) && is_numeric($parameters[0])) {
            $args['complexity'] = (int)$parameters[0];
        }

        if (isset($parameters[1]) && is_numeric($parameters[1])) {
            $args['count'] = (int)$parameters[1];
        }

        $config = $this->getComplexityConfiguration($args);

        if ($config['complexity'] == 0 || $config['count'] == 0) {
            // both of these situations negate the necessity to call computeComplexity
            return true;
        }

        $complexity = $this->computeComplexity($value, $config['count']);
        $success = $config['complexity'] <= $complexity;

        if (!$success) {
            $this->setCustomMessages($config, $validator);
        }

        return $success;
    }

    /**
     * @param $password
     * @param $count
     * @return int
     */
    protected function computeComplexity($password, $count)
    {
        $complexity = 0;

        // Uppercase letters
        $num_uppercase = preg_match_all('/[A-Z]/', $password);
        if ($num_uppercase >= $count) {
            $complexity++;
        }

        // Lowercase letters
        $num_lowercase = preg_match_all('/[a-z]/', $password);
        if ($num_lowercase >= $count) {
            $complexity++;
        }

        // Numbers
        $num_numbers = preg_match_all('/[0-9]/', $password);
        if ($num_numbers >= $count) {
            $complexity++;
        }

        // Symbols
        $num_symbols = preg_match_all("/\W/", $password);
        // Underscore is not matched by \W but we consider it a symbol.
        $num_symbols += substr_count($password, '_');
        if ($num_symbols >= $count) {
            $complexity++;
        }

        return $complexity;
    }

    /**
     * @param array $config
     * @param Validator $validator
     */
    protected function setCustomMessages($config, $validator)
    {
        // TODO: consider changing this output
        // explanation: if $complexity = 1 but the message has failed due to
        //              $count being greater than the length of the user input
        //              this message will read "... 1 of the following types: ..."
        //              Might be better just to say, "...password must be at least n
        //              characters long"
        $tmpl = "Your :attribute must contain %s %s of the following types: "
            . "uppercase, lowercase, numbers, and symbols";

        $complexity = $config['complexity'];
        $quantifier = 'at least';

        // since the number of character classes is *not* bound to change this should
        // be okay to hardcode
        if ($complexity == 4) {
            $complexity = "each";
            if (!($config['count'] > 1)) {
                $quantifier = '';
            }
        }

        if ($config['count'] > 1) {
            $var = "{$config['count']} characters from {$complexity}";
        } else {
            $var = $complexity;
        }

        $msg = sprintf($tmpl, $quantifier, $var);

        $validator->setCustomMessages(['password.complexity' => $msg]);
    }

    /**
     * @param array $parameters
     * @return array
     */
    public function getComplexityConfiguration($parameters = [])
    {
        $config = [
            'complexity' => 0,
            'count' => 0,
        ];

        return array_merge($config, config('cdash.password'), $parameters);
    }
}
