<?php

namespace Violet88\VaultModule\Tasks;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use Violet88\VaultModule\VaultClient;

class DecryptAllTask extends BuildTask
{
    private static $segment = 'DecryptAllTask';

    protected $title = 'Decrypt all data';
    protected $description = 'Decrypts all encrypted data in the database, allowing you to change encryption keys or field types.';
    protected $enabled = true;

    public function run($request)
    {
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

            error_log("Decrypting $className...");
            error_log("Found " . count($objects) . " $className(s).");

            foreach ($objects as $object) {
                foreach ($encrypted_fields as $field) {
                    $value = $object->$field;

                    if (!str_starts_with($value ?? '', 'vault:'))
                        continue;

                    try {
                        $decrypted_value = $client->decrypt($value);

                        if ($decrypted_value === 'null')
                            $decrypted_value = null;
                    } catch (\Exception $e) {
                        error_log("Error decrypting $field in $className #{$object->ID}: " . $e->getMessage());
                        continue;
                    }

                    DB::prepared_query("UPDATE $table_name SET $field = ? WHERE ID = ?", [$decrypted_value, $object->ID]);
                    DB::prepared_query("UPDATE $table_name SET {$field}_bidx = ? WHERE ID = ?", [null, $object->ID]);
                }
            }
        }

        exit('Done!');
    }
}
