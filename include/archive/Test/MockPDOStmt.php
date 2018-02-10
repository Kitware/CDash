<?php
namespace CDash\Test;

class MockPDOStmt extends \PDOStatement
{
    private $queries = [];

    public function mockFetchCall($query, $result)
    {
        $this->queries[$query] = $result;
    }
}
