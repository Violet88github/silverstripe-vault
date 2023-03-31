<?php

namespace Violet88\VaultModule\SearchFilter;

use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Filters\SearchFilter;
use Violet88\VaultModule\VaultClient;

/**
 * The EncryptedSearchField class is a new SilverStripe SearchFilter that allows for searching through blind indexes for encrypted data, but otherwise behaves as a normal SearchFilter.
 *
 * @package Violet88\VaultModule\SearchFilter
 * @author  Violet88 @violet88github <info@violet88.nl>
 * @author  Roël Couwenberg @PixNyb <contact@roelc.me>
 * @access  public
 */
class EncryptedSearchFilter extends SearchFilter
{
    protected function applyOne(DataQuery $query)
    {
        // Search through the blind index for the encrypted data
        $column = $this->getDbName();

        $term = $this->getValue();
        $term_hmac = VaultClient::create()->encrypt($term);
        $blindIndexColumn = rtrim($column, '"') . '_bidx"';

        $query->where([
            $blindIndexColumn => $term_hmac
        ]);
    }

    protected function excludeOne(DataQuery $query)
    {
        // Search through the blind index for the encrypted data
        $column = $this->getDbName();

        $term = $this->getValue();
        $term_hmac = VaultClient::create()->encrypt($term);
        $blindIndexColumn = rtrim($column, '"') . '_bidx"';

        $query->where([
            $blindIndexColumn . ' != ?' => $term_hmac
        ]);
    }
}
