<?php

/**
 * Copyright 2015-2016 DataStax, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Cassandra;

/**
 * Datatype integration tests.
 */
class DatatypeIntegrationTest extends BasicIntegrationTest {
    /**
     * Ensure Decimal/Varint encoding on byte boundaries
     *
     * This test will ensure that the PHP driver is properly encoding Decimal
     * and Varint datatypes for positive values with leading 1's that land on
     * a byte boundary.
     *
     * @test
     * @ticket PHP-70
     */
    public function testByteBoundaryDecimalVarint() {
        // Create the table
        $query = "CREATE TABLE {$this->tableNamePrefix} (key timeuuid PRIMARY KEY, value_decimal decimal, value_varint varint)";
        $this->session->execute(new SimpleStatement($query));

        // Iterate through a few byte boundary positive values
        foreach (range(1, 20) as $i) {
            // Assign the values for the statement
            $key = new Timeuuid();
            $value_varint = pow(2, (8 * $i)) - 1;
            $value_decimal = $value_varint / 100;
            $values = array(
                $key,
                new Decimal($value_decimal),
                new Varint($value_varint)
            );

            // Insert the value into the table
            $query = "INSERT INTO {$this->tableNamePrefix} (key, value_decimal, value_varint) VALUES (?, ?, ?)";
            $statement = new SimpleStatement($query);
            $options = new ExecutionOptions(array("arguments" => $values));
            $this->session->execute($statement, $options);

            // Select the decimal and varint
            $query = "SELECT value_decimal, value_varint FROM {$this->tableNamePrefix} WHERE key=?";
            $statement = new SimpleStatement($query);
            $options = new ExecutionOptions(array("arguments" => array($key)));
            $rows = $this->session->execute($statement, $options);

            // Ensure the decimal and varint are valid
            $this->assertCount(1, $rows);
            $row = $rows->first();
            $this->assertNotNull($row);
            $this->assertArrayHasKey("value_decimal", $row);
            $this->assertEquals($values[1], $row["value_decimal"]);
            $this->assertArrayHasKey("value_varint", $row);
            $this->assertEquals($values[2], $row["value_varint"]);
        }
    }

    /**
     * @test
     * @ticket PHP-63
     */
    public function testSupportsSmallint() {
        // Create the table
        $query = "CREATE TABLE {$this->tableNamePrefix} (value_smallint smallint PRIMARY KEY)";
        $this->session->execute(new SimpleStatement($query));
        $statement = $this->session->prepare("INSERT INTO {$this->tableNamePrefix} (value_smallint) VALUES (?)");

        $futures = array();
        foreach (range(Smallint::min(), Smallint::max()) as $value) {
            $futures[] = $this->session->executeAsync($statement, new ExecutionOptions(array("arguments" => array(new Smallint($value)))));
        }
        foreach ($futures as $future) {
            $future->get();
        }
        unset($futures);

        $rows = $this->session->execute(new SimpleStatement("SELECT * FROM {$this->tableNamePrefix}"));
        $this->assertCount(65536, $rows);
        $row = $rows->first();
        $this->assertNotNull($row);
        $this->assertEquals(new Smallint(-1), $row["value_smallint"]);
    }
}
