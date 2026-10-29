<?php

// Write to an already-open stream; no SQL files are stored in the public directory.
function write_database_backup(mysqli $conn, $stream): int {
    $tables = [];
    foreach ($conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) $tables[] = array_values($row)[0];
    $write = static function (string $sql) use ($stream): void {
        $length = strlen($sql);
        for ($offset = 0; $offset < $length;) {
            $written = fwrite($stream, substr($sql, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('Unable to write the database backup.');
            $offset += $written;
        }
    };
    $write("-- Phbestcoffee database backup\nSET NAMES utf8mb4;\nSET time_zone='+08:00';\nSET FOREIGN_KEY_CHECKS=0;\n");
    $conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $conn->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try {
        foreach ($tables as $table) {
            $name = '`' . str_replace('`', '``', $table) . '`';
            $create = $conn->query('SHOW CREATE TABLE ' . $name)->fetch_row()[1];
            $write("\nDROP TABLE IF EXISTS $name;\n$create;\n");
            $rows = $conn->query('SELECT * FROM ' . $name, MYSQLI_USE_RESULT);
            try {
                while ($row = $rows->fetch_assoc()) {
                    $values = array_map(static fn($value) => $value === null ? 'NULL' : "UNHEX('" . bin2hex((string)$value) . "')", array_values($row));
                    $write('INSERT INTO ' . $name . ' VALUES (' . implode(',', $values) . ");\n");
                }
            } finally { $rows->free(); }
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    $write("\nSET FOREIGN_KEY_CHECKS=1;\n");
    return count($tables);
}
