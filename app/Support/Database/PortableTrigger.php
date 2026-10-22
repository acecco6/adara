<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class PortableTrigger
{
    public static function create(string $definition): void
    {
        $connection = DB::connection();
        $connection->statement(self::compile($definition, $connection->getDriverName()));
    }

    public static function compile(string $definition, string $driver): string
    {
        if ($driver === 'sqlite') {
            return $definition;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Database triggers are not configured for the [{$driver}] driver.");
        }

        $pattern = '/^CREATE\s+TRIGGER\s+([a-zA-Z0-9_]+)\s+(BEFORE|AFTER)\s+(INSERT|UPDATE(?:\s+OF\s+[a-zA-Z0-9_,\s]+)?|DELETE)\s+ON\s+([a-zA-Z0-9_]+)\s*(?:WHEN\s+(.*?))?\s+BEGIN\s+(.*?)\s+END\s*;?$/is';

        if (preg_match($pattern, trim($definition), $matches) !== 1) {
            throw new InvalidArgumentException('The SQLite trigger definition could not be parsed.');
        }

        [, $name, $timing, $event, $table, $condition, $body] = $matches;
        $updateOfColumn = null;

        if (preg_match('/^UPDATE\s+OF\s+([a-zA-Z0-9_]+)/i', $event, $updateOf) === 1) {
            $updateOfColumn = $updateOf[1];
        }

        $event = strtoupper(strtok($event, " \t\r\n"));
        $condition = trim((string) $condition);
        $body = trim($body);

        if ($condition !== '') {
            $condition = self::translateCondition($condition);

            if ($updateOfColumn !== null) {
                $condition = "NOT (OLD.{$updateOfColumn} <=> NEW.{$updateOfColumn}) AND ({$condition})";
            }

            if (preg_match("/^SELECT\\s+RAISE\\(\\s*ABORT\\s*,\\s*'((?:''|[^'])*)'\\s*\\)\\s*;?$/i", $body, $raise) === 1) {
                $message = str_replace("''", "'", $raise[1]);
                $body = "IF ({$condition}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '".str_replace("'", "''", $message)."'; END IF;";
            } else {
                $body = "IF ({$condition}) THEN {$body} END IF;";
            }
        } elseif (preg_match("/^SELECT\\s+RAISE\\(\\s*ABORT\\s*,\\s*'((?:''|[^'])*)'\\s*\\)\\s*;?$/i", $body, $raise) === 1) {
            $message = str_replace("''", "'", $raise[1]);
            $body = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '".str_replace("'", "''", $message)."';";
        }

        return "CREATE TRIGGER `{$name}` {$timing} {$event} ON `{$table}` FOR EACH ROW BEGIN {$body} END";
    }

    private static function translateCondition(string $condition): string
    {
        $condition = preg_replace(
            "/typeof\\(\\s*NEW\\.[a-zA-Z0-9_]+\\s*\\)\\s*<>\\s*'integer'/i",
            'FALSE',
            $condition,
        ) ?? $condition;

        return preg_replace_callback(
            '/\b(NEW|OLD)\.([a-zA-Z0-9_]+)\s+IS\s+NOT\s+(NEW|OLD)\.([a-zA-Z0-9_]+)/i',
            static fn (array $match): string => "NOT ({$match[1]}.{$match[2]} <=> {$match[3]}.{$match[4]})",
            $condition,
        ) ?? $condition;
    }
}
