# HN Branch Sync Guide

This document describes Branch (HN) sync endpoints and flows with Central.

## Environment
- APP_BRANCH_CODE: `HN`
- CENTRAL_API_URL: e.g. `https://cps.duyet.dev`
- CENTRAL_API_KEY: shared secret for Central→Branch calls

## Endpoints (Branch)

- POST `/api/upsert/products`
  - Auth: header `X-API-Key: <CENTRAL_API_KEY>`
  - Body: JSON array of products with fields: `id, sku, name, brand_name, price, promo_price, status, msrp, ext_json?, updated_at`
  - Effect: upsert rows into `products_replica`

- POST `/api/upsert/employees`
  - Auth: `X-API-Key`
  - Body: JSON array of employees; only `branch_code === 'HN'` are stored
  - Effect: upsert rows into `employee_replica`

- POST `/api/set-stock`
  - Auth: `X-API-Key`
  - Body: `{ "product_id": number, "qty": number }`
  - Effect: upsert row into `branch_inventory (product_id, qty)` (absolute set)

- GET `/api/catalog`
  - Public (for demo) — returns effective catalog via `v_pos_catalog`

## Manual Pull (Branch UI)

- POST `/sync/pull/products` → calls Central `/api/products`, upserts into `products_replica`
- POST `/sync/pull/employees` → calls Central `/api/employees`, upserts branch employees

## Notes
- Employees for other branches are ignored by server-side filter.
- Central should call these endpoints after changes, and Branch can also trigger manual pulls.
- Stock for selling can come from `inventory` or `branch_inventory`; for absolute set, prefer `branch_inventory`.
