# TODO: Critical Bug Review

## Keymap Lookup Bug Pattern

**Date**: 2025-11-15
**Priority**: HIGH
**Status**: OPEN

### Issue Description
Discovered a critical bug in `AssignsRoles` where code was using `find($id)` to lookup models by their keymap value (e.g., ULID) instead of their primary key. This caused all role assignments to incorrectly target user ID 1 because `find()` searches by the primary key column (`id`), not the configured keymap column (`ulid`).

### Root Cause
When `enforceMorphKeyMap` is configured to use alternative keys (like `ulid`), the `mapAuthorityByClass()` helper extracts the keymap value. However, subsequent code was using `Model::find($value)` which searches by the model's primary key, not the keymap column.

**Before (broken)**:
```php
$authority = $query->find($authorityId); // Searches by 'id' column
```

**After (fixed)**:
```php
$authority = $query->where(Models::getModelKeyFromClass($authorityClass), $authorityId)->first();
```

### Action Items

1. **Codebase-wide Audit** (REQUIRED)
   - [ ] Search for all `find()` calls that receive values from keymap lookups
   - [ ] Search for `findOrFail()`, `findMany()`, `findOr()` with keymap values
   - [ ] Review all code paths where `Models::getModelKey()` or `mapAuthorityByClass()` is used
   - [ ] Check if values are being used correctly with appropriate lookup methods

2. **Specific Files to Review**
   - [ ] `src/Conductors/GivesAbilities.php` - likely has same pattern
   - [ ] `src/Conductors/RemovesAbilities.php` - likely has same pattern
   - [ ] `src/Conductors/RemovesRoles.php` - likely has same pattern
   - [ ] `src/Conductors/ChecksRoles.php` - verify authority lookups
   - [ ] `src/Conductors/SyncsRolesAndAbilities.php` - verify authority lookups
   - [ ] Any other conductor that accepts `$authority` parameter

3. **Search Patterns**
   ```bash
   # Find potential problematic patterns
   rg "::find\(" src/
   rg "->find\(" src/
   rg "findOrFail\(" src/
   rg "mapAuthorityByClass" src/
   rg "getModelKey" src/
   ```

4. **Regression Test Required**
   - [ ] Create test that configures User model with `ulid` keymap
   - [ ] Create multiple users with different ULIDs
   - [ ] Assign roles to each user via `Warden::assign()->to($user)`
   - [ ] Assert each user received their assigned role (not just user 1)
   - [ ] Test should fail on old code, pass on fixed code
   - [ ] Add similar tests for abilities, removes, checks, syncs

5. **Documentation**
   - [ ] Document the correct pattern for model lookups with keymaps
   - [ ] Add warning in contributor docs about using `find()` with keymap values
   - [ ] Consider adding PHPStan rule to detect this pattern

### Impact
**Critical** - This bug caused data corruption where all role assignments were incorrectly assigned to a single user instead of the intended users. Any production system using keymap configuration with non-default keys is affected.

### Related Code
- Fixed in: `src/Conductors/AssignsRoles.php:143`
- New helper: `ModelRegistry::getModelKeyFromClass()`
- Pattern source: `Support/Helpers::mapAuthorityByClass()`
