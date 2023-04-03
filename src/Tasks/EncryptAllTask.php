<?php

namespace Violet88\VaultModule\Tasks;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Violet88\VaultModule\VaultClient;

class EncryptAllTask extends BuildTask
{
    private static $segment = 'EncryptAllTask';

    protected $title = 'Encrypt all data';
    protected $description = 'Encrypts all data that should be encrypted in the database.';
    protected $enabled = true;

    public function run($request)
    {
        $startTime = microtime(true);
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

            error_log("Encrypting $className...");
            error_log("Found " . count($objects) . " $className(s).");

            foreach ($objects as $object) {
                foreach ($encrypted_fields as $field) {
                    $value = $object->$field;

                    if (str_starts_with($value ?? '', 'vault:'))
                        continue;

                    try {
                        $encrypted_value = $client->encrypt($value ?? 'null');
                        $hmac_value = $client->hmac($value ?? 'null');

                        error_log("Encrypted $field in $className #{$object->ID} to " . substr($encrypted_value, 0, 20) . '... with HMAC ' . substr($hmac_value, 0, 20) . '...');
                    } catch (\Exception $e) {
                        error_log("Error encrypting $field in $className #{$object->ID}: " . $e->getMessage());
                        continue;
                    }

                    DB::prepared_query("UPDATE $table_name SET $field = ? WHERE ID = ?", [$encrypted_value, $object->ID]);
                    DB::prepared_query("UPDATE $table_name SET {$field}_bidx = ? WHERE ID = ?", [$hmac_value, $object->ID]);
                }
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $executionTime = round($executionTime * 100000) / 100000;

        error_log("Encryption completed in $executionTime seconds.");
        exit('Done encrypting in ' . $executionTime . ' seconds.');
    }
}
