<?php

namespace App\Cart;

/**
 * Test seam invoked inside the merge DB transaction before commit.
 * Production binding is a no-op; tests may replace it to force rollback.
 */
class CartMergeTransactionHook
{
    public function beforeCommit(): void
    {
        //
    }
}
