<?php

namespace App\Exceptions;

use RuntimeException;

class CatalogTaxonomyException extends RuntimeException
{
    public static function invalidParent(): self
    {
        return new self(__('The selected parent category is invalid.'));
    }

    public static function maxDepthExceeded(): self
    {
        return new self(__('Categories may have at most three levels (root, subcategory, leaf).'));
    }

    public static function selfParent(): self
    {
        return new self(__('A category cannot be its own parent.'));
    }

    public static function cyclicParent(): self
    {
        return new self(__('A category cannot be nested under one of its descendants.'));
    }
}
