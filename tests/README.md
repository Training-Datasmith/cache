# Test layout

- `Contract/` is the package's executable coverage. It verifies the public
  interfaces in `src/`, including signatures, inheritance, and structured
  PHPDoc types that PHP cannot express.
- `Fixtures/` contains a small in-memory reference implementation used only by
  the tests. It is not shipped package behavior.
- `Integration/` exercises mandatory PSR-6 behavior against that reference
  implementation. It is not a reusable conformance suite for other cache
  implementations.
