<?php

namespace Violet88\VaultModule\Tasks;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use Violet88\VaultModule\VaultClient;

class DecryptDBTask extends BuildTask
{
    private static $segment = 'DecryptDBTask';

    protected $title = 'Decrypt database';
    protected $description = 'Decrypt all encrypted data in the database, allowing you to change encryption keys or field types.';
    protected $enabled = true;

    public function run($request)
    {
        $startTime = microtime(true);

        $classCount = 0;
        $valueCount = 0;

        try {
            $client = VaultClient::create();
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $executionTime = round($executionTime * 100000) / 100000;

            error_log("Decryption failed in $executionTime seconds.");
            exit('Decryption failed in ' . $executionTime . ' seconds.');
        }

        $classes = ClassInfo::subclassesFor(DataObject::class);
        array_shift($classes);

        foreach ($classes as $class) {
            $className = ClassInfo::shortName($class);
            $fields = $class::config()->get('db');
            $encrypted_fields = array_keys(array_filter($fields, function ($field) {
                return str_starts_with($field, 'Encrypted');
            }));
            $table_name = (new $class)->baseTable();
            $objects = $class::get();

            if (count($encrypted_fields) === 0)
                continue;

            $classCount++;

            error_log("Decrypting $className...");
            error_log("Found " . count($objects) . " $className(s).");

            foreach ($objects as $object) {
                $actual_encrypted_fields = array_filter($encrypted_fields, function ($field) use ($object) {
                    return str_starts_with($object->$field ?? '', 'vault:');
                });

                if (count($actual_encrypted_fields) === 0) {
                    error_log("No encrypted data found in $className #{$object->ID}.");
                    continue;
                }

                error_log("Decrypting $className #{$object->ID}...");

                $valueCount += count($actual_encrypted_fields);

                $data = array_values(array_map(function ($field) use ($object) {
                    return $object->$field;
                }, $actual_encrypted_fields));

                $decrypted_data = $client->decrypt($data);

                $decrypted_data = array_map(function ($value) {
                    if ($value === 'null')
                        return null;
                    return $value;
                }, $decrypted_data);

                $blind_idx_fields = array_map(function ($field) {
                    return $field . '_bidx';
                }, $actual_encrypted_fields);

                $actual_encrypted_fields = array_merge($actual_encrypted_fields, $blind_idx_fields);
                $decrypted_data = array_merge($decrypted_data, $decrypted_data);

                DB::prepared_query("UPDATE $table_name SET " . implode(' = ?, ', $actual_encrypted_fields) . ' = ? WHERE ID = ?', array_merge($decrypted_data, [$object->ID]));
                error_log("Decrypted $className #{$object->ID}.");
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $executionTime = round($executionTime * 100000) / 100000;

        error_log("Decryption completed in $executionTime seconds.");

        // Get the file contents from ../html/TaskResult.html. Using SilverStripe templates doesn't work for some reason.
        $html = file_get_contents(__DIR__ . '/../html/TaskResult.html');

        $data = [
            '%Title%' => 'Database decryption',
            '%Status%' => 'completed',
            '%Subtitle%' => 'Successfully decrypted the database',
            '%ID%' => 'decrypt-db' . time(),
        ];

        $extraData = [
            'Time taken:' => $executionTime . 's',
            'Classes:' => $classCount,
            'Fields:' => $valueCount,
        ];

        $html = preg_replace(array_keys($data), array_values($data), $html);

        $extraDataHtml = '';
        foreach ($extraData as $key => $value)
            $extraDataHtml .= '<tr><td>' . $key . '</td><td><span class="colored">' . $value . '</span></td></tr>';

        $html = preg_replace('%DataTable%', $extraDataHtml, $html);
        exit($html);
    }
}
