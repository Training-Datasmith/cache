# Architecture: cache

## Purpose

The PSR-6 cache interface definitions (`psr/cache`). This package contains only PHP interfaces — no implementations. It standardises how PHP libraries interact with caching backends so implementations are interchangeable.

## Directory Structure

```
src/
  Cache_Item_Interface.php       — Interface for a single cache entry (get, set, isHit, expiresAt, etc.)
  Cache_Item_Pool_Interface.php  — Interface for the cache pool (getItem, save, delete, clear, etc.)
  Cache_Exception.php            — Marker interface for all PSR-6 exceptions
  Invalid_Argument_Exception.php — Thrown for invalid cache keys (extends Cache_Exception)
```

## Key Design Decisions

- **Interface-only** — this package ships zero implementation code. It defines the contract that cache libraries (Symfony Cache, Stash, etc.) must satisfy.
- **Two-interface design** — `Cache_Item_Interface` represents a single item; `Cache_Item_Pool_Interface` represents the pool/store. Items are retrieved from the pool, mutated, then saved back.
- **Deferred saves** — `Cache_Item_Pool_Interface::save_deferred()` + `commit()` allow batching multiple writes into a single backend call.

## Extension Points

None — this is a specification package. Implement the interfaces in your cache library.

## Dependency Flow

```
Application
  └── Cache_Item_Pool_Interface (Symfony Cache, Stash, etc.)
        └── Cache_Item_Interface (returned by get_item / get_items)
```
