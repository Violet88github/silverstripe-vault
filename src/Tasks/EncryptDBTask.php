<?php

namespace Violet88\VaultModule\Tasks;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Violet88\VaultModule\VaultClient;

class EncryptDBTask extends BuildTask
{
    private static $segment = 'EncryptDBTask';

    protected $title = 'Encrypt database';
    protected $description = 'Encrypt all unencrypted data that should be encrypted in the database using the current encryption key. Already encrypted data will be skipped.';
    protected $enabled = true;

    public function run($request)
    {
        $startTime = microtime(true);

        $classCount = 0;
        $valueCount = 0;

        $client = VaultClient::create();
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

            error_log("Encrypting $className...");
            error_log("Found " . count($objects) . " $className(s).");

            foreach ($objects as $object) {
                $data = array_values(array_map(function ($field) use ($object) {
                    return $object->$field ?? 'null';
                }, $encrypted_fields));

                $data = array_filter($data, function ($value) {
                    return !str_starts_with($value, 'vault:');
                });

                if (count($data) === 0) {
                    error_log("No unencrypted data found in $className #{$object->ID}.");
                    continue;
                }

                error_log("Encrypting $className #{$object->ID}...");

                $valueCount += count($data);

                $decrypted_data = $client->encrypt($data);

                $blind_idx_fields = array_map(function ($field) {
                    return $field . '_bidx';
                }, $encrypted_fields);

                $actual_encrypted_fields = array_merge($encrypted_fields, $blind_idx_fields);
                $decrypted_data = array_merge($decrypted_data, array_map(function ($value) use ($client) {
                    return $client->hmac($value);
                }, $decrypted_data));

                DB::prepared_query("UPDATE $table_name SET " . implode(' = ?, ', $actual_encrypted_fields) . ' = ? WHERE ID = ?', array_merge($decrypted_data, [$object->ID]));
                error_log("Encrypted $className #{$object->ID}.");
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $executionTime = round($executionTime * 100000) / 100000;

        error_log("Encryption completed in $executionTime seconds.");

        // Get the file contents from ../html/TaskResult.html. Using SilverStripe templates doesn't work for some reason.
        $html = file_get_contents(__DIR__ . '/../html/TaskResult.html');

        $data = [
            '%Title%' => 'Database encryption',
            '%Status%' => 'completed',
            '%Subtitle%' => 'Successfully encrypted the database',
            '%ID%' => 'rotate-key' . time(),
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
