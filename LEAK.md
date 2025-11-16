# Test State Leakage Investigation

## Problem Summary

`Tests\Unit\Migrators\BouncerMigratorTest` fails when run as part of the full test suite via `make test:docker:postgres:ulid` but passes when run in isolation.

## Root Cause Identified

Through systematic test isolation, we identified that **tests in `./tests/Unit/Console/`** are leaking state that causes BouncerMigratorTest to fail.

### Evidence

1. **Baseline**: BouncerMigratorTest passes when run alone ✅
2. **Full Suite**: BouncerMigratorTest fails when all tests run ❌
3. **Systematic Exclusion**: Tested each test file/directory one by one
4. **Failure Point**: When `./tests/Unit/Console/` tests were included, BouncerMigratorTest started failing with ErrorException

### Test Results

- **Root level tests only** (Console excluded): 420 tests pass including BouncerMigratorTest ✅
- **Console + BouncerMigrator only**: 26 tests pass ✅
- **Root level tests + Console + BouncerMigrator**: 17 BouncerMigratorTest tests fail with ErrorException ❌
- **Full suite with Console excluded**: All 1,069 tests pass including BouncerMigratorTest ✅

### Key Finding

**Console tests are propagating state from root level tests to BouncerMigrator.**

The failure only occurs when:
1. Root level tests run first (setting up some state)
2. Console tests run second (picking up that state and transforming it somehow)
3. BouncerMigrator runs third (failing due to the propagated/transformed state)

This is NOT a simple case of Console tests leaking state directly - Console tests are acting as a **conduit** that propagates state from earlier tests.

## Files Involved

### Leaking Tests (Suspects)
- `tests/Unit/Console/MigrateFromBouncerCommandTest.php`
- `tests/Unit/Console/MigrateFromSpatieCommandTest.php`

### Affected Test
- `tests/Unit/Migrators/BouncerMigratorTest.php`

## Investigation Tasks

**Agent Instructions: Investigate why Console tests affect Migrator tests**

### Phase 1: Identify the Specific Test
1. Determine which specific test file in `tests/Unit/Console/` causes the leak
   - Test `MigrateFromBouncerCommandTest.php` in isolation
   - Test `MigrateFromSpatieCommandTest.php` in isolation
2. If both cause issues, identify which specific test method is the culprit

### Phase 2: Analyze State Leakage
Examine the leaking test(s) for:

1. **Database State**
   - Are tables being modified and not cleaned up?
   - Are migrations being run that affect schema?
   - Are records being inserted without proper cleanup?

2. **Configuration State**
   - Are config values being set via `Config::set()` and not restored?
   - Check specifically for:
     - `warden.migrators.bouncer.*` config keys
     - `warden.primary_key_type`
     - `warden.*_morph_type` settings

3. **Model Boot State**
   - Are Eloquent models being booted with specific configurations?
   - Is `Model::clearBootedModels()` being called in teardown?
   - Note: BouncerMigratorTest already calls this in `afterEach()`

4. **Singleton/Static State**
   - Are there any static properties or singletons being modified?
   - Are facades being resolved and cached?
   - Is there container state being leaked?

5. **ULID-Specific Issues**
   - The failure only occurs with `make test:docker:postgres:ulid`
   - Check for primary key type conflicts
   - Check for morph key type conflicts
   - Examine how Console tests handle ULID vs standard IDs

### Phase 3: Compare Test Setup/Teardown

Compare the setup and teardown between:
- `tests/Unit/Console/MigrateFromBouncerCommandTest.php`
- `tests/Unit/Migrators/BouncerMigratorTest.php`

Look for:
- Missing `afterEach()` cleanup in Console tests
- Different database refresh strategies
- Different config handling approaches

### Phase 4: Review Error Details

Run the failing test suite and capture the full ErrorException details:
```bash
make test:docker:postgres:ulid 2>&1 | grep -A 20 "BouncerMigratorTest"
```

Examine:
- What is the exact error message?
- What line is failing in BouncerMigratorTest?
- Is it a database error, type error, or configuration error?

### Phase 5: Propose Fix

Based on findings, propose one of:

1. **Add proper cleanup** to Console tests
   - Add missing `afterEach()` hooks
   - Clear config state after each test
   - Reset database state properly

2. **Improve test isolation** in BouncerMigratorTest
   - Add defensive checks for config state
   - Add explicit database cleanup
   - Add model boot state verification

3. **Fix underlying issue** if there's a real bug
   - If the leakage reveals an actual application bug
   - If there's improper state management in the migrator itself

## Success Criteria

The investigation is complete when:
1. ✅ We know exactly which Console test(s) cause the failure
2. ✅ We understand what state is being leaked
3. ✅ We have a clear fix that makes `make test:docker:postgres:ulid` pass with all tests enabled

## Current Workaround

Temporarily exclude Console tests in `phpunit.ulid.xml`:
```xml
<exclude>./tests/Unit/Console</exclude>
```

This workaround should be removed once the root cause is fixed.
