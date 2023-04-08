<?php

namespace Violet88\VaultModule\Tasks;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use Violet88\VaultModule\VaultClient;

class RotateKeyTask extends BuildTask
{
    private static $segment = 'RotateKeyTask';

    protected $title = 'Rotate encryption key';
    protected $description = 'Rotate the encryption key used to encrypt data in the database. This will decrypt all data using the old key, then re-encrypt it using the new key.';
    protected $enabled = true;

    public function run($request)
    {
        $startTime = microtime(true);
        $valueCount = 0;
        $classCount = 0;

        try {
            $client = VaultClient::create();
            $client->getKey()->rotate();
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $executionTime = round($executionTime * 100000) / 100000;

            error_log("Key rotation failed in $executionTime seconds.");
            exit('Key rotation failed in ' . $executionTime . ' seconds.');
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

            error_log("Rewrapping $className...");
            error_log("Found " . count($objects) . " $className(s).");

            foreach ($objects as $object) {
                $actual_encrypted_fields = array_filter($encrypted_fields, function ($field) use ($object) {
                    return str_starts_with($object->$field ?? '', 'vault:');
                });

                if (count($actual_encrypted_fields) === 0) {
                    error_log("No encrypted data found in $className #{$object->ID}.");
                    continue;
                }

                error_log("Rewrapping $className #{$object->ID}...");

                $data = array_values(array_map(function ($field) use ($object) {
                    return $object->$field;
                }, $actual_encrypted_fields));

                $decrypted_data = $client->rewrap($data);

                $valueCount += count($decrypted_data);

                DB::prepared_query("UPDATE $table_name SET " . implode(' = ?, ', $actual_encrypted_fields) . ' = ? WHERE ID = ?', array_merge($decrypted_data, [$object->ID]));
                error_log("Rewrapped $className #{$object->ID}.");
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $executionTime = round($executionTime * 100000) / 100000;

        error_log("Key rotation completed in $executionTime seconds.");

        // Get the file contents from ../html/TaskResult.html. Using SilverStripe templates doesn't work for some reason.
        $html = file_get_contents(__DIR__ . '/../html/TaskResult.html');

        $data = [
            '%Title%' => 'Key rotation',
            '%Status%' => 'completed',
            '%Subtitle%' => 'Successfully rotated the encryption key.',
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
