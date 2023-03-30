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
        $client = VaultClient::create();
        $classes = ClassInfo::subclassesFor(DataObject::class);
        array_shift($classes);

        foreach ($classes as $class) {
            $fields = $class::config()->get('db');
            $encrypted_fields = array_keys(array_filter($fields, function ($field) {
                return str_starts_with($field, 'Encrypted');
            }));
            $table_name = (new $class)->baseTable();
            $objects = $class::get();

            if (count($encrypted_fields) === 0)
                continue;

            error_log("Encrypting $table_name...");
            error_log("Found " . count($objects) . " objects.");

            foreach ($objects as $object) {
                foreach ($encrypted_fields as $field) {
                    $value = $object->$field;

                    if (str_starts_with($value ?? '', 'vault:'))
                        continue;

                    $encrypted_value = $client->encrypt($value ?? 'null');

                    DB::prepared_query("UPDATE $table_name SET $field = ? WHERE ID = ?", [$encrypted_value, $object->ID]);
                }
            }
        }

        exit('Done!');
    }
}