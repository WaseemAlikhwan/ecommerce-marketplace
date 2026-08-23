# S5A Docker/MySQL gate acceptance

**Status:** Accepted  
**Date:** 2026-08-20

## Compose

All services running; MySQL healthy.

## PHPUnit (app container)

- **208 tests / 1227 assertions** — passed after rebuilding `app`/`queue` images with GD JPEG/WebP support.

## GD / image extensions (app container)

- GD loaded
- JPEG encode/decode
- PNG support
- WebP encode/decode
- `exif_read_data`
- `fileinfo`

## Disposable MySQL database

- Zero-to-full migrate + seed
- Verified `product_images`, `product_image_translations`, `products.primary_image_id`
- Verified `pi_product_store_fk`, `products_primary_image_fk`, `pi_path_uq`, `pi_product_pos_uq`
- S5A rollback removed image tables and `primary_image_id`; catalog tables remained
- S5A re-migrate restored constraints
- Disposable database dropped

## Development database

- In-place `php artisan migrate` applied S5A migration (no `migrate:fresh`).
