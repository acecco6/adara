<?php

namespace Tests\Unit;

use App\Support\Database\PortableTrigger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PortableTriggerTest extends TestCase
{
    public function test_sqlite_trigger_definition_is_returned_unchanged(): void
    {
        $definition = "CREATE TRIGGER catalog_valid BEFORE INSERT ON products WHEN NEW.name = '' BEGIN SELECT RAISE(ABORT, 'invalid'); END";

        $this->assertSame($definition, PortableTrigger::compile($definition, 'sqlite'));
    }

    public function test_mysql_trigger_uses_signal_and_native_integer_columns(): void
    {
        $definition = "CREATE TRIGGER catalog_valid BEFORE INSERT ON products WHEN typeof(NEW.minimum_stock) <> 'integer' OR NEW.minimum_stock < 0 BEGIN SELECT RAISE(ABORT, 'invalid product'); END";

        $compiled = PortableTrigger::compile($definition, 'mysql');

        $this->assertStringContainsString('FOR EACH ROW BEGIN IF (FALSE OR NEW.minimum_stock < 0)', $compiled);
        $this->assertStringContainsString("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invalid product'", $compiled);
        $this->assertStringNotContainsString('RAISE(ABORT', $compiled);
        $this->assertStringNotContainsString('typeof(', $compiled);
    }

    public function test_mysql_trigger_translates_null_safe_immutability_checks(): void
    {
        $definition = "CREATE TRIGGER sales_immutable BEFORE UPDATE ON sales WHEN NEW.number IS NOT OLD.number BEGIN SELECT RAISE(ABORT, 'immutable'); END";

        $compiled = PortableTrigger::compile($definition, 'mysql');

        $this->assertStringContainsString('NOT (NEW.number <=> OLD.number)', $compiled);
    }

    public function test_mysql_trigger_translates_update_of_into_a_changed_column_guard(): void
    {
        $definition = "CREATE TRIGGER sale_status BEFORE UPDATE OF status ON sales WHEN OLD.status <> 'confirmed' OR NEW.status <> 'cancelled' BEGIN SELECT RAISE(ABORT, 'invalid transition'); END";

        $compiled = PortableTrigger::compile($definition, 'mysql');

        $this->assertStringContainsString('BEFORE UPDATE ON `sales`', $compiled);
        $this->assertStringContainsString('NOT (OLD.status <=> NEW.status)', $compiled);
    }

    public function test_mysql_trigger_preserves_non_validation_actions(): void
    {
        $definition = 'CREATE TRIGGER product_initial_balance AFTER INSERT ON products BEGIN INSERT INTO balances (product_id, quantity) VALUES (NEW.id, 0); END';

        $this->assertStringContainsString(
            'BEGIN INSERT INTO balances (product_id, quantity) VALUES (NEW.id, 0); END',
            PortableTrigger::compile($definition, 'mysql'),
        );
    }

    public function test_unknown_driver_and_invalid_definition_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        PortableTrigger::compile('CREATE TRIGGER trigger_name', 'pgsql');
    }

    public function test_malformed_mysql_trigger_definition_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PortableTrigger::compile('CREATE TRIGGER trigger_name', 'mysql');
    }
}
