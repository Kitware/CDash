<?php

namespace App\Database\Query\Grammars;

class PostgresGrammar extends \Illuminate\Database\Query\Grammars\PostgresGrammar
{
    public function getDateFormat()
    {
        return 'Y-m-d H:i:s.u';
    }
}
