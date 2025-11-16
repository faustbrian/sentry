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

**Console command tests DIRECTLY leak state to ALL migrator tests.**

Critical discovery: When ALL other tests were excluded and only Console + Migrator tests ran, the migrator tests STILL failed. This proves Console tests are the direct cause, NOT propagating state from other tests.

**Both Console command tests must be excluded for the full test suite to pass:**
- Excluding only `MigrateFromBouncerCommandTest.php` is not sufficient
- Excluding only `MigrateFromSpatieCommandTest.php` is not sufficient
- BOTH must be excluded for tests to pass

The pattern is clear: **Console commands that execute migrators leave behind state that breaks migrator unit tests.**

## Files Involved

### Leaking Tests (CONFIRMED)
- **`tests/Unit/Console/MigrateFromBouncerCommandTest.php`** ✓ CONFIRMED CULPRIT
- **`tests/Unit/Console/MigrateFromSpatieCommandTest.php`** ✓ CONFIRMED CULPRIT

### Affected Tests
- `tests/Unit/Migrators/BouncerMigratorTest.php` (17 tests fail)
- `tests/Unit/Migrators/GuardMigrationTest.php` (fails)
- `tests/Unit/Migrators/SpatieMigratorTest.php` (fails)

### Pattern
The Console commands that **call** the migrators are affecting the migrator **tests** themselves. This suggests the commands are leaving behind state that the migrator tests then encounter.

## Investigation Tasks

**Agent Instructions: Debug state leakage from Console command tests to Migrator tests**

### Phase 1: Analyze Both Console Command Tests ✅ CONFIRMED CULPRITS

**Status**: Both `MigrateFromBouncerCommandTest.php` and `MigrateFromSpatieCommandTest.php` are confirmed to leak state.

**Next Steps**:
1. Read both test files to understand their structure and lifecycle hooks
2. Identify which specific test method(s) in each file cause the leakage
3. Focus on tests that actually execute the migration commands (not just test setup/validation)

### Phase 2: Identify Specific State Leakage Vectors

**Priority Areas** (Console tests executing migration commands):

1. **Database Schema State** 🔥 HIGHEST PRIORITY
   - Console commands run actual migrations via `$this->artisan('migrate:from:bouncer')`
   - These migrations modify the database schema
   - Check if schema changes persist between tests
   - Look for missing database rollbacks/cleanup in `afterEach()`
   - **Key question**: Are Console tests running migrations that create/modify tables, and NOT cleaning them up?

2. **Configuration State** 🔥 HIGH PRIORITY
   - Console commands may set config values during migration
   - Check for `Config::set()` calls without corresponding cleanup
   - **Specific configs to examine**:
     - `warden.migrators.bouncer.*` config keys
     - `warden.primary_key_type`
     - `warden.*_morph_type` settings (ulidMorph specifically)
   - Compare: Do Console tests restore config in `afterEach()`?

3. **Model Boot State** 🔥 HIGH PRIORITY
   - Console commands boot Eloquent models with specific configurations
   - Migration process may boot models differently than unit tests expect
   - **Check**: Do Console tests call `Model::clearBootedModels()` in cleanup?
   - **Note**: BouncerMigratorTest DOES clear booted models in `afterEach()`

4. **Database Records State**
   - Console commands insert/modify records as part of migration
   - Check if test data persists across test runs
   - Look for missing `RefreshDatabase` or manual cleanup

5. **ULID-Specific State** 🔥 CRITICAL
   - **Failure ONLY occurs with ULID primary keys** (`make test:docker:postgres:ulid`)
   - Check for primary key type conflicts
   - Check for morph key type conflicts (ulidMorph vs default)
   - **Hypothesis**: Console tests may be setting/using different morph types or primary key configurations that persist

6. **Singleton/Container State**
   - Are facades being resolved and cached with wrong configurations?
   - Is there container state being leaked?
   - Check for service provider registrations that persist

### Phase 3: Compare Test Lifecycle Hooks

**Compare cleanup strategies** between Console command tests and Migrator tests:

1. **Read and compare**:
   - `tests/Unit/Console/MigrateFromBouncerCommandTest.php` - lifecycle hooks
   - `tests/Unit/Console/MigrateFromSpatieCommandTest.php` - lifecycle hooks
   - `tests/Unit/Migrators/BouncerMigratorTest.php` - lifecycle hooks (reference)

2. **Specific comparison points**:
   - Does Console test have `afterEach()` hook? If not, ADD IT.
   - Does Console test call `Model::clearBootedModels()`? If not, ADD IT.
   - Does Console test reset config values? If not, ADD IT.
   - Does Console test clean up database schema changes? If not, ADD IT.
   - What database refresh strategy is used? (`RefreshDatabase` trait, manual cleanup, etc.)

3. **Look for asymmetry**:
   - Migrator tests may assume clean state that Console tests don't provide
   - Console tests may create state that Migrator tests don't expect

### Phase 4: Capture and Analyze Error Details

**To reproduce the failure**, temporarily re-enable ONE Console command test:

1. **Edit `phpunit.ulid.xml`** - remove ONE exclude line:
   ```bash
   # Remove this line temporarily:
   # <exclude>./tests/Unit/Console/MigrateFromBouncerCommandTest.php</exclude>
   ```

2. **Run tests and capture full error output**:
   ```bash
   make test:docker:postgres:ulid 2>&1 | tee test_failure_output.txt
   ```

3. **Analyze the error**:
   - What is the exact ErrorException message?
   - What line number is failing in which Migrator test?
   - Is it a database error (schema/constraint)?
   - Is it a type error (ULID vs integer)?
   - Is it a configuration error (missing config)?
   - Is it a model state error (unexpected boot state)?

4. **Restore `phpunit.ulid.xml`** after capturing the error

### Phase 5: Implement Fix

**Based on findings from Phases 1-4, implement the appropriate fix:**

#### Option A: Add Proper Cleanup to Console Command Tests (MOST LIKELY)

If Console tests are missing cleanup hooks:

1. **Add `afterEach()` hook to both Console command tests**:
   ```php
   afterEach(function () {
       Model::clearBootedModels();
       // Add other cleanup as needed
   });
   ```

2. **Reset configuration state** if config is modified:
   ```php
   afterEach(function () {
       Config::set('warden.migrators.bouncer', $originalConfig);
       Config::set('warden.primary_key_type', $originalKeyType);
       // etc.
   });
   ```

3. **Clean up database schema** if migrations are run:
   - Roll back migrations that were run during test
   - Or use proper database refresh strategy

#### Option B: Fix ULID-Specific Configuration Issue

If the issue is specific to ULID morph types:

1. **Ensure morph types are properly set/reset** in Console tests
2. **Check if Console tests override morph type config** and don't restore it
3. **Verify that ULID models are booted correctly** with proper morph types

#### Option C: Fix Underlying Bug in Application Code

If the investigation reveals an actual bug:

1. **Document the bug** in the codebase
2. **Fix the root cause** in the application code (not just tests)
3. **Ensure tests properly validate** the fix

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
